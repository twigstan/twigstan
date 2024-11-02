<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
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

        $method = [new ConstantStringType('common')];

        if (isset($methodCall->args[2])) {
            if ( ! $methodCall->args[2] instanceof Arg) {
                return null;
            }

            $method = $scope->getType($methodCall->args[2]->value)->getConstantStrings();
        }

        if (count($method) !== 1) {
            return null;
        }

        return match ($method[0]->getValue()) {
            'common' => $scope->getType(new FuncCall(new Name('round'), array_filter([
                $methodCall->args[0],
                $methodCall->args[1] ?? null,
            ]))),
            'ceil' => $scope->getType(new FuncCall(new Name('ceil'), [$methodCall->args[0]])),
            'floor' => $scope->getType(new FuncCall(new Name('floor'), [$methodCall->args[0]])),
            default => null,
        };
    }
}
