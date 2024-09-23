<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
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

        $variables = array_values(array_filter(
            $scope->getDefinedVariables(),
            fn($variable) => $variable !== 'this',
        ));

        $keys = array_map(
            fn($variable) => new ConstantStringType($variable),
            $variables,
        );

        $values = array_map(
            fn($variable) => $scope->getVariableType($variable),
            $variables,
        );

        return new ConstantArrayType($keys, $values);
    }
}
