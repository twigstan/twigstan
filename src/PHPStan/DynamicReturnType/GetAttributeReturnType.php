<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class GetAttributeReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getAttribute';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) < 4) {
            return null;
        }

        if ( ! $methodCall->args[2] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[3] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[5] instanceof Arg) {
            return null;
        }

        $objectType = $scope->getType($methodCall->args[2]->value);

        if ($objectType instanceof MixedType) {
            return new MixedType();
        }

        $propertyOrMethodType = $scope->getType($methodCall->args[3]->value);

        if ($propertyOrMethodType instanceof ConstantIntegerType) {
            $propertyOrMethod = $propertyOrMethodType->getValue();
        } else {
            $constantStringTypes = $propertyOrMethodType->getConstantStrings();
            if ($constantStringTypes === []) {
                return new MixedType();
            }

            $propertyOrMethod = $constantStringTypes[0]->getValue();
        }

        if ( ! $methodCall->args[5]->value instanceof String_) {
            return new MixedType();
        }

        $callType = $methodCall->args[5]->value->value;

        if (in_array($callType, [\Twig\Template::ANY_CALL, \Twig\Template::ARRAY_CALL], true)) {
            if ($objectType->isArray()->yes()) {
                return $objectType->getOffsetValueType($propertyOrMethodType);
            }
        }

        $nullable = false;
        if ($objectType->isNull()->maybe()) {
            $nullable = true;
            $objectType = $objectType->tryRemove(new NullType());
        }

        if ($objectType === null) {
            return new ErrorType();
        }

        // if (is_int($propertyOrMethod)) {
        //    return new ErrorType(); // @todo prob array?
        // }

        if (in_array($callType, [\Twig\Template::ANY_CALL], true)) {
            if ($objectType->hasProperty((string) $propertyOrMethod)->yes()) {
                $property = $objectType->getProperty((string) $propertyOrMethod, $scope);
                if ($property->isPublic()) {
                    // if ($nullable) {
                    //    return new UnionType([$property->getReadableType(), new NullType()]);
                    // }

                    return $property->getReadableType();
                }
            }
        }

        if (in_array($callType, [\Twig\Template::ANY_CALL, \Twig\Template::METHOD_CALL], true)) {
            foreach (['', 'get', 'is', 'has'] as $prefix) {
                if ( ! $objectType->hasMethod($prefix . $propertyOrMethod)->yes()) {
                    continue;
                }

                $method = $objectType->getMethod($prefix . $propertyOrMethod, $scope);
                $type = ParametersAcceptorSelector::selectSingle($method->getVariants())->getReturnType();

                return $type;
            }
        }

        return new ErrorType();
    }
}
