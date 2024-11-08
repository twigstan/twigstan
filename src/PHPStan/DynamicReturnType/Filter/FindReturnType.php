<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Twig\Environment;
use Twig\Extension\CoreExtension;

final readonly class FindReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'find';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        $args = $methodCall->args;

        if (Environment::MAJOR_VERSION === 3) {
            array_shift($args);
        }

        if ($args === []) {
            return null;
        }

        if ( ! $args[0] instanceof Arg) {
            return null;
        }

        // TODO: When PHPStan supports array_find, pass that to getType.

        $input = $scope->getType($args[0]->value);

        return TypeCombinator::addNull($input->getIterableValueType());
    }
}
