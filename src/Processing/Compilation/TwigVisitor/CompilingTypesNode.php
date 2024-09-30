<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\TypesNode;

#[YieldReady]
final class CompilingTypesNode extends TypesNode
{
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        foreach ($this->getAttribute('mapping') as $name => ['type' => $type, 'optional' => $optional]) {
            $compiler->write('twigstan_type_hint($context, ')
                ->repr($name)
                ->raw(', ')
                ->repr($type)
                ->raw(', ')
                ->repr($optional)
                ->raw(");\n");
        }
    }
}
