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
            $hint = sprintf(
                "$%s = twigstan_type_hint(%s);\n",
                $name,
                var_export($type, true),
            );

            if ($optional) {
                $compiler->write("if (rand(0, 1) === 1) {\n")
                    ->indent()
                    ->write($hint)
                    ->outdent()
                    ->write("}\n");

                continue;
            }

            $compiler->write($hint);
        }
    }
}
