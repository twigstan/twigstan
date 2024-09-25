<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class RemoveParentAssignVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?int
    {
        // Find: expression stmt $_parent = $context['_parent'];
        // Remove it

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if ( ! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        if ( ! $node->expr->var instanceof Variable) {
            return null;
        }

        if ($node->expr->var->name !== '_parent') {
            return null;
        }

        if ( ! $node->expr->expr instanceof ArrayDimFetch) {
            return null;
        }

        if ( ! $node->expr->expr->var instanceof Variable) {
            return null;
        }

        if ($node->expr->expr->var->name !== 'context') {
            return null;
        }

        if ( ! $node->expr->expr->dim instanceof String_) {
            return null;
        }

        if ($node->expr->expr->dim->value !== '_parent') {
            return null;
        }

        return NodeTraverser::REMOVE_NODE;
    }
}
