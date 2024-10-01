<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

final readonly class GetDefinedVarsReturnType implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'get_defined_vars';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): Type {
        if ($scope->canAnyVariableExist()) {
            return new ArrayType(
                new MixedType(),
                new MixedType(),
            );
        }

        // @phpstan-ignore phpstanApi.class
        if ( ! $scope instanceof MutatingScope) {
            return new ArrayType(
                new MixedType(),
                new MixedType(),
            );
        }

        $variables = array_values(array_filter(
            $scope->getDefinedVariables(),
            fn($variable) => $variable !== 'this',
        ));

        $builder = ConstantArrayTypeBuilder::createEmpty();
        foreach ($variables as $variable) {
            $builder->setOffsetValueType(new ConstantStringType($variable), $scope->getVariableType($variable));
        }

        // @see https://github.com/phpstan/phpstan/issues/11772
        // @phpstan-ignore phpstanApi.method
        foreach ($scope->debug() as $key => $value) {
            if ( ! str_starts_with($key, '$')) {
                continue;
            }

            if ( ! str_ends_with($key, ' (Maybe)')) {
                continue;
            }

            $variable = substr($key, 1, -8);
            $builder->setOffsetValueType(new ConstantStringType($variable), $scope->getVariableType($variable), true);
        }

        return $builder->getArray();
    }
}
