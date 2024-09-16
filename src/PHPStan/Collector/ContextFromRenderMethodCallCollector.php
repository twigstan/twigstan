<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @implements TemplateContextCollector<Node\Expr\MethodCall>
 */
final readonly class ContextFromRenderMethodCallCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->var->name !== 'this') {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if (!\in_array($node->name->name, ['render', 'renderView'], true)) {
            return null;
        }

        $varType = $scope->getType($node->var);
        $method = $scope->getMethodReflection($varType, $node->name->toString());

        if ($method === null) {
            return null;
        }

        if ($method->getDeclaringClass()->getName() !== AbstractController::class) {
            return null;
        }

        if (count($node->args) !== 2) {
            return null;
        }

        if (!$node->args[0] instanceof Node\Arg) {
            return null;
        }

        if (!$node->args[0]->value instanceof Node\Scalar\String_) {
            return null;
        }

        if (!$node->args[1] instanceof Node\Arg) {
            return null;
        }

        $template = $node->args[0]->value->value;
        $context = $scope->getType($node->args[1]->value);

        return [
            [
                'startLine' => $node->getStartLine(),
                'endLine' => $node->getEndLine(),
                'template' => $template,
                'context' => (new Printer())->print($context->toPhpDocNode()),
            ],
        ];
    }
}
