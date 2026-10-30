<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeVisitorAbstract;

/**
 * Symfony UX Twig components compile the index of their embedded template as a string,
 * which PHP coerces to the int that Twig\Template::load() expects.
 *
 * Find:    $this->load("layout.twig", 1, "123")
 * Replace: $this->load("layout.twig", 1, 123)
 */
final class IntegerTemplateIndexVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Expr\MethodCall) {
            return null;
        }

        if ( ! $node->var instanceof Expr\Variable || $node->var->name !== 'this') {
            return null;
        }

        if ( ! $node->name instanceof Node\Identifier || $node->name->name !== 'load') {
            return null;
        }

        $index = $node->getArgs()[2] ?? null;

        if ($index === null || ! $index->value instanceof Scalar\String_ || preg_match('/^\d+$/', $index->value->value) !== 1) {
            return null;
        }

        $index->value = new Scalar\Int_((int) $index->value->value);

        return $node;
    }
}
