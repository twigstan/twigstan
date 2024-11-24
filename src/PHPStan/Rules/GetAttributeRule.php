<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Twig\Extension\CoreExtension;
use TwigStan\PHPStan\Visitor\AssertTypeVisitor;

/**
 * @implements Rule<Node\Expr\StaticCall>
 */
final readonly class GetAttributeRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ( ! $node->name instanceof Node\Identifier) {
            return [];
        }

        if ($node->name->name !== 'getAttribute') {
            return [];
        }

        if ( ! $node->class instanceof Node\Name\FullyQualified) {
            return [];
        }

        if ($node->class->toString() !== CoreExtension::class) {
            return [];
        }

        if ($node->getAttribute(AssertTypeVisitor::ATTRIBUTE_NAME) === true) {
            return [];
        }

        $returnType = $scope->getType($node);

        if ( ! $returnType instanceof ErrorType) {
            return [];
        }

        $arguments = $this->getNormalizedArguments($node);

        if ($arguments === null) {
            return [];
        }

        $objectType = $scope->getType($arguments['object']);

        if ($objectType instanceof MixedType) {
            return [];
        }

        $propertyOrMethodType = $scope->getType($arguments['item']);

        return [
            RuleErrorBuilder::message(sprintf(
                'Cannot get attribute %s on %s.',
                $propertyOrMethodType->describe(VerbosityLevel::value()),
                $objectType->describe(VerbosityLevel::value()),
            ))->identifier('getAttribute.unknown')->tip('See https://twig.symfony.com/doc/3.x/templates.html#dot_operator')->build(),
        ];
    }

    /**
     * @return null|array{object: Expr, item: Expr, type: Expr}
     */
    private function getNormalizedArguments(StaticCall $methodCall): ?array
    {
        if (count($methodCall->args) < 5) {
            return null;
        }

        if ( ! $methodCall->args[2] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[3] instanceof Arg) {
            return null;
        }

        if ( ! $methodCall->args[5] instanceof Arg) {
            return null;
        }

        return [
            'object' => $methodCall->args[2]->value,
            'item' => $methodCall->args[3]->value,
            'type' => $methodCall->args[5]->value,
        ];
    }
}
