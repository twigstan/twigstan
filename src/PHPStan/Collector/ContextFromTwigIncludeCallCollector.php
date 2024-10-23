<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;

/**
 * @implements TemplateContextCollector<Node\Expr\MethodCall>
 */
final readonly class ContextFromTwigIncludeCallCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        // Find: $this->include(\get_defined_vars(), "@EndToEnd/Include/footer.twig", [], \false, \false);

        if ( ! $node->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->var->name !== 'this') {
            return null;
        }

        if ( ! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name !== 'include') {
            return null;
        }

        $args = $node->getArgs();

        if (count($args) < 4) {
            return null;
        }

        $context = $scope->getType($args[0]->value)->getConstantArrays();

        if (count($context) !== 1) {
            return null;
        }

        $context = $context[0];

        $templates = $scope->getType($args[1]->value)->getConstantStrings();

        if (count($templates) === 0) {
            return null;
        }

        $variables = $scope->getType($args[2]->value)->getConstantArrays();

        if (count($variables) !== 1) {
            return null;
        }

        $variables = $variables[0];

        $withContext = $scope->getType($args[3]->value)->isTrue()->yes();

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
                'startLine' => $node->getStartLine(),
                'endLine' => $node->getEndLine(),
                'template' => $template->getValue(),
                'context' => (new Printer())->print($templateContext->toPhpDocNode()),
            ];
        }

        return $result;
    }
}
