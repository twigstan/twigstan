<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Function;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Mod;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class CycleReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'cycle';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) !== 2) {
            return null;
        }

        if ( ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[1] instanceof Arg) {
            return null;
        }

        $values = $scope->getType($methodCall->args[0]->value);
        $position = $scope->getType($methodCall->args[1]->value);

        return $scope->getType(new ArrayDimFetch(
            new TypeExpr($values),
            new Mod(
                new TypeExpr($position),
                new FuncCall(
                    new Name('count'),
                    [new Arg(new TypeExpr($values))],
                ),
            ),
        ));
    }
}
