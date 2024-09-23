<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class ReplaceTypesNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof TypesNode) {
            return $node;
        }

        return new CompilingTypesNode($node->getAttribute('mapping'), $node->getTemplateLine());
    }

    public function getPriority(): int
    {
        return 0;
    }
}
