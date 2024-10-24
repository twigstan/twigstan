<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class MarkNameExpressionAsAlwaysDefinedVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        // TODO: twig/twig:v3.15.0 Remove NameExpression check and bump minimum required Twig version to 3.15
        // @phpstan-ignore class.notFound
        if ($node::class !== (Environment::VERSION_ID <= 31400 ? NameExpression::class : ContextVariable::class)) {
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
