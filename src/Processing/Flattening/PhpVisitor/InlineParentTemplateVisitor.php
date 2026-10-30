<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use LogicException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Twig\CommentHelper;

final class InlineParentTemplateVisitor extends NodeVisitorAbstract
{
    /**
     * @param list<Node\Stmt> $stmts
     */
    public function __construct(
        private array $stmts,
    ) {}

    private bool $inlined = false;

    /**
     * @return null|array<Node>|int
     */
    public function leaveNode(Node $node): null | array | int
    {
        // Find: $this->parent = $this->load("@EndToEnd/Inheritance/_layout.twig", 1);
        // Replace with the statements of the parent template.

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        // The parent template is inlined, so it must not be rendered again.
        if ($this->inlined && $this->isParentYield($node)) {
            return NodeVisitor::REMOVE_NODE;
        }

        if ( ! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        $assign = $node->expr;

        if ( ! $assign->var instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        if ( ! $assign->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($assign->var->var->name !== 'this') {
            return null;
        }

        if ( ! $assign->var->name instanceof Node\Identifier) {
            return null;
        }

        if ($assign->var->name->name !== 'parent') {
            return null;
        }

        if ( ! $assign->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $assign->expr->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($assign->expr->var->name !== 'this') {
            return null;
        }

        if ( ! $assign->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($assign->expr->name->name !== 'load') {
            return null;
        }

        if (count($assign->expr->args) !== 2) {
            return null;
        }

        $sourceLocation = CommentHelper::getSourceLocationFromComments($node->getComments());

        if ($sourceLocation === null) {
            throw new LogicException('Expected source location to be set.');
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AppendSourceLocationVisitor($sourceLocation));

        $this->inlined = true;

        return $traverser->traverse($this->stmts);
    }

    /**
     * Find: yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
     */
    private function isParentYield(Node\Stmt\Expression $node): bool
    {
        if ( ! $node->expr instanceof Node\Expr\YieldFrom) {
            return false;
        }

        $yield = $node->expr->expr;

        if ( ! $yield instanceof Node\Expr\MethodCall || ! $yield->name instanceof Node\Identifier || $yield->name->name !== 'yield') {
            return false;
        }

        $unwrap = $yield->var;

        if ( ! $unwrap instanceof Node\Expr\MethodCall || ! $unwrap->name instanceof Node\Identifier || $unwrap->name->name !== 'unwrap') {
            return false;
        }

        $parent = $unwrap->var;

        return $parent instanceof Node\Expr\PropertyFetch
            && $parent->var instanceof Node\Expr\Variable
            && $parent->var->name === 'this'
            && $parent->name instanceof Node\Identifier
            && $parent->name->name === 'parent';
    }
}
