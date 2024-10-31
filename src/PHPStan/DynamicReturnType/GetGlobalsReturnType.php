<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Twig\Environment;

final readonly class GetGlobalsReturnType implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function getClass(): string
    {
        return Environment::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getGlobals';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type {
        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($this->twig->getGlobals() as $key => $value) {
            $builder->setOffsetValueType(new ConstantStringType($key), $this->getType($value));
        }

        return $builder->getArray();
    }

    private function getType(mixed $value): Type
    {
        if ($value === null) {
            return new NullType();
        }

        if (is_int($value)) {
            return new IntegerType();
        }

        if (is_float($value)) {
            return new FloatType();
        }

        if (is_bool($value)) {
            return new BooleanType();
        }

        if (is_string($value)) {
            return new StringType();
        }

        if (is_object($value)) {
            return new ObjectType($value::class);
        }

        if (is_array($value)) {
            $arrayBuilder = ConstantArrayTypeBuilder::createEmpty();

            if (count($value) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
                $arrayBuilder->degradeToGeneralArray(true);
            }

            foreach ($value as $k => $v) {
                $arrayBuilder->setOffsetValueType(
                    $this->getType($k),
                    $this->getType($v),
                );
            }

            return $arrayBuilder->getArray();
        }

        return new MixedType();
    }
}
