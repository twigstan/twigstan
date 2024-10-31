<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

final class AddExtraLineNumberCommentVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Stmt\ClassMethod) {
            return null;
        }

        $target = null;
        foreach (array_reverse($node->stmts ?? []) as $stmt) {
            if ($target === null) {
                // Find:         yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));

                if ( ! $stmt instanceof Stmt\Expression) {
                    continue;
                }

                if ( ! $stmt->expr instanceof Node\Expr\YieldFrom) {
                    continue;
                }

                if ( ! $stmt->expr->expr instanceof Node\Expr\MethodCall) {
                    continue;
                }

                if ( ! $stmt->expr->expr->name instanceof Node\Identifier) {
                    continue;
                }

                if ($stmt->expr->expr->name->name !== 'yield') {
                    continue;
                }

                if ( ! $stmt->expr->expr->var instanceof Node\Expr\MethodCall) {
                    continue;
                }

                if ( ! $stmt->expr->expr->var->name instanceof Node\Identifier) {
                    continue;
                }

                if ($stmt->expr->expr->var->name->name !== 'unwrap') {
                    continue;
                }

                $target = $stmt;

                continue;
            }

            if ($stmt->getComments() === []) {
                continue;
            }

            $target->setAttribute('comments', $stmt->getComments());

            return $node;
        }

        return null;
    }
}
