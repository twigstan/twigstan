<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\ShouldNotHappenException;
use TwigStan\Twig\CommentHelper;

/**
 * @implements Collector<Node\Expr\MethodCall, array{
 *     blockName: string,
 *     sourceLocation: string,
 *     context: string,
 *     parent: bool,
 * }>
 */
final readonly class BlockContextCollector implements Collector
{
    public function getNodeType(): string
    {
        return Node\Expr\YieldFrom::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof Node\Expr\YieldFrom) {
            return null;
        }

        if (! $node->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (! $node->expr->name instanceof Node\Identifier) {
            return null;
        }

        if (!in_array($node->expr->name->name, ['yieldBlock', 'yieldParentBlock'], true)) {
            return null;
        }

        $blockName = $node->expr->args[0]->value->value;
        $context = $scope->getType($node->expr->args[1]->value);

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