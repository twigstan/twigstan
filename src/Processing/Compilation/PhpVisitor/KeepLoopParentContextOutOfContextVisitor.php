<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Twig stores a copy of the whole context in the context itself at every for loop, and another one in loop.parent.
 * Every nested loop repeats it, so the size of the context type grows exponentially with the nesting, and PHPStan
 * generalizes the context once it gets too complex, losing the types of all variables. The copy is kept in a local
 * variable instead, and loop.parent is only kept when the template might read it.
 *
 * Find:
 *     $context['_parent'] = $context;
 *     $context['_seq'] = CoreExtension::ensureTraversable($context["items"]);
 *     $context['loop'] = ['parent' => $context['_parent'], 'index0' => 0, 'index' => 1, 'first' => true];
 *     ...
 *     foreach ($context['_seq'] as $context["_key"] => $context["item"]) { ... }
 *     $_parent = $context['_parent'];
 *     unset($context['_seq'], $context['_key'], $context['item'], $context['_parent'], $context['loop']);
 *     $context = array_intersect_key($context, $_parent) + $_parent;
 *
 * (Twig 3.30 and later merge in two statements: `$context = array_intersect_key($context, $_parent);` and
 * `$context += $_parent;`.)
 *
 * Replace:
 *     $_parent1 = $context;
 *     $context['_seq'] = CoreExtension::ensureTraversable($context["items"]);
 *     $context['loop'] = ['index0' => 0, 'index' => 1, 'first' => true];
 *     ...
 *     foreach ($context['_seq'] as $context["_key"] => $context["item"]) { ... }
 *     unset($context['_seq'], $context['_key'], $context['item'], $context['loop']);
 *     $context = array_intersect_key($context, $_parent1) + $_parent1;
 *
 * Nested loops are rewritten first. A loop that still reads $context['_parent'] afterwards (the template uses
 * `_parent`) is left as it is, and so are the loops around it.
 */
final class KeepLoopParentContextOutOfContextVisitor extends NodeVisitorAbstract
{
    private int $counter = 0;

    public function leaveNode(Node $node): null
    {
        if (
            ! $node instanceof Stmt\ClassMethod
            && ! $node instanceof Stmt\Function_
            && ! $node instanceof Expr\Closure
            && ! $node instanceof Stmt\Foreach_
            && ! $node instanceof Stmt\For_
            && ! $node instanceof Stmt\While_
            && ! $node instanceof Stmt\Do_
            && ! $node instanceof Stmt\If_
            && ! $node instanceof Stmt\ElseIf_
            && ! $node instanceof Stmt\Else_
            && ! $node instanceof Stmt\TryCatch
            && ! $node instanceof Stmt\Catch_
            && ! $node instanceof Stmt\Finally_
            && ! $node instanceof Stmt\Case_
            && ! $node instanceof Stmt\Block
        ) {
            return null;
        }

        if ($node->stmts === null) {
            return null;
        }

        $stmts = array_values($node->stmts);
        for ($index = 0; $index < count($stmts); $index++) {
            if ($this->isSnapshot($stmts[$index])) {
                $stmts = $this->rewriteLoop($stmts, $index);
            }
        }

        $node->stmts = $stmts;

        return null;
    }

    /**
     * @param list<Stmt> $stmts
     *
     * @return list<Stmt>
     */
    private function rewriteLoop(array $stmts, int $start): array
    {
        $restore = null;
        for ($index = $start + 1; $index < count($stmts); $index++) {
            if ($this->isRestore($stmts[$index])) {
                $restore = $index;

                break;
            }
        }

        if ($restore === null) {
            return $stmts;
        }

        $unset = $stmts[$restore + 1] ?? null;
        $merge = $this->getMerge(array_slice($stmts, $restore + 2, 2));

        if ( ! $unset instanceof Stmt\Unset_ || $merge === null) {
            return $stmts;
        }

        $loopStmts = array_slice($stmts, $start + 1, $restore - $start - 1);
        $loopParentItem = $this->findLoopParentItem($loopStmts);

        foreach ((new NodeFinder())->find($loopStmts, $this->isContextParent(...)) as $parentRead) {
            if ($loopParentItem === null || $parentRead !== $loopParentItem['item']->value) {
                return $stmts;
            }
        }

        $variable = new Expr\Variable(sprintf('_parent%d', ++$this->counter));

        if ($loopParentItem !== null) {
            if ($this->mayReadLoopParent($loopStmts)) {
                $loopParentItem['item']->value = $variable;
            } else {
                unset($loopParentItem['array']->items[$loopParentItem['key']]);
                $loopParentItem['array']->items = array_values($loopParentItem['array']->items);
            }
        }

        $unset->vars = array_values(array_filter(
            $unset->vars,
            fn(Expr $var) => ! $this->isContextParent($var),
        ));

        foreach ((new NodeFinder())->findInstanceOf($merge, Expr\Variable::class) as $parentVariable) {
            if ($this->isParentVariable($parentVariable)) {
                $parentVariable->name = $variable->name;
            }
        }

        $stmts[$start] = new Stmt\Expression(
            new Expr\Assign($variable, new Expr\Variable('context')),
            $stmts[$start]->getAttributes(),
        );
        unset($stmts[$restore]);

        return array_values($stmts);
    }

