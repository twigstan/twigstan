<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Types app.request as not null in templates rendered by controllers, see AppUserContext.
 * The stub of AppVariable cannot name the Request class, because PHPStan validates stubs with only the classes of stubs.
 */
#[AutowiredService]
final readonly class AppRequestReturnType implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return AppVariable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getRequest';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        if ( ! $scope->getType($methodCall->var)->getTemplateType(AppVariable::class, 'THasRequest')->isTrue()->yes()) {
            return null;
        }

        return new ObjectType(Request::class);
    }
}
