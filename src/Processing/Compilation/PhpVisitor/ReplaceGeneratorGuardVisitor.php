<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Twig ends generators with `return; yield;` so that they are generators even without output.
 * PHPStan reports the `yield` as unreachable, and the early return breaks flattening.
 *
 * Find:    return; yield;
 * Replace: yield from [];
 */
final class ReplaceGeneratorGuardVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Stmt\ClassMethod && ! $node instanceof Expr\Closure) {
            return null;
        }

        $stmts = $node->stmts ?? [];
        $count = count($stmts);

        if ($count < 2) {
            return null;
        }

        $return = $stmts[$count - 2];
        $yield = $stmts[$count - 1];

        if ( ! $return instanceof Stmt\Return_ || $return->expr !== null) {
            return null;
        }

        if ( ! $yield instanceof Stmt\Expression || ! $yield->expr instanceof Expr\Yield_ || $yield->expr->value !== null) {
            return null;
        }

        array_splice($stmts, $count - 2, 2, [
            new Stmt\Expression(new Expr\YieldFrom(new Expr\Array_()), $return->getAttributes()),
        ]);

        $node->stmts = $stmts;

        return $node;
    }
}
