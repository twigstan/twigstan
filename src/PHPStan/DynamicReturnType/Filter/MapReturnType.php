<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class MapReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'map';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) !== 3) {
            return null;
        }

        if ( ! $methodCall->args[1] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[2] instanceof Arg) {
            return null;
        }

        $input = $scope->getType($methodCall->args[1]->value);
        $reducer = $scope->getType($methodCall->args[2]->value);

        if ( ! $reducer->isCallable()->yes()) {
            return null;
        }

        return new ArrayType(
            $input->getIterableKeyType(),
            $reducer->getCallableParametersAcceptors($scope)[0]->getReturnType(),
        );
    }
}
