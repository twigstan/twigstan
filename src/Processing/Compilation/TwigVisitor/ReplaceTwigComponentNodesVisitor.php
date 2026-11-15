<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Symfony\UX\TwigComponent\Twig\ComponentNode;
use Symfony\UX\TwigComponent\Twig\PropsNode;
use Twig\Environment;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class ReplaceTwigComponentNodesVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ComponentNode) {
            return SimpleComponentNode::create($node);
        }

        if ($node instanceof PropsNode) {
            return SimplePropsNode::create($node);
        }

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
