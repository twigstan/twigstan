<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

/**
 * Replaces the Symfony UX ComponentNode (`{% component %}` and `<twig:Name>...</twig:Name>`).
 * The original compiles to runtime internals that change between UX versions and cannot be analyzed.
 *
 * Compiles to the context that the blocks of the component's body are rendered with:
 *     yield from \twigstan_embed(123, [...$context, ...["type" => "success"], ...\twigstan_component($context, "Alert", ["type" => "success"])]);
 * The twigstan_component() call also marks the template of the component as rendered with these props.
 */
#[YieldReady]
final class SimpleComponentNode extends Node implements NodeOutputInterface
{
    public static function create(Node $node): self
    {
        if ( ! $node->hasNode('component')) {
            // symfony/ux-twig-component 2.x stores the name as a string.
            $component = new ConstantExpression($node->getAttribute('component'), $node->getTemplateLine());
        } else {
            // symfony/ux-twig-component 3.x stores an expression, where a bare name like `{% component Alert %}` is the literal name.
            $component = $node->getNode('component');

            if ($component instanceof NameExpression && ! $component->hasExplicitParentheses()) {
                $component = new ConstantExpression($component->getAttribute('name'), $node->getTemplateLine());
            }
        }

        $nodes = ['component' => $component];

        if ($node->hasNode('props')) {
            $nodes['props'] = $node->getNode('props');
        }

        return new self(
            $nodes,
            [
                'only' => $node->getAttribute('only'),
                'embedded_index' => $node->getAttribute('embedded_index'),
            ],
            $node->getTemplateLine(),
        );
    }

    public function compile(Compiler $compiler): void
    {
        $context = $this->getAttribute('only') === true ? '[]' : '$context';

        $compiler
            ->addDebugInfo($this)
            ->write('yield from \twigstan_embed(')
            ->repr((int) $this->getAttribute('embedded_index'))
            ->raw(sprintf(', [...%s, ...', $context));
        $this->compileProps($compiler);
        $compiler
            ->raw(sprintf(', ...\twigstan_component(%s, ', $context))
            ->subcompile($this->getNode('component'))
            ->raw(', ');
        $this->compileProps($compiler);
        $compiler->raw(")]);\n");
    }

    private function compileProps(Compiler $compiler): void
    {
        if ($this->hasNode('props')) {
            $compiler->subcompile($this->getNode('props'));

            return;
        }

        $compiler->raw('[]');
    }
}
