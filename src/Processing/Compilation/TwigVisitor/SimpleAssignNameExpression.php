<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Compiler;
use Twig\Node\Expression\AssignNameExpression;

final class SimpleAssignNameExpression extends AssignNameExpression
{
    public static function create(AssignNameExpression $node): self
    {
        $simple = new self($node->getAttribute('name'), $node->getTemplateLine());
        $simple->attributes = $node->attributes;

        return $simple;
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('$')
            ->raw($this->getAttribute('name'))
        ;
    }
}
