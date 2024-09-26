<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class ReplaceIncludeNodeNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ( ! $node instanceof IncludeNode) {
            return $node;
        }

        return SimpleIncludeNode::create($node);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
