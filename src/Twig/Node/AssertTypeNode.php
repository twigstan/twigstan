<?php

declare(strict_types=1);

namespace TwigStan\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

#[YieldReady]
final class AssertTypeNode extends Node
{
    public function __construct(string $name, string $expectedType, int $lineno = 0)
    {
        parent::__construct([], [
            'name' => $name,
            'expectedType' => $expectedType,
        ], $lineno);
    }

    public function compile(Compiler $compiler): void {}
}
