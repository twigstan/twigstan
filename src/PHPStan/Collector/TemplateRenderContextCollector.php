<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Constant\ConstantArrayType;
use ReflectionException;
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
final readonly class TemplateRenderContextCollector implements Collector
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

            [$template, $context] = $this->findTemplateAndContextFromMethodCallOrAttribute($returnNode->expr, $returnStatement->getScope(), $node->getMethodName());

            if ($template === null && $context === null) {
                continue;
            }

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

    /**
     *
     * @return array{string|null, ConstantArrayType|null}
     * @throws ReflectionException
     */
    private function findTemplateAndContextFromMethodCallOrAttribute(Node\Expr $expr, Scope $scope, string $methodName): array
    {
        $returnType = $scope->getType($expr);

        if ($returnType->isArray()->yes()) {
            $attributes = $scope->getClassReflection()->getNativeReflection()->getMethod($methodName)->getAttributes(Template::class);
            return [$attributes[0]?->getArguments()[0], $returnType];
        }

        if ($expr instanceof Node\Expr\MethodCall && $expr->name instanceof Node\Identifier && $expr->name->toString() === 'render') {
            $methodCalledOnType = $scope->getType($expr->var);
            $methodReflection = $scope->getMethodReflection($methodCalledOnType, $expr->name->name);
            if ($methodReflection->getDeclaringClass()->getName() === AbstractController::class) {
                $context = $expr->args[1]->value;

                return [$expr->args[0]->value->value, $context === null ? new ConstantArrayType([], []) : $scope->getType($context)];
            }
        }

        return [null, null];
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
}
