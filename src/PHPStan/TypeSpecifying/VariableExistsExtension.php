<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeSpecifying;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Node\IssetExpr;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\FunctionTypeSpecifyingExtension;

final class VariableExistsExtension implements FunctionTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function isFunctionSupported(
        FunctionReflection $functionReflection,
        FuncCall $node,
        TypeSpecifierContext $context,
    ): bool {
        return $functionReflection->getName() === 'twigstan_variable_exists' && ! $context->null();
    }

    public function specifyTypes(
        FunctionReflection $functionReflection,
        FuncCall $node,
        Scope $scope,
        TypeSpecifierContext $context,
    ): SpecifiedTypes {
        if ( ! isset($node->getArgs()[0])) {
            return new SpecifiedTypes();
        }

        if ($context->null()) {
            throw new ShouldNotHappenException();
        }

        if ( ! $node->getArgs()[0]->value instanceof String_) {
            return new SpecifiedTypes();
        }

        $variableName = $node->getArgs()[0]->value->value;
        $variable = new Variable($variableName);
        $variableType = $scope->getType($variable);

        if ($context->false()) {
            return $this->typeSpecifier->create(
                new IssetExpr($variable),
                $variableType,
                $context,
            );
        }

        return $this->typeSpecifier->create(
            $variable,
            $variableType,
            $context,
        );
    }
}
