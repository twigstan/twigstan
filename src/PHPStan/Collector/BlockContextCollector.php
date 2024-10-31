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
 * @implements Collector<Node\Stmt\Expression, array{
 *     blockName: null|string,
 *     sourceLocation: SourceLocation,
 *     context: string,
 *     parent: bool,
 * }>
 */
final readonly class BlockContextCollector implements Collector, ExportingCollector
{
    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        // Find: yield from $this->unwrap()->yieldBlock('footer', $context, $blocks);

        if ( ! $node->expr instanceof Node\Expr\YieldFrom) {
            return null;
        }

        if ( ! $node->expr->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $node->expr->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ( ! in_array($node->expr->expr->name->name, ['yield', 'yieldBlock', 'yieldParentBlock'], true)) {
            return null;
        }

        if (count($node->expr->expr->args) < 2) {
            return null;
        }

        $blockName = null;

        if ($node->expr->expr->name->name === 'yield') {
            if ( ! $node->expr->expr->args[0] instanceof Node\Arg) {
                return null;
            }

            $context = $scope->getType($node->expr->expr->args[0]->value);
        } else {
            // yieldBlock, yieldParentBlock

            if ( ! $node->expr->expr->args[0] instanceof Node\Arg) {
                return null;
            }

            if ( ! $node->expr->expr->args[0]->value instanceof Node\Scalar\String_) {
                return null;
            }

            if ( ! $node->expr->expr->args[1] instanceof Node\Arg) {
                return null;
            }

            $blockName = $node->expr->expr->args[0]->value->value;
            $context = $scope->getType($node->expr->expr->args[1]->value);
        }

        $sourceLocation = null;
        foreach ($node->getComments() as $comment) {
            $sourceLocation = CommentHelper::getSourceLocationFromComment($comment->getText());

            if ($sourceLocation !== null) {
                break;
            }
        }

        if ($sourceLocation === null) {
            throw new ShouldNotHappenException(sprintf('Could not find Twig line number on %s:%d.', $scope->getFile(), $node->getStartLine()));
        }

        return [
            'blockName' => $blockName,
            'sourceLocation' => $sourceLocation,
            'context' => (new Printer())->print($context->toPhpDocNode()),
            'parent' => $node->expr->expr->name->name === 'yieldParentBlock',
        ];
    }
}
