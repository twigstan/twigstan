<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType\Filter;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;

#[Conditional(attribute: new AutowiredService(), if: 'not(%twigstan.phases.templateContextCollecting%)')]
final readonly class SprintfReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'sprintf';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        return $scope->getType(new FuncCall(new Name('sprintf'), $methodCall->args));
    }
}
