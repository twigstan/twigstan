<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ObjectType;
use Twig\Environment;

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
        if (!$node->var instanceof Node\Expr\Variable) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if (!\in_array($node->name->name, ['render', 'display'], true)) {
            return null;
        }

        $varType = $scope->getType($node->var);
        if (!(new ObjectType(Environment::class))->isSuperTypeOf($varType)->yes()) {
            return null;
        }

        $args = $node->getArgs();
        if (!isset($args[0])) {
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
                'startLine' => $node->getStartLine(),
                'endLine' => $node->getEndLine(),
                'template' => $view->getValue(),
                'context' => (new Printer())->print($context->toPhpDocNode()),
            ];
        }

        return $result;
    }
}