    /**
     * The `'parent' => $context['_parent']` item of `$context['loop'] = [...]`.
     *
     * @param array<Stmt> $loopStmts
     *
     * @return null|array{array: Expr\Array_, key: int, item: Node\ArrayItem}
     */
    private function findLoopParentItem(array $loopStmts): ?array
    {
        foreach ($loopStmts as $stmt) {
            if ( ! $stmt instanceof Stmt\Expression || ! $stmt->expr instanceof Expr\Assign) {
                continue;
            }

            if ( ! $this->isContextOffset($stmt->expr->var, 'loop') || ! $stmt->expr->expr instanceof Expr\Array_) {
                continue;
            }

            foreach ($stmt->expr->expr->items as $key => $item) {
                if ($item->key instanceof Scalar\String_ && $item->key->value === 'parent' && $this->isContextParent($item->value)) {
                    return ['array' => $stmt->expr->expr, 'key' => $key, 'item' => $item];
                }
            }
        }

        return null;
    }

    /**
     * Whether $context['loop'] is used in any other way than assigning or unsetting it, or reading an offset of it
     * other than "parent", directly or with CoreExtension::getAttribute().
     *
     * @param array<Stmt> $loopStmts
     */
    private function mayReadLoopParent(array $loopStmts): bool
    {
        $nodeFinder = new NodeFinder();

        $knownUses = [];

        foreach ($nodeFinder->find($loopStmts, fn(Node $node) => true) as $node) {
            $uses = [];

            if ($node instanceof Expr\Assign) {
                $uses = [$node->var];
            } elseif ($node instanceof Stmt\Unset_) {
                $uses = $node->vars;
            } elseif ($node instanceof Expr\ArrayDimFetch && $this->isOffsetOtherThanParent($node->dim)) {
                $uses = [$node->var];
            } elseif ($node instanceof Expr\StaticCall && $node->name instanceof Node\Identifier && $node->name->toLowerString() === 'getattribute') {
                $args = $node->getArgs();

                if (isset($args[2], $args[3]) && $this->isOffsetOtherThanParent($args[3]->value)) {
                    $uses = [$args[2]->value];
                }
            }

            foreach ($uses as $use) {
                if ($this->isContextOffset($use, 'loop')) {
                    $knownUses[spl_object_id($use)] = true;
                }
            }
        }

        foreach ($nodeFinder->find($loopStmts, fn(Node $node) => $this->isContextOffset($node, 'loop')) as $use) {
            if ( ! isset($knownUses[spl_object_id($use)])) {
                return true;
            }
        }

        return false;
    }

    private function isOffsetOtherThanParent(?Node $offset): bool
    {
        if ($offset instanceof Scalar\Int_) {
            return true;
        }

        return $offset instanceof Scalar\String_ && $offset->value !== 'parent';
    }

    /**
     * $context['_parent'] = $context;
     */
    private function isSnapshot(Stmt $stmt): bool
    {
        return $stmt instanceof Stmt\Expression
            && $stmt->expr instanceof Expr\Assign
            && $this->isContextParent($stmt->expr->var)
            && $this->isContext($stmt->expr->expr);
    }

    /**
     * $_parent = $context['_parent'];
     */
    private function isRestore(Stmt $stmt): bool
    {
        return $stmt instanceof Stmt\Expression
            && $stmt->expr instanceof Expr\Assign
            && $this->isParentVariable($stmt->expr->var)
            && $this->isContextParent($stmt->expr->expr);
    }

    /**
     * `$context = array_intersect_key($context, $_parent) + $_parent;`, or `$context = array_intersect_key($context, $_parent);`
     * followed by `$context += $_parent;`.
     *
     * @param list<Stmt> $stmts the statements after the unset()
     *
     * @return null|list<Stmt> the merging statements
     */
    private function getMerge(array $stmts): ?array
    {
        $assign = $stmts[0] ?? null;

        if ( ! $assign instanceof Stmt\Expression || ! $assign->expr instanceof Expr\Assign || ! $this->isContext($assign->expr->var)) {
            return null;
        }

        $merged = $assign->expr->expr;

        if ($merged instanceof Expr\BinaryOp\Plus && $this->isParentVariable($merged->right) && $this->isIntersection($merged->left)) {
            return [$assign];
        }

        $addition = $stmts[1] ?? null;

        if (
            $this->isIntersection($merged)
            && $addition instanceof Stmt\Expression
            && $addition->expr instanceof Expr\AssignOp\Plus
            && $this->isContext($addition->expr->var)
            && $this->isParentVariable($addition->expr->expr)
        ) {
            return [$assign, $addition];
        }

        return null;
    }

    /**
     * array_intersect_key($context, $_parent)
     */
    private function isIntersection(Expr $expr): bool
    {
        if ( ! $expr instanceof Expr\FuncCall || ! $expr->name instanceof Node\Name || $expr->name->toLowerString() !== 'array_intersect_key') {
            return false;
        }

        $args = $expr->getArgs();

        return count($args) === 2 && $this->isContext($args[0]->value) && $this->isParentVariable($args[1]->value);
    }

    private function isParentVariable(Node $node): bool
    {
        return $node instanceof Expr\Variable && $node->name === '_parent';
    }

    private function isContextParent(Node $node): bool
    {
        return $this->isContextOffset($node, '_parent');
    }

    private function isContextOffset(Node $node, string $offset): bool
    {
        return $node instanceof Expr\ArrayDimFetch
            && $this->isContext($node->var)
            && $node->dim instanceof Scalar\String_
            && $node->dim->value === $offset;
    }

    private function isContext(Node $node): bool
    {
        return $node instanceof Expr\Variable && $node->name === 'context';
    }
}
