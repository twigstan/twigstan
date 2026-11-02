<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Routing\Attribute\Route;
use TwigStan\Twig\SourceLocation;

/**
 * @implements TemplateContextCollector<MethodReturnStatementsNode>
 */
final readonly class ContextFromReturnedArrayWithTemplateAttributeCollector implements TemplateContextCollector
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

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

        $template = $this->getTemplateFromAttribute($node, $classReflection, $scope);

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

            // This class does not exist anymore in Symfony 8.
            if ($attribute->getName() === 'Symfony\Component\Routing\Annotation\Route') {
                return true;
            }
        }

        return false;
    }

    private function getTemplateFromAttribute(MethodReturnStatementsNode $node, ClassReflection $classReflection, Scope $scope): ?string
    {
        $methodReflection = $classReflection->getNativeReflection()->getMethod($node->getMethodName());

        foreach ($methodReflection->getAttributes(Template::class) as $attribute) {
            if ( ! $this->reflectionProvider->hasClass(Template::class)) {
                return null;
            }

            $constructor = $this->reflectionProvider->getClass(Template::class)->getConstructor();

            $args = [];
            foreach ($attribute->getArgumentsExpressions() as $name => $value) {
                $args[] = new Node\Arg($value, name: is_string($name) ? new Node\Identifier($name) : null);
            }

            $new = ArgumentsNormalizer::reorderNewArguments(
                ParametersAcceptorSelector::selectFromArgs($scope, $args, $constructor->getVariants(), $constructor->getNamedArgumentsVariants()),
                new Node\Expr\New_(new Node\Name\FullyQualified(Template::class), $args),
            );

            if ($new === null) {
                continue;
            }

            $args = $new->getArgs();

            if ($args === []) {
                continue;
            }

            if ( ! $args[0]->value instanceof Node\Scalar\String_) {
                continue;
            }

            return $args[0]->value->value;
        }

        return null;
    }
}
