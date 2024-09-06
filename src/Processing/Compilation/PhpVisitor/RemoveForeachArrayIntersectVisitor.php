<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

final class RemoveForeachArrayIntersectVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): array|null
    {
        // Find: $context = array_intersect_key($context, $_parent) + $_parent;
        // Replace with: extract($_parent); unset($_parent);

        if (!$node instanceof Node\Stmt\Expression) {
            return null;
        }

        if (! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        if (! $node->expr->var instanceof Variable) {
            return null;
        }

        if ($node->expr->var->name !== 'context') {
            return null;
        }

        if (! $node->expr->expr instanceof Node\Expr\BinaryOp\Plus) {
            return null;
        }

        $binaryOp = $node->expr->expr;

        if (! $binaryOp->left instanceof Node\Expr\FuncCall) {
            return null;
        }

        $funcCall = $binaryOp->left;

        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        if ($funcCall->name->toString() !== 'array_intersect_key') {
            return null;
        }

        return [
            // Add: extract($_parent);
            new Node\Stmt\Expression(
                new Node\Expr\FuncCall(
                    new Node\Name('extract'),
                    [
                        new Node\Arg(
                            new Variable('_parent'),
                        ),
                    ],
                ),
            ),

            // Add: unset($_parent);
            new Node\Stmt\Unset_(
                [new Node\Expr\Variable('_parent')],
            ),
        ];
    }
}
