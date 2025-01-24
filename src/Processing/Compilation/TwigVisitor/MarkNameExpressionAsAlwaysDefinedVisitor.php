<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class MarkNameExpressionAsAlwaysDefinedVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node::class !== ContextVariable::class) {
            return $node;
        }

        if ($node->getAttribute('is_defined_test') === true) {
            return $node;
        }

        // Instead of printing: ($context["name"] ?? null)
        // We want: $context["name"]
        $node->setAttribute('always_defined', true);

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
