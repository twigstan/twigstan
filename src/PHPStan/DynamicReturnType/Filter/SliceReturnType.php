<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class SliceReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'slice';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) < 2) {
            return null;
        }

        if ( ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[1] instanceof Arg) {
            return null;
        }

        $argType = $scope->getType($methodCall->args[1]->value);

        if ($argType->isString()->yes()) {
            return $scope->getType(new FuncCall(
                new Name('mb_substr'),
                [
                    $methodCall->args[1],
                    $methodCall->args[2],
                    $methodCall->args[3],
                ],
            ));
        }

        return $scope->getType(new FuncCall(new Name('array_slice'), array_slice($methodCall->args, 1)));
    }
}
