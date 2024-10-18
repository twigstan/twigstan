<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class RemoveParentYieldVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?int
    {
        // Find: yield from $this->parent->yield($context, \array_merge($this->blocks, $blocks));
        // Remove whole expression node

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if ( ! $node->expr instanceof Node\Expr\YieldFrom) {
            return null;
        }

        if ( ! $node->expr->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $node->expr->expr->var instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        if ( ! $node->expr->expr->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->expr->expr->var->var->name !== 'this') {
            return null;
        }

        if ( ! $node->expr->expr->var->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->expr->expr->var->name->name !== 'parent') {
            return null;
        }

        if ( ! $node->expr->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->expr->expr->name->name !== 'yield') {
            return null;
        }

        return NodeVisitor::REMOVE_NODE;
    }
}
