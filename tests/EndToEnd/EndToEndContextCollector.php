<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use Symfony\Component\Filesystem\Path;
use TwigStan\PHPStan\Collector\TemplateContextCollector;
use TwigStan\Twig\SourceLocation;

/**
 * @implements TemplateContextCollector<Node\Expr\Yield_>
 */
final readonly class EndToEndContextCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Expr\Yield_::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return null;
        }

        if ( ! $classReflection->isSubclassOf(AbstractRenderingTestCase::class)) {
            return null;
        }

        if ($scope->getFunction()?->getName() !== 'getContextForTemplates') {
            return null;
        }

        if ( ! $node->value instanceof Node\Expr\Array_) {
            return null;
        }

        if (count($node->value->items) !== 2) {
            return null;
        }

        if ( ! $node->value->items[0]->value instanceof Node\Expr\BinaryOp\Concat) {
            return null;
        }

        if ( ! $node->value->items[0]->value->left instanceof Node\Scalar\MagicConst\Dir) {
            return null;
        }

        if ( ! $node->value->items[0]->value->right instanceof Node\Scalar\String_) {
            return null;
        }

        $view = $node->value->items[0]->value->right->value;
        $context = $scope->getType($node->value->items[1]->value);

        return [
            [
                'sourceLocation' => new SourceLocation($scope->getFile(), 0),
                'template' => Path::join(Path::getDirectory($scope->getFile()), $view),
                'context' => (new Printer())->print($context->toPhpDocNode()),
            ],
        ];
    }
}
