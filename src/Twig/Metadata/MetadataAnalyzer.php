<?php

declare(strict_types=1);

namespace TwigStan\Twig\Metadata;

use RuntimeException;
use Twig\Environment;
use Twig\Node\BodyNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\Node;
use TwigStan\Processing\Compilation\Parser\TwigNodeParser;
use TwigStan\Twig\Node\NodeFinder;
use TwigStan\Twig\TwigFileCanonicalizer;

final readonly class MetadataAnalyzer
{
    public function __construct(
        private Environment $twig,
        private TwigNodeParser $twigNodeParser,
        private NodeFinder $nodeFinder,
        private TwigFileCanonicalizer $twigFileCanonicalizer,
    ) {}

    public function getMetadata(string $template): Metadata
    {
        $template = $this->twigNodeParser->parse($template);

        if ($template->getSourceContext() === null) {
            throw new RuntimeException('Template has no source context.');
        }

        $parentLineNumber = null;
        $parents = [];

        if ($template->hasNode('parent')) {
            $parentLineNumber = $template->getNode('parent')->getTemplateLine();
            $parents = array_map(
                $this->twigFileCanonicalizer->canonicalize(...),
                $this->getStringsFromExpression($template->getNode('parent')),
            );
        }

        $traits = [];
        foreach ($template->getNode('traits') as $trait) {
            $targets = [];
            foreach ($trait->getNode('targets') as $blockName => $target) {
                $targets[$blockName] = $target->getAttribute('value');
            }

            $traits[] = [
                'name' => $trait->getNode('template')->getAttribute('value'),
                'targets' => $targets,
            ];
        }

        $blocks = [];
        $parentBlocks = [];

        foreach ($template->getNode('blocks') as $block) {
            if ( ! $block instanceof BodyNode) {
                throw new RuntimeException(sprintf('Node is expected to be of type %s, got %s.', BodyNode::class, $block::class));
            }

            $blockName = $block->getNode('0')->getAttribute('name');
            $blocks[] = $blockName;

            $parentExpression = $this->nodeFinder->findInstanceOf($block, ParentExpression::class);

            if ($parentExpression !== null) {
                $parentBlocks[] = $blockName;
            }
        }

        return new Metadata(
            $template->getSourceContext()->getName(),
            $this->twig->getTemplateClass($template->getSourceContext()->getName()),
            $template->getSourceContext()->getPath(),
            $parentLineNumber,
            $parents,
            $traits,
            $blocks,
            $parentBlocks,
        );
    }

    /**
     * @return list<string>
     */
    private function getStringsFromExpression(Node $node): array
    {
        if ($node instanceof ConstantExpression) {
            return [$node->getAttribute('value')];
        }

        // if ($node instanceof NameExpression) {
        //    return ['$' . $node->getAttribute('name')];
        // }

        if ($node instanceof ArrayExpression) {
            $strings = [];
            foreach ($node as $element) {
                $strings = [...$strings, ...$this->getStringsFromExpression($element)];
            }

            return $strings;
        }

        if ($node instanceof ConditionalExpression) {
            return [
                ...$this->getStringsFromExpression($node->getNode('expr2')),
                ...$this->getStringsFromExpression($node->getNode('expr3')),
            ];
        }

        if ($node instanceof ConcatBinary) {
            if ($node->getNode('left') instanceof ConstantExpression) {
                return array_map(
                    fn(string $template) => $node->getNode('left')->getAttribute('value') . $template,
                    $this->getStringsFromExpression($node->getNode('right')),
                );
            }

            if ($node->getNode('right') instanceof ConstantExpression) {
                return array_map(
                    fn(string $template) => $template . $node->getNode('right')->getAttribute('value'),
                    $this->getStringsFromExpression($node->getNode('left')),
                );
            }
        }

        throw new RuntimeException(sprintf('Parent node "%s" is not a constant expression.', $node::class));
    }
}
