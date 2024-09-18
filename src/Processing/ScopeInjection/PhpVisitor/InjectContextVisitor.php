<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection\PhpVisitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Printer\Printer;
use TwigStan\Processing\ScopeInjection\ArrayShapeMerger;
use TwigStan\Twig\SourceLocation;

final class InjectContextVisitor extends NodeVisitorAbstract
{
    /**
     * @param list<array{
     *     blockName: string,
     *     sourceLocation: SourceLocation,
     *     context: \PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode,
     *     parent: bool
     * }> $contextBeforeBlock
     */
    public function __construct(
        private ArrayShapeNode $contextFromTemplateRender,
        private array $contextBeforeBlock,
        private ArrayShapeMerger $arrayShapeMerger,
    ) {}

    public function enterNode(Node $node): Node | null
    {
        // Search for the following pattern:
        //     // line 7
        //    /**
        //     * @param array{} $__twigstan_context
        //     * @return iterable<scalar>
        //     */
        //    public function block_main(array $__twigstan_context) : iterable

        if (! $node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        if (! $node->name instanceof Node\Identifier) {
            return null;
        }

        $phpDoc = $node->getDocComment();
        if ($phpDoc === null) {
            return null;
        }

        if (preg_match('/^(?<parent>parent_)?block_(?<blockName>\w+)$/', $node->name->name, $match) === 1) {
            $contextBeforeBlock = $this->getContextBeforeBlock(
                $match['blockName'],
                $match['parent'] !== '',
            );

            $context = $this->arrayShapeMerger->merge(
                $this->contextFromTemplateRender,
                $contextBeforeBlock,
                true,
            );
        } elseif($node->name->name === 'main') {
            $context = $this->contextFromTemplateRender;
        } else {
            return null;
        }

        $node->setDocComment(new Doc(
            str_replace('array{}', (new Printer())->print($context), $phpDoc->getText()),
        ));
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

        return $context ?? new ArrayShapeNode([]);
    }
}
