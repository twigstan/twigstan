<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Compiler;
use Twig\Node\Expression\NameExpression;

final class SimpleNameExpression extends NameExpression
{
    /**
     * @var array<string, string>
     */
    private array $specialVars = [
        '_self' => '$this->getTemplateName()',
        '_context' => '$context',
        '_charset' => '$this->env->getCharset()',
    ];

    public static function create(NameExpression $node): self
    {
        $simple = new self($node->getAttribute('name'), $node->getTemplateLine());
        $simple->attributes = $node->attributes;

        return $simple;
    }

    public function compile(Compiler $compiler): void
    {
        $name = $this->getAttribute('name');

        if ($this->getAttribute('is_defined_test')) {
            if (isset($this->specialVars[$name])) {
                $compiler->repr(true);
            } else {
                $compiler
                    ->raw('twigstan_variable_exists(')
                    ->string($name)
                    ->raw(')')
                ;
            }
        } elseif (isset($this->specialVars[$name])) {
            $compiler->raw($this->specialVars[$name]);
        } else {
            $compiler
                ->raw('$')
                ->raw($name)
            ;
        }
    }
}
