<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantArrayType;
use ReflectionException;
use TwigStan\PHPStan\Type\RequirementsConstantArrayType;
use TwigStan\Twig\Requirements\RequirementsNotFoundException;
use TwigStan\Twig\Requirements\RequirementsReader;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route as LegacyRoute;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @implements Rule<MethodReturnStatementsNode>
 */
class RenderRequirementsRule implements Rule
{
    public function __construct(
        private RequirementsReader $requirementsReader,
    ) {}

    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodReturnStatementsNode) {
            return [];
        }

        if (!$this->hasRouteAttribute($node, $scope)) {
            return [];
        }

        $returnStatements = $node->getReturnStatements();
        if (count($returnStatements) === 0) {
            return [];
        }

        $errors = [];
        foreach ($returnStatements as $returnStatement) {
            $returnNode = $returnStatement->getReturnNode();
            if ($returnNode->expr === null) {
                continue;
            }

            [$template, $context] = $this->findTemplateAndContextFromMethodCallOrAttribute($returnNode->expr, $returnStatement->getScope(), $node->getMethodName());

            if ($template === null && $context === null) {
                continue;
            }

            if (!$context->isConstantArray()->yes()) {
                continue;
            }

            try {
                $requirements = $this->requirementsReader->read($template);
            } catch (RequirementsNotFoundException) {
                $requirements = new RequirementsConstantArrayType([], []);
            }

            $accepts = $requirements->acceptsWithReason($context, true, true, true);

            foreach ($accepts->reasons as $reason) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Requirements for template "%s" are not valid: %s.',
                    $template,
                    rtrim($reason, '.'),
                ))->identifier('twig.render.invalidRequirements')->line($returnNode->getLine())->build();
            }
        }

        return $errors;
    }

    /**
     * @param Node\Expr $expr
     * @param Scope $scope
     * @param string $methodName
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
