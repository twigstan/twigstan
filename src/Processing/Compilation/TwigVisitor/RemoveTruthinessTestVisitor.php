<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\Expression\Test\TrueTest;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Twig wraps conditions (`if`, `not`, `and`, `or`, ternaries, ...) in a TrueTest, so that an empty Markup is falsy.
 *
 * Instead of printing: if (($tmp = $context["name"]) && $tmp instanceof \Twig\Markup ? (string) $tmp : $tmp)
 * We want: if ($context["name"])
 */
final readonly class RemoveTruthinessTestVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ( ! $node instanceof TrueTest) {
            return $node;
        }

        return $node->getNode('node');
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
