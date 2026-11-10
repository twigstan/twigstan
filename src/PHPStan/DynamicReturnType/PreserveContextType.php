<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

#[Conditional(attribute: new AutowiredService(), if: 'not(%twigstan.phases.templateContextCollecting%)')]
final readonly class PreserveContextType implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'twigstan_preserve_context';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $funcCall, Scope $scope): ?Type
    {
        if ( ! isset($funcCall->getArgs()[0])) {
            return null;
        }

        return TypeTraverser::map($scope->getType($funcCall->getArgs()[0]->value), static function (Type $type, callable $traverse): Type {
            // @phpstan-ignore phpstanApi.instanceofType (The preservation API belongs to the concrete shape.)
            if ($type instanceof ConstantArrayType) {
                return $type->withPreservedArrayShape();
            }

            return $traverse($type);
        });
    }
}
