<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

#[YieldReady]
final class AssertTypeNode extends Node
{
    public function __construct(AbstractExpression $name, string $expectedType, int $lineno = 0)
    {
        parent::__construct([
            'name' => $name,
        ], [
            'expectedType' => $expectedType,
        ], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("// @phpstan-ignore offsetAccess.notFound\n")
            ->write('\PHPStan\Testing\assertType(')
            ->string($this->getAttribute('expectedType'))
            ->raw(', ')
            ->subcompile($this->getNode('name'))
            ->raw(");\n");
    }
}
