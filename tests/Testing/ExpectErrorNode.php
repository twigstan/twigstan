<?php

declare(strict_types=1);

namespace TwigStan\Testing;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;

#[YieldReady]
final class ExpectErrorNode extends Node
{
    public function __construct(ConstantExpression $line, ConstantExpression $error, int $lineno = 0)
    {
        parent::__construct([], [
            'line' => $line->getAttribute('value'),
            'error' => $error->getAttribute('value'),
        ], $lineno);
    }

    public function compile(Compiler $compiler): void {}
}
