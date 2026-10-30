<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use Closure;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ClosureTypeFactory;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\AbstractTwigCallable;
use Twig\Environment;

/**
 * Types the closure of $this->env->getFunction('name')->getCallable() by the closure that the Twig environment has.
 */
final readonly class GetCallableReturnType implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private Environment $twig,
        private ClosureTypeFactory $closureTypeFactory,
    ) {}

    public function getClass(): string
    {
        return AbstractTwigCallable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getCallable';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        if ( ! $methodCall->var instanceof MethodCall) {
            return null;
        }

        $callable = GetTwigCallableReturnType::getTwigCallable($this->twig, $methodCall->var, $scope)?->getCallable();

        if ( ! $callable instanceof Closure) {
            return null;
        }

        return $this->closureTypeFactory->fromClosureObject($callable);
    }
}
