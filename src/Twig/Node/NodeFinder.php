<?php

declare(strict_types=1);

namespace TwigStan\Twig\Node;

use Twig\Node\Node;

final readonly class NodeFinder
{
    /**
     * @param class-string<Node> $class
     * @return list<Node>
     */
    public function findInstanceOf(Node $node, string $class): array
    {
        $found = [];
        foreach ($node as $child) {
            if (is_a($child, $class, true)) {
                $found[] = $child;

                continue;
            }

            $found = [...$found, ...$this->findInstanceOf($child, $class)];
        }

        return $found;
    }

    /**
     * @param class-string<Node> $class
     */
    public function findFirstInstanceOf(Node $node, string $class): ?Node
    {
        foreach ($node as $child) {
            if (is_a($child, $class, true)) {
                return $child;
            }

            $found = $this->findFirstInstanceOf($child, $class);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
