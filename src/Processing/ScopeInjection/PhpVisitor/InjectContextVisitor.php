<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection\PhpVisitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Printer\Printer;
use TwigStan\PHPStan\Collector\BlockContextCollector;
use TwigStan\Processing\ScopeInjection\ArrayShapeMerger;
use TwigStan\Processing\ScopeInjection\TwigScopeInjector;

/**
 * @phpstan-import-type ContextData from TwigScopeInjector
 */
final class InjectContextVisitor extends NodeVisitorAbstract
{
    /**
     * @param list<ContextData> $contextBeforeBlock
     */
    public function __construct(
        private readonly array $contextBeforeBlock,
        private readonly ArrayShapeMerger $arrayShapeMerger,
    ) {}

    /**
     * The index of the embedded template whose class is visited, such as 123 in __TwigTemplate_abc___123.
     */
    private ?int $embedIndex = null;

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->embedIndex = preg_match('/___(\d+)$/', $node->name->name ?? '', $match) === 1 ? (int) $match[1] : null;

            return null;
        }

        // Search for the following pattern:
        //     // line 7
        //    /**
        //     * @param array{} $context
        //     * @return iterable<scalar>
        //     */
        //    public function block_main(array $context) : iterable

        if ( ! $node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        $phpDoc = $node->getDocComment();

        if ($phpDoc === null) {
            return null;
        }

        if (preg_match('/^(?<parent>parent_)?block_(?<blockName>\w+)$/', $node->name->name, $match) === 1) {
            $contextBeforeBlock = $this->embedIndex !== null
                ? $this->getContextBeforeBlock(BlockContextCollector::embedKey($this->embedIndex), false)
                : $this->getContextBeforeBlock($match['blockName'], $match['parent'] !== '');

            $context = $contextBeforeBlock;
        } else {
            return null;
        }

        $node->setDocComment(
            new Doc(
                sprintf(
                    <<<'DOC'
                        /**
                         * @param %s $context
                         * @param array{} $blocks
                         * @return iterable<null|scalar|\Stringable>
                         */
                        DOC,
                    (new Printer())->print($context),
                ),
            ),
        );

        return $node;
    }

    private function getContextBeforeBlock(string $blockName, bool $parent): ArrayShapeNode
    {
        $context = null;
        foreach ($this->contextBeforeBlock as $contextBeforeBlock) {
            if ($contextBeforeBlock['blockName'] !== $blockName) {
                continue;
            }

            if ($contextBeforeBlock['parent'] !== $parent) {
                continue;
            }

            if ($context === null) {
                $context = $contextBeforeBlock['context'];

                continue;
            }

            $context = $this->arrayShapeMerger->merge($context, $contextBeforeBlock['context']);
        }

        return $context ?? ArrayShapeNode::createSealed([]);
    }
}
