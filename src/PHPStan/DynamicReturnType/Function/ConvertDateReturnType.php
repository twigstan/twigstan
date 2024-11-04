<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Function;

use DateTime;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class ConvertDateReturnType implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'convertDate';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        if ($methodCall->args === []) {
            return null;
        }

        if ( ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        $argType = $scope->getType($methodCall->args[0]->value);

        if ($argType->isString()->yes()) {
            return new ObjectType(DateTime::class);
        }

        return $argType;
    }
}
