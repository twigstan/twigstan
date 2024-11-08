<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Environment;
use Twig\Extension\CoreExtension;

final readonly class ReduceReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'reduce';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        $args = $methodCall->args;

        if (Environment::MAJOR_VERSION === 3) {
            array_shift($args);
        }

        if ( ! $args[1] instanceof Arg) {
            return null;
        }

        $reducer = $scope->getType($args[1]->value);

        if ( ! $reducer->isCallable()->yes()) {
            return null;
        }

        return $reducer->getCallableParametersAcceptors($scope)[0]->getReturnType();
    }
}
