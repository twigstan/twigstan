<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class RemoveAssignMacrosVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?int
    {
        // Find: $macros = $this->macros;
        // Remove it.

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if ( ! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        if ( ! $node->expr->var instanceof Variable) {
            return null;
        }

        if ($node->expr->var->name !== 'macros') {
            return null;
        }

        if ( ! $node->expr->expr instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        if ( ! $node->expr->expr->var instanceof Variable) {
            return null;
        }

        if ($node->expr->expr->var->name !== 'this') {
            return null;
        }
        if ( ! $node->expr->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->expr->expr->name->name !== 'macros') {
            return null;
        }

        return NodeTraverser::REMOVE_NODE;
    }
}
