<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Extension\CoreExtension;
use TwigStan\PHPStan\GetAttributeCheck;

#[Conditional(attribute: new AutowiredService(), if: 'not(%twigstan.phases.templateContextCollecting%)')]
final readonly class GetAttributeReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private GetAttributeCheck $attributeCheck) {}

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
        $result = $this->attributeCheck->check($methodCall, $scope);

        if ($result === null) {
            return null;
        }

        return $result[0];
    }
}
