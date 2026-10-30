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

        // slice($charset, $item, $start, $length = null, $preserveKeys = false)
        if ($argType->isString()->yes()) {
            return $scope->getType(new FuncCall(new Name('mb_substr'), array_slice($methodCall->args, 1, 3)));
        }

        if ($argType->isIterable()->yes()) {
            return $scope->getType(new FuncCall(new Name('array_slice'), array_slice($methodCall->args, 1)));
        }

        return null;
    }
}
