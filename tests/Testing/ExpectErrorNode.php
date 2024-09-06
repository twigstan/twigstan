<?php

declare(strict_types=1);

namespace TwigStan\Testing;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

#[YieldReady]
final class ExpectErrorNode extends Node
{
    public function __construct(?string $file, int $line, string $error)
    {
        parent::__construct([], array_filter([
            'file' => $file,
            'line' => $line,
            'error' => $error,
        ]));
    }

    public function compile(Compiler $compiler): void {}
}
