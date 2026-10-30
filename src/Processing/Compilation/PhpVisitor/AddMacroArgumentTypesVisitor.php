<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

/**
 * Types the arguments of macros with the union of the arguments that they are called with.
 *
 * Find:    new \Twig\TwigMacro("minutes", function ($seconds = \null, ...$varargs): string|\Twig\Markup {
 *              $context = ["seconds" => $seconds, "varargs" => $varargs] + $this->env->getGlobals();
 * Append:      twigstan_type_hint($context, 'seconds', 'int', false);
 */
final class AddMacroArgumentTypesVisitor extends NodeVisitorAbstract
{
    /**
     * @param array<string, list<ArrayShapeNode>> $calls The arguments of each call to each macro
     */
    public function __construct(
        private readonly array $calls,
    ) {}

    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Expr\New_ || ! $node->class instanceof Node\Name || $node->class->toString() !== 'Twig\TwigMacro') {
            return null;
        }

        $args = $node->getArgs();

        if (count($args) < 2 || ! $args[0]->value instanceof Scalar\String_ || ! $args[1]->value instanceof Expr\Closure) {
            return null;
        }

        $calls = $this->calls[$args[0]->value->value] ?? [];

        if ($calls === []) {
            return null;
        }

        $closure = $args[1]->value;

        $typeHints = [];
        foreach ($closure->params as $position => $param) {
            if ($param->variadic || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $type = $this->getType($calls, $position, $param->var->name, $param->default);

            if ($type === null) {
                continue;
            }

            $typeHints[] = new Stmt\Expression(new Expr\FuncCall(
                new Node\Name('twigstan_type_hint'),
                [
                    new Node\Arg(new Expr\Variable('context')),
                    new Node\Arg(new Scalar\String_($param->var->name)),
                    new Node\Arg(new Scalar\String_($type)),
                    new Node\Arg(new Expr\ConstFetch(new Node\Name('false'))),
                ],
            ));
        }

        // Find: $context = ["seconds" => $seconds, "varargs" => $varargs] + $this->env->getGlobals();
        foreach ($closure->stmts as $index => $stmt) {
            if (
                $stmt instanceof Stmt\Expression
                && $stmt->expr instanceof Expr\Assign
                && $stmt->expr->var instanceof Expr\Variable
                && $stmt->expr->var->name === 'context'
            ) {
                array_splice($closure->stmts, $index + 1, 0, $typeHints);

                return $node;
            }
        }

        return null;
    }

    /**
     * @param list<ArrayShapeNode> $calls
     */
    private function getType(array $calls, int $position, string $name, ?Expr $default): ?string
    {
        $types = [];
        foreach ($calls as $arguments) {
            $passed = false;
            foreach ($arguments->items as $index => $item) {
                $key = match (true) {
                    $item->keyName === null => $index,
                    $item->keyName instanceof ConstExprIntegerNode => (int) $item->keyName->value,
                    $item->keyName instanceof ConstExprStringNode => $item->keyName->value,
                    $item->keyName instanceof IdentifierTypeNode => $item->keyName->name,
                    default => null,
                };

                if ($key !== $position && $key !== $name) {
                    continue;
                }

                $types[] = (string) $item->valueType;
                $passed = ! $item->optional;
            }

            if ($passed) {
                continue;
            }

            // Twig passes the default value, or null when there is none.
            $defaultType = $default === null ? 'null' : $this->getDefaultType($default);

            if ($defaultType === null) {
                return null;
            }

            $types[] = $defaultType;
        }

        $types = array_values(array_unique($types));

        if (count($types) === 1) {
            return $types[0];
        }

        return implode('|', array_map(fn($type) => sprintf('(%s)', $type), $types));
    }

    private function getDefaultType(Expr $default): ?string
    {
        return match (true) {
            $default instanceof Scalar\String_ => sprintf("'%s'", addcslashes($default->value, "'\\")),
            $default instanceof Scalar\Int_, $default instanceof Scalar\Float_ => (string) $default->value,
            $default instanceof Expr\ConstFetch => strtolower($default->name->toString()),
            $default instanceof Expr\Array_ && $default->items === [] => 'array{}',
            default => null,
        };
    }
}
