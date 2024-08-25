<?php

declare(strict_types=1);

namespace TwigStan\Twig\Node;

use Twig\Node\Node;

final readonly class NodeMapper
{
    /**
     * @template TResult of mixed
     * @param Node $node
     * @param callable(Node): TResult $callback
     *
     * @return list<TResult>
     */
    public function map(Node $node, callable $callback): array
    {
        $result = [];
        foreach ($node as $item) {
            $result[] = $callback($item);
        }

        return $result;
    }
}
