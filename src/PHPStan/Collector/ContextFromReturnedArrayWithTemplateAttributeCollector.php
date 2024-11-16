<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Reflection\ClassReflection;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Routing\Annotation\Route as LegacyRoute;
use Symfony\Component\Routing\Attribute\Route;
use TwigStan\Twig\SourceLocation;

/**
 * @implements TemplateContextCollector<MethodReturnStatementsNode>
 */
final readonly class ContextFromReturnedArrayWithTemplateAttributeCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return null;
        }

        if ( ! $this->hasRouteAttribute($node, $classReflection)) {
            return null;
        }

        $template = $this->getTemplateFromAttribute($node, $classReflection);

        if ($template === null) {
            return null;
        }

        $returnStatements = $node->getReturnStatements();

        if ($returnStatements === []) {
            return null;
        }

        $data = [];
        foreach ($returnStatements as $returnStatement) {
            $returnNode = $returnStatement->getReturnNode();

            if ($returnNode->expr === null) {
                continue;
            }

            $context = $returnStatement->getScope()->getType($returnNode->expr);

            if ( ! $context->isArray()->yes()) {
                continue;
            }

            $data[] = [
                'sourceLocation' => new SourceLocation($scope->getFile(), $returnNode->getStartLine()),
                'template' => $template,
                'context' => (new Printer())->print($context->toPhpDocNode()),
            ];
        }

        if ($data === []) {
            return null;
        }

        return $data;
    }

    private function hasRouteAttribute(MethodReturnStatementsNode $node, ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getNativeReflection()->getMethod($node->getMethodName())->getAttributes() as $attribute) {
            if ($attribute->getName() === Route::class) {
                return true;
            }

            if ($attribute->getName() === LegacyRoute::class) {
                return true;
            }
        }

        return false;
    }

    private function getTemplateFromAttribute(MethodReturnStatementsNode $node, ClassReflection $classReflection): ?string
    {
        $methodReflection = $classReflection->getNativeReflection()->getMethod($node->getMethodName());

        foreach ($methodReflection->getAttributes(Template::class) as $attribute) {
            $args = $attribute->getArgumentsExpressions();

            if ($args === []) {
                continue;
            }

            if ( ! $args[0] instanceof Node\Scalar\String_) {
                continue;
            }

            return $args[0]->value;
        }

        return null;
    }
}
