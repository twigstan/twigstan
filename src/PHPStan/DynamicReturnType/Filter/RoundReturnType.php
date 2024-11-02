<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class RoundReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'round';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if ($methodCall->args === []) {
            return null;
        }

        $method = $scope->getType($methodCall->args[2]?->value ?? new String_('common'))->getConstantStrings();

        if (count($method) !== 1) {
            return null;
        }

        return $scope->getType(match ($method[0]->getValue()) {
            'common' => new FuncCall(new Name('round'), array_filter([
                $methodCall->args[0],
                $methodCall->args[1] ?? null,
            ])),
            'ceil' => new FuncCall(new Name('ceil'), [$methodCall->args[0]]),
            'floor' => new FuncCall(new Name('floor'), [$methodCall->args[0]]),
            default => null,
        });
    }
}
