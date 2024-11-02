<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class LastReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'last';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) !== 2) {
            return null;
        }

        if ( ! $methodCall->args[1] instanceof Arg) {
            return null;
        }

        $argType = $scope->getType($methodCall->args[1]->value);

        if ($argType->isString()->yes()) {
            return $scope->getType(new FuncCall(new Name('substr'), [
                $methodCall->args[1],
                new Arg(new LNumber(-1)),
            ]));
        }

        return $argType->getLastIterableValueType();
    }
}
