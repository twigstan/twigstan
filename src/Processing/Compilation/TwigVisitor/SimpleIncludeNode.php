<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use InvalidArgumentException;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Extension\CoreExtension;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\IncludeNode;

#[YieldReady]
final class SimpleIncludeNode extends IncludeNode
{
    public static function create(IncludeNode $node): self
    {
        $expr = $node->getNode('expr');

        if ( ! $expr instanceof AbstractExpression) {
            throw new InvalidArgumentException('The "expr" node must be an instance of AbstractExpression.');
        }

        $variables = $node->hasNode('variables') ? $node->getNode('variables') : null;

        if ($variables !== null && ! $variables instanceof AbstractExpression) {
            throw new InvalidArgumentException('The "variables" node must be an instance of AbstractExpression.');
        }

        return new self(
            $expr,
            $variables,
            $node->getAttribute('only'),
            $node->getAttribute('ignore_missing'),
            $node->getTemplateLine(),
        );
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->write(sprintf('%s::include(', CoreExtension::class))
            ->raw('$this->env, $context, ')
            ->subcompile($this->getNode('expr'))
            ->raw(', ');

        if ($this->hasNode('variables')) {
            $compiler->subcompile($this->getNode('variables'));
        } else {
            $compiler->raw('[]');
        }

        $compiler
            ->raw(', ')
            ->raw($this->getAttribute('only') ? 'true' : 'false')
            ->raw(', ')
            ->raw($this->getAttribute('ignore_missing') ? 'true' : 'false')
            ->raw(");\n");
    }
}
