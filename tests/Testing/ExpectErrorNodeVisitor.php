<?php

declare(strict_types=1);

namespace TwigStan\Testing;

use Twig\Environment;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final class ExpectErrorNodeVisitor implements NodeVisitorInterface
{
    /**
     * @var list<string>
     */
    public array $expectedErrors = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        if (! $node instanceof ExpectErrorNode) {
            return $node;
        }

        $this->expectedErrors[] = sprintf(
            '%02d: %s',
            $node->getAttribute('line'),
            $node->getAttribute('error'),
        );

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if (! $node instanceof ExpectErrorNode) {
            return $node;
        }

        return null;
    }

    public function getPriority(): int
    {
        return 0;

    }

}
