<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Twig\Template;

final readonly class GetTemplateForMacroReturnType implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Template::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getTemplateForMacro';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $template = $scope->getType($methodCall->var);

        if (count($methodCall->args) !== 4) {
            return null;
        }

        if ( ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        $methods = $scope->getType($methodCall->args[0]->value)->getConstantStrings();

        if (count($methods) !== 1) {
            return null;
        }

        $method = $methods[0]->getValue();

        if ($template->hasMethod($method)->yes()) {
            return $template;
        }

        // TODO: We need to go to the parents of the template to find the method

        return null;
    }
}
