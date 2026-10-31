<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use LogicException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
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
 * @implements Collector<Node\Stmt\Expression, list<ContextData>>
 */
final readonly class BlockContextCollector implements Collector, ExportingCollector
{
    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    /**
     * @return null|list<ContextData>
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if ( ! $node->expr instanceof Node\Expr\YieldFrom) {
            return $this->collectBlockCalls($node, $scope);
        }

        // Find: yield from \twigstan_embed(123, $context);
        if ($node->expr->expr instanceof Node\Expr\FuncCall && $node->expr->expr->name instanceof Node\Name && $node->expr->expr->name->toString() === 'twigstan_embed') {
            $args = $node->expr->expr->getArgs();

            if (count($args) !== 2 || ! $args[0]->value instanceof Node\Scalar\Int_) {
                return null;
            }

            return [$this->createContextData($node, $scope, self::embedKey($args[0]->value->value), $scope->getType($args[1]->value), false)];
        }

        // Find: yield from $this->parent->unwrap()->yield($context, $blocks);
        if (
            $node->expr->expr instanceof Node\Expr\MethodCall
            && $node->expr->expr->name instanceof Node\Identifier
            && $node->expr->expr->name->name === 'yield'
            && count($node->expr->expr->args) >= 2
            && $node->expr->expr->args[0] instanceof Node\Arg
        ) {
            return [$this->createContextData($node, $scope, null, $scope->getType($node->expr->expr->args[0]->value), false)];
        }

        return $this->collectBlockCalls($node, $scope);
    }

    /**
     * Find the blocks that the statement renders, such as:
     *     yield from $this->unwrap()->yieldBlock('footer', $context, $blocks);
     *     yield $this->unwrap()->hasBlock('aside', $context, $blocks) ? $this->unwrap()->renderBlock('aside', $context, $blocks) : '';
     *
     * @return null|list<ContextData>
     */
    private function collectBlockCalls(Node\Stmt\Expression $node, Scope $scope): ?array
    {
        $visitor = new class extends NodeVisitorAbstract {
            /**
             * @var list<Node\Expr\MethodCall>
             */
            public array $calls = [];

            public function enterNode(Node $node): ?int
            {
                // The statements of closures, such as for `{% set %}` blocks, are collected with their own scope.
                if ($node instanceof Node\FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if (
                    $node instanceof Node\Expr\MethodCall
                    && $node->name instanceof Node\Identifier
                    && in_array($node->name->name, ['yieldBlock', 'renderBlock', 'yieldParentBlock', 'renderParentBlock'], true)
                    && count($node->args) >= 2
                    && $node->args[0] instanceof Node\Arg
                    && $node->args[0]->value instanceof Node\Scalar\String_
                    && $node->args[1] instanceof Node\Arg
                ) {
                    $this->calls[] = $node;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse([$node->expr]);

        if ($visitor->calls === []) {
            return null;
        }

        $result = [];
        foreach ($visitor->calls as $call) {
            assert($call->args[0] instanceof Node\Arg && $call->args[0]->value instanceof Node\Scalar\String_ && $call->args[1] instanceof Node\Arg && $call->name instanceof Node\Identifier);

            $result[] = $this->createContextData(
                $node,
                $scope,
                $call->args[0]->value->value,
                $scope->getType($call->args[1]->value),
                in_array($call->name->name, ['yieldParentBlock', 'renderParentBlock'], true),
            );
        }

        return $result;
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
