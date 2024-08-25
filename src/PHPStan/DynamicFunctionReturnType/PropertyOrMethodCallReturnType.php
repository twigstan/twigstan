<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicFunctionReturnType;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;

final readonly class PropertyOrMethodCallReturnType implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'twigstan_get_property_or_call_method';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        $objectType = $scope->getType($functionCall->args[0]->value);

        if ($objectType instanceof MixedType) {
            return new MixedType();
        }

        $propertyOrMethodType = $functionCall->args[1]->value instanceof Scalar\String_ ? new ConstantStringType($functionCall->args[1]->value->value) : $scope->getType($functionCall->args[1]->value);

        if (! $propertyOrMethodType instanceof ConstantStringType) {
            return new MixedType();
        }

        if ($objectType->isArray()->yes()) {
            return $objectType->getOffsetValueType($propertyOrMethodType);
        }

        $propertyOrMethod = $propertyOrMethodType->getValue();

        $nullable = false;
        if ($objectType->isNull()->maybe()) {
            $nullable = true;
            $objectType = $objectType->tryRemove(new NullType());
        }

        //if (is_int($propertyOrMethod)) {
        //    return new ErrorType(); // @todo prob array?
        //}

        if ($objectType->hasProperty($propertyOrMethod)->yes()) {
            $property = $objectType->getProperty($propertyOrMethod, $scope);
            if ($property->isPublic()) {
                //if ($nullable) {
                //    return new UnionType([$property->getReadableType(), new NullType()]);
                //}

                return $property->getReadableType();
            }
        }

        foreach (['', 'get', 'is', 'has'] as $prefix) {
            if (!$objectType->hasMethod($prefix . $propertyOrMethod)->yes()) {
                continue;
            }

            $method = $objectType->getMethod($prefix . $propertyOrMethod, $scope);
            $type = ParametersAcceptorSelector::selectSingle($method->getVariants())->getReturnType();

            return $type;
        }

        return new ErrorType();
    }
}
