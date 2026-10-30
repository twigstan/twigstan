<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use LogicException;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Type;
use TwigStan\Twig\CommentHelper;
use TwigStan\Twig\SourceLocation;

/**
 * @phpstan-type ContextData = array{
 *     blockName: null|string,
 *     sourceLocation: SourceLocation,
 *     context: string,
 *     parent: bool,
 *     relatedBlockName: null|string,
 *     relatedParent: bool,
 * }
 * @implements Collector<Node\Stmt\Expression, ContextData>
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

        // Find: yield from \twigstan_embed(123, $context);
        if ($node->expr->expr instanceof Node\Expr\FuncCall && $node->expr->expr->name instanceof Node\Name && $node->expr->expr->name->toString() === 'twigstan_embed') {
            $args = $node->expr->expr->getArgs();

            if (count($args) !== 2 || ! $args[0]->value instanceof Node\Scalar\Int_) {
                return null;
            }

            return $this->createContextData($node, $scope, self::embedKey($args[0]->value->value), $scope->getType($args[1]->value), false);
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

        return $this->createContextData($node, $scope, $blockName, $context, $node->expr->expr->name->name === 'yieldParentBlock');
    }

    /**
     * The blocks of an embedded template are rendered with the context where it is embedded.
     */
    public static function embedKey(int $index): string
    {
        return sprintf('@embed:%d', $index);
    }

    /**
     * @return ContextData
     */
    private function createContextData(Node\Stmt\Expression $node, Scope $scope, ?string $blockName, Type $context, bool $parent): array
    {
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

        $functionName = $scope->getFunctionName();

        if (null !== $functionName) {
            preg_match('/^(?<parent>parent_)?block_(?<blockName>\w+)$/', $functionName, $match);
        }

        return [
            'blockName' => $blockName,
            'sourceLocation' => $sourceLocation,
            'context' => (new Printer())->print($context->toPhpDocNode()),
            'parent' => $parent,
            'relatedBlockName' => $match['blockName'] ?? null,
            'relatedParent' => ($match['parent'] ?? '') !== '',
        ];
    }
}
