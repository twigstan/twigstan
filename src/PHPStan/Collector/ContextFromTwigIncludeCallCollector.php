<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use LogicException;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use Twig\Extension\CoreExtension;
use TwigStan\Twig\CommentHelper;

/**
 * @implements TemplateContextCollector<Node\Stmt\Expression>
 */
final readonly class ContextFromTwigIncludeCallCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        // Find: yield \Twig\Extension\CoreExtension::include($this->env, $context, "@EndToEnd/Include/footer.twig", ["title" => "Hello, World!"], \false);

        if ( ! $node->expr instanceof Node\Expr\Yield_) {
            return null;
        }

        if ( ! $node->expr->value instanceof Node\Expr\StaticCall) {
            return null;
        }

        if ( ! $node->expr->value->class instanceof Node\Name\FullyQualified) {
            return null;
        }

        if ($node->expr->value->class->toString() !== CoreExtension::class) {
            return null;
        }

        if ( ! $node->expr->value->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->expr->value->name->name !== 'include') {
            return null;
        }

        $sourceLocation = null;
        foreach ($node->getComments() as $comment) {
            $sourceLocation = CommentHelper::getSourceLocationFromComment($comment->getText());

            if ($sourceLocation !== null) {
                break;
            }
        }

        if ($sourceLocation === null) {
            throw new LogicException(sprintf('Could not find Twig line number on %s:%d.', $scope->getFile(), $node->getStartLine()));
        }

        $args = $node->expr->value->getArgs();

        if (count($args) < 3) {
            return null;
        }

        $contexts = $scope->getType($args[1]->value)->getConstantArrays();

        if (count($contexts) !== 1) {
            return null;
        }

        $context = $contexts[0];

        $templates = $scope->getType($args[2]->value)->getConstantStrings();

        if (count($templates) === 0) {
            return null;
        }

        $variables = isset($args[3]) ? $scope->getType($args[3]->value)->getConstantArrays() : [new ConstantArrayType([], [])];

        if (count($variables) !== 1) {
            return null;
        }

        $variables = $variables[0];

        $withContext = ! isset($args[4]) || $scope->getType($args[4]->value)->isTrue()->yes();

        if ($withContext) {
            $builder = ConstantArrayTypeBuilder::createFromConstantArray($context);

            foreach ($variables->getKeyTypes() as $key) {
                $builder->setOffsetValueType($key, $variables->getOffsetValueType($key));
            }

            $templateContext = $builder->getArray();
        } else {
            $templateContext = $variables;
        }

        $result = [];
        foreach ($templates as $template) {
            $result[] = [
                'sourceLocation' => $sourceLocation,
                'template' => $template->getValue(),
                'context' => (new Printer())->print($templateContext->toPhpDocNode()),
            ];
        }

        return $result;
    }
}
