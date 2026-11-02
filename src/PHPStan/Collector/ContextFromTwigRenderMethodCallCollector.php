<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ObjectType;
use Twig\Environment;
use TwigStan\Twig\SourceLocation;

/**
 * @implements TemplateContextCollector<Node\Expr\MethodCall>
 */
final readonly class ContextFromTwigRenderMethodCallCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if ( ! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ( ! in_array($node->name->name, ['render', 'display'], true)) {
            return null;
        }

        $varType = $scope->getType($node->var);

        if ( ! (new ObjectType(Environment::class))->isSuperTypeOf($varType)->yes()) {
            return null;
        }

        $method = $scope->getMethodReflection($varType, $node->name->name);

        if ($method === null) {
            return null;
        }

        $normalizedCall = ArgumentsNormalizer::reorderMethodArguments(
            ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $method->getVariants(), $method->getNamedArgumentsVariants()),
            $node,
        );

        if ($normalizedCall === null) {
            return null;
        }

        $args = $normalizedCall->getArgs();

        if ( ! isset($args[0])) {
            return null;
        }

        $views = $scope->getType($args[0]->value)->getConstantStrings();

        if (count($views) === 0) {
            return null;
        }

        if (isset($args[1])) {
            $context = $scope->getType($args[1]->value);
        } else {
            $context = new ConstantArrayType([], []);
        }

        $result = [];
        foreach ($views as $view) {
            $result[] = [
                'sourceLocation' => new SourceLocation($scope->getFile(), $node->getStartLine()),
                'template' => $view->getValue(),
                'context' => (new Printer())->print($context->toPhpDocNode()),
            ];
        }

        return $result;
    }
}
