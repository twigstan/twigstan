<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\ShouldNotHappenException;
use TwigStan\Twig\CommentHelper;
use TwigStan\Twig\SourceLocation;

/**
 * @implements Collector<Node\Expr\YieldFrom, array{
 *     blockName: string,
 *     sourceLocation: SourceLocation,
 *     context: string,
 *     parent: bool,
 * }>
 */
final readonly class BlockContextCollector implements Collector, ExportingCollector
{
    public function getNodeType(): string
    {
        return Node\Expr\YieldFrom::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if ( ! $node->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $node->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ( ! in_array($node->expr->name->name, ['yieldBlock', 'yieldParentBlock'], true)) {
            return null;
        }

        if (count($node->expr->args) < 2) {
            return null;
        }

        if ( ! $node->expr->args[0] instanceof Node\Arg) {
            return null;
        }

        if ( ! $node->expr->args[0]->value instanceof Node\Scalar\String_) {
            return null;
        }

        if ( ! $node->expr->args[1] instanceof Node\Arg) {
            return null;
        }

        $blockName = $node->expr->args[0]->value->value;
        $context = $scope->getVariableType('context');

        $sourceLocation = null;
        foreach ($node->getComments() as $comment) {
            $sourceLocation = CommentHelper::getSourceLocationFromComment($comment->getText());
            if ($sourceLocation !== null) {
                break;
            }
        }

        if ($sourceLocation === null) {
            throw new ShouldNotHappenException(sprintf('Could not find Twig line number on %s:%d', $scope->getFile(), $node->getStartLine()));
        }

        return [
            'blockName' => $blockName,
            'sourceLocation' => $sourceLocation,
            'context' => (new Printer())->print($context->toPhpDocNode()), // or this?: $context->describe(VerbosityLevel::cache()),
            'parent' => $node->expr->name->name === 'yieldParentBlock',
        ];
    }
}
