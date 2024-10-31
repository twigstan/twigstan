<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Twig\Environment;
use Twig\Template;

final readonly class LoadTemplateReturnType implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function getClass(): string
    {
        return Template::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'loadTemplate';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        if (count($methodCall->args) !== 3) {
            return null;
        }

        if ( ! $methodCall->args[0] instanceof Arg) {
            return null;
        }

        $templates = $scope->getType($methodCall->args[0]->value)->getConstantStrings();

        if (count($templates) !== 1) {
            return null;
        }

        $template = $templates[0]->getValue();

        return new ObjectType($this->twig->getTemplateClass($template));
    }
}
