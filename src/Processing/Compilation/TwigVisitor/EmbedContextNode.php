<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\EmbedNode;
use Twig\Node\Node;

/**
 * Compiles an embed to the context that its blocks are rendered with.
 */
#[YieldReady]
final class EmbedContextNode extends Node
{
    public static function create(EmbedNode $node): self
    {
        return new self(
            $node->hasNode('variables') ? ['variables' => $node->getNode('variables')] : [],
            [
                'index' => $node->getAttribute('index'),
                'only' => $node->getAttribute('only'),
            ],
            $node->getTemplateLine(),
        );
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('yield from \twigstan_embed(')
            ->repr($this->getAttribute('index'))
            ->raw(', [');

        if ($this->getAttribute('only') !== true) {
            $compiler->raw('...$context, ');
        }

        if ($this->hasNode('variables')) {
            $compiler
                ->raw('...')
                ->subcompile($this->getNode('variables'));
        }

        $compiler->raw("]);\n");
    }
}
