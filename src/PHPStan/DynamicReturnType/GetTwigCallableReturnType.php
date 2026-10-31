<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Twig compiles functions, filters and tests that are closures to $this->env->getFunction('name')->getCallable()(...).
 * They exist when the template compiles, so these methods do not return null for them.
 */
final readonly class GetTwigCallableReturnType implements DynamicMethodReturnTypeExtension
{
    private const array METHODS = [
        'getFunction' => TwigFunction::class,
        'getFilter' => TwigFilter::class,
        'getTest' => TwigTest::class,
    ];

    public function __construct(
        private Environment $twig,
    ) {}

    public function getClass(): string
    {
        return Environment::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return isset(self::METHODS[$methodReflection->getName()]);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $twigCallable = self::getTwigCallable($this->twig, $methodCall, $scope);

        if ($twigCallable === null) {
            return null;
        }

        return new ObjectType($twigCallable::class);
    }

    /**
     * The function, filter or test that $methodCall, such as $this->env->getFunction('name'), returns.
     */
    public static function getTwigCallable(Environment $twig, MethodCall $methodCall, Scope $scope): null | TwigFilter | TwigFunction | TwigTest
    {
        if ( ! $methodCall->name instanceof Identifier || ! isset(self::METHODS[$methodCall->name->name])) {
            return null;
        }

        if ( ! (new ObjectType(Environment::class))->isSuperTypeOf($scope->getType($methodCall->var))->yes()) {
            return null;
        }

        $args = $methodCall->getArgs();

        if (count($args) !== 1) {
            return null;
        }

        $names = $scope->getType($args[0]->value)->getConstantStrings();

        if (count($names) !== 1) {
            return null;
        }

        return match ($methodCall->name->name) {
            'getFunction' => $twig->getFunction($names[0]->getValue()),
            'getFilter' => $twig->getFilter($names[0]->getValue()),
            default => $twig->getTest($names[0]->getValue()),
        };
    }
}
