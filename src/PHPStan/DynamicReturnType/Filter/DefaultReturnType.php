<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Twig\Extension\CoreExtension;

final readonly class DefaultReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'default';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) < 1) {
            return null;
        }

        if ( ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        $argType = $scope->getType($methodCall->args[0]->value);
        $default = new ConstantStringType('');

        if (isset($methodCall->args[1])) {
            if ( ! $methodCall->args[1] instanceof Arg) {
                return null;
            }

            $default = $scope->getType($methodCall->args[1]->value);
        }

        if ($argType->isFalse()->yes()) {
            return $default;
        }

        if ($argType->isNull()->yes()) {
            return $default;
        }

        $constantStrings = $argType->getConstantStrings();

        if (count($constantStrings) === 1) {
            return $constantStrings[0]->getValue() !== '' ? $argType : $default;
        }

        if ($argType->getArraySize()->equals(new ConstantIntegerType(0))) {
            return $default;
        }

        return TypeCombinator::union(
            $argType,
            $default,
        );
    }
}
