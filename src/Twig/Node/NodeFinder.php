<?php

declare(strict_types=1);

namespace TwigStan\Twig\Node;

use Twig\Node\Node;

final readonly class NodeFinder
{
    /**
     * @param class-string<Node> $class
     */
    public function findInstanceOf(Node $node, string $class): ?Node
    {
        foreach ($node as $child) {
            if (is_a($child, $class, true)) {
                return $child;
            }

            $found = $this->findInstanceOf($child, $class);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
