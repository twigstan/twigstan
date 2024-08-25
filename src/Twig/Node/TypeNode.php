<?php

declare(strict_types=1);

namespace TwigStan\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

#[YieldReady]
final class TypeNode extends Node
{
    /**
     * @param array<string, string> $types
     */
    public function __construct(array $types, int $lineno = 0)
    {
        parent::__construct([], [
            'types' => $types,
        ], $lineno);
    }

    public function compile(Compiler $compiler): void {}
}
