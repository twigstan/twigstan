<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Twig caches the parent template in Twig\Template::$parent, which is untyped.
 *
 * Find:    return $this->parent ??= $this->load("layout.twig", 1);
 * Replace: return $this->load("layout.twig", 1);
 */
final class RemoveParentCachingVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Expr\AssignOp\Coalesce) {
            return null;
        }

        if ( ! $node->var instanceof Expr\PropertyFetch) {
            return null;
        }

        if ( ! $node->var->var instanceof Expr\Variable || $node->var->var->name !== 'this') {
            return null;
        }

        if ( ! $node->var->name instanceof Node\Identifier || $node->var->name->name !== 'parent') {
            return null;
        }

        return $node->expr;
    }
}
