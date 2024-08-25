<?php

declare(strict_types=1);

namespace TwigStan\Twig\Visitor;

use Twig\Environment;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use TwigStan\Twig\Node\RequirementsNode;

final readonly class RequirementsVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode || $node instanceof MacroNode) {
            $bodyNode = $node->getNode('body');

            if ($bodyNode->getNode('0') instanceof RequirementsNode) {
                $node->setAttribute('requirements', $bodyNode->getNode('0')->getAttribute('requirements'));

                return $node;
            }

            foreach ($bodyNode->getNode('0') as $childNode) {
                if (!$childNode instanceof RequirementsNode) {
                    continue;
                }

                $node->setAttribute('requirements', $childNode->getAttribute('requirements'));

                return $node;
            }
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
