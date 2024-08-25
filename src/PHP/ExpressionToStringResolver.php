<?php

declare(strict_types=1);

namespace TwigStan\PHP;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\VerbosityLevel;

final readonly class ExpressionToStringResolver
{
    public function resolve(?Node\Expr $value, Scope $scope): ExpressionToStringResult
    {
        if ($value === null) {
            return ExpressionToStringResult::create();
        }

        if ($value instanceof Node\Scalar\String_) {
            return ExpressionToStringResult::value($value->value);
        }

        if ($value instanceof Node\Expr\ArrayDimFetch) {
            $type = $scope->getType($value);

            if ($type->isConstantScalarValue()->yes()) {
                return ExpressionToStringResult::value(...$type->getConstantScalarValues());
            }

            $error = RuleErrorBuilder::message(sprintf(
                'Unable to determine value from variable "%s" of type "%s".',
                $value->dim?->value ?? '*ERROR*',
                $type->describe(VerbosityLevel::precise()),
            ))->line($value->getLine())->build();

            return ExpressionToStringResult::error($error);
        }

        if ($value instanceof Node\Expr\Ternary) {
            return ExpressionToStringResult::create(
                $this->resolve($value->if, $scope),
                $this->resolve($value->else, $scope),
            );
        }

        if ($value instanceof Node\Expr\BinaryOp\Concat) {
            if ($value->left instanceof Node\Scalar\String_) {
                $right = $this->resolve($value->right, $scope);

                return $right->map(fn(string $template) => $value->left->value . $template);
            }

            if ($value->right instanceof Node\Scalar\String_) {
                $left = $this->resolve($value->left, $scope);

                return $left->map(fn(string $template) => $template . $value->right->value);
            }
        }

        if ($value instanceof Node\Expr\Array_) {
            $result = ExpressionToStringResult::create();

            foreach ($value->items as $item) {
                $result = $result->and($this->resolve($item->value, $scope));
            }

            return $result;
        }

        if ($value instanceof Node\Expr\Variable) {
            $type = $scope->getType($value);

            $result = ExpressionToStringResult::create();

            foreach ($type->getConstantScalarValues() as $item) {
                $result = $result->and(ExpressionToStringResult::value($item));
            }

            return $result;
        }

        throw new ShouldNotHappenException(sprintf('Unable to resolve Twig expression "%s" to string.', get_class($value)));
    }
}
