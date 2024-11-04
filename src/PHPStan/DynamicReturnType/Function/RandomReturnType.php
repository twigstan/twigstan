<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Function;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

final readonly class RandomReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'random';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        $argType = new NullType();

        if (isset($methodCall->args[1]) && $methodCall->args[1] instanceof Arg) {
            $argType = $scope->getType($methodCall->args[1]->value);
        }

        $max = new NullType();

        if (isset($methodCall->args[2]) && $methodCall->args[1] instanceof Arg) {
            $max = $scope->getType($methodCall->args[2]->value);
        }

        if ($argType->isNull()->yes()) {
            return $scope->getType(new FuncCall(
                new Name('mt_rand'),
                $max->isNull()->yes() ? [] : [
                    new Arg(new LNumber(0)),
                    new Arg(new TypeExpr($max)),
                ],
            ));
        }

        if ($argType->isInteger()->yes() || $argType->isFloat()->yes()) {
            $argType = $argType->toInteger();

            if ($max->isNull()->yes()) {
                if (count($argType->getConstantScalarValues()) === 1 && $argType->getConstantScalarValues()[0] < 0) {
                    $max = new ConstantIntegerType(0);
                    $min = $argType;
                } else {
                    $max = $argType;
                    $min = new ConstantIntegerType(0);
                }
            } else {
                $min = $argType;
            }

            return $scope->getType(new FuncCall(
                new Name('mt_rand'),
                [
                    new Arg(new TypeExpr($min)),
                    new Arg(new TypeExpr($max)),
                ],
            ));
        }

        if ($argType->isString()->yes()) {
            return new StringType();
        }

        return $argType->getIterableValueType();
    }
}
