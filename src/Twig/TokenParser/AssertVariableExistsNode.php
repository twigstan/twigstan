<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

#[YieldReady]
final class AssertVariableExistsNode extends Node
{
    public function __construct(AbstractExpression $name, string $certainty, int $lineno = 0)
    {
        parent::__construct([
            'name' => $name,
        ], [
            'certainty' => $certainty,
        ], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("\PHPStan\Testing\assertVariableCertainty(\n")
            ->indent()
            ->write(sprintf(
                '\PHPStan\TrinaryLogic::create%s()',
                ucfirst($this->getAttribute('certainty')),
            ))
            ->raw(",\n")
            ->write("// @phpstan-ignore offsetAccess.notFound\n")
            ->subcompile($this->getNode('name'), false)
            ->write("\n")
            ->outdent()
            ->write(");\n");
    }
}
