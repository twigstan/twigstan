<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Twig\Environment;

final class TwigGlobalsToPhpDoc
{
    private ArrayShapeNode $globals;

    public function __construct(private Environment $twig) {}

    public function getGlobals(): ArrayShapeNode
    {
        return $this->globals ??= $this->generateGlobals();
    }

    private function generateGlobals(): ArrayShapeNode
    {
        $globals = [];
        foreach ($this->twig->getGlobals() as $name => $value) {
            $globals[] = new ArrayShapeItemNode(
                new ConstExprStringNode($name),
                false,
                match (true) {
                    is_object($value) => new IdentifierTypeNode($value::class),
                    is_float($value) => new IdentifierTypeNode('float'),
                    is_int($value) => new IdentifierTypeNode('int'),
                    is_string($value) => new IdentifierTypeNode('string'),
                    default => new IdentifierTypeNode('mixed'),
                },
            );
        }

        return new ArrayShapeNode($globals);
    }
}
