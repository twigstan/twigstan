<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\PhpDocParser\Printer\Printer;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route as LegacyRoute;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @implements Collector<MethodReturnStatementsNode, list<array{
 *     startLine: int,
 *     endLine: int,
 *     template: string,
 *     context: string,
 * }>>
 */
final readonly class ContextFromReturnedArrayWithTemplateAttributeCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof MethodReturnStatementsNode) {
            return null;
        }

        if (!$this->hasRouteAttribute($node, $scope)) {
            return null;
        }

        $template =  $this->getTemplateFromAttribute($node, $scope);
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

            $context = $scope->getType($returnNode->expr);

            $data[] = [
                'startLine' => $returnNode->getStartLine(),
                'endLine' => $returnNode->getEndLine(),
                'template' => $template,
                'context' => (new Printer())->print($context->toPhpDocNode()),
            ];
        }

        if ($data === []) {
            return null;
        }

        return $data;
    }


    private function hasRouteAttribute(Node $node, Scope $scope): bool
    {
        foreach ($scope->getClassReflection()->getNativeReflection()->getMethod($node->getMethodName())->getAttributes() as $attribute) {
            if ($attribute->getName() === Route::class) {
                return true;
            }

            if ($attribute->getName() === LegacyRoute::class) {
                return true;
            }
        }

        return false;
    }

    private function getTemplateFromAttribute(Node $node, Scope $scope): ?string
    {
        foreach ($scope->getClassReflection()->getNativeReflection()->getMethod($node->getMethodName())->getAttributes(Template::class) as $attribute) {
            return $attribute->getArguments()[0];
        }

        return null;
    }
}
