<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeSpecifier;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\NullType;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use PHPStan\Type\TypeCombinator;
use Twig\Extension\CoreExtension;

/**
 * When `x is empty` is false, x is not null, false, '' or an empty array.
 * When it is true, x can still be an empty Countable or Stringable, so it is not narrowed.
 */
final class EmptyTestTypeSpecifyingExtension implements StaticMethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $staticMethodReflection, StaticCall $node, TypeSpecifierContext $context): bool
    {
        return $staticMethodReflection->getName() === 'testEmpty' && $context->false() && count($node->getArgs()) === 1;
    }

    public function specifyTypes(MethodReflection $staticMethodReflection, StaticCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        return $this->typeSpecifier->create(
            $node->getArgs()[0]->value,
            TypeCombinator::union(new NullType(), new ConstantBooleanType(false), new ConstantStringType(''), new ConstantArrayType([], [])),
            TypeSpecifierContext::createFalse(),
            $scope,
        );
    }
}
