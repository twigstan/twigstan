<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Printer\Printer;
use Twig\Environment;

final class TwigGlobalsToPhpDoc
{
    private string $globals;

    public function __construct(private Environment $twig) {}

    public function getGlobals(): string
    {
        $this->globals ??= $this->generateGlobals();

        return $this->globals;
    }

    private function generateGlobals(): string
    {
        $globals = [];
        foreach ($this->twig->getGlobals() as $name => $value) {

            $globals[] = new ArrayShapeItemNode(
                new ConstExprStringNode($name),
                false,
                match(true) {
                    is_object($value) => new IdentifierTypeNode($value::class),
                    is_float($value) => new IdentifierTypeNode('float'),
                    is_int($value) => new IdentifierTypeNode('int'),
                    is_string($value) => new IdentifierTypeNode('string'),
                    default => new IdentifierTypeNode('mixed'),
                },
            );
        }

        $node = new ArrayShapeNode($globals);

        return (new Printer())->print($node);
    }

}
