<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Twig casts printed expressions to string. PHPStan reports most of these casts as useless.
 *
 * Find:    yield (string) $context["name"];
 * Replace: yield $context["name"];
 */
final class RemoveStringCastFromYieldVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Expr\Yield_) {
            return null;
        }

        if ( ! $node->value instanceof Expr\Cast\String_) {
            return null;
        }

        $node->value = $node->value->expr;

        return $node;
    }
}
