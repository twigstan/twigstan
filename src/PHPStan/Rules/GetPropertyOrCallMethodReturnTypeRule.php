<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ErrorType;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
class GetPropertyOrCallMethodReturnTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Expr\FuncCall) {
            return [];
        }

        if (! $node->name instanceof Node\Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'twigstan_get_property_or_call_method') {
            return [];
        }

        $type = $scope->getType($node);

        if (! $type instanceof ErrorType) {
            return [];
        }

        $calledOnType = $scope->getType($node->args[0]->value);

        if ($calledOnType instanceof ErrorType) {
            return [];
        }

        $errors = [];

        $errors[] = RuleErrorBuilder::message(sprintf(
            'Neither the property "%1$s" nor one of the methods "%1$s()", "get%1$s()"/"is%1$s()"/"has%1$s()" or "__call()" exist and have public access in class "%2$s".',
            trim($scope->getType($node->args[1]->value)->describe(VerbosityLevel::value()), '\'"'),
            $scope->getType($node->args[0]->value)->describe(VerbosityLevel::value()),
        ))->identifier('twig.getPropertyOrCallMethodReturnType')->line($node->getLine())->build();

        return $errors;
    }
}
