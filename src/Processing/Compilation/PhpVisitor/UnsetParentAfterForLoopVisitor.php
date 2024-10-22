<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class UnsetParentAfterForLoopVisitor extends NodeVisitorAbstract
{
    /**
     * @return null|array<Node\Stmt>
     */
    public function leaveNode(Node $node): ?array
    {
        // Find: $_parent = $context['_parent'];
        // Replace: $_parent = $context['_parent']; unset($context['_parent']);

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if ( ! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        if ( ! $node->expr->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ( ! is_string($node->expr->var->name)) {
            return null;
        }

        if ($node->expr->var->name !== '_parent') {
            return null;
        }

        if ( ! $node->expr->expr instanceof Node\Expr\ArrayDimFetch) {
            return null;
        }

        if ( ! $node->expr->expr->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->expr->expr->var->name !== 'context') {
            return null;
        }

        if ( ! $node->expr->expr->dim instanceof Node\Scalar\String_) {
            return null;
        }

        if ($node->expr->expr->dim->value !== '_parent') {
            return null;
        }

        return [
            $node,
            new Node\Stmt\Unset_([
                new Node\Expr\ArrayDimFetch(
                    new Node\Expr\Variable('context'),
                    new Node\Scalar\String_('_parent'),
                ),
            ]),
        ];
    }
}
