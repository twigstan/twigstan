<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

#[YieldReady]
final class DumpTypeNode extends Node
{
    public function __construct(?AbstractExpression $expr, int $lineno = 0)
    {
        parent::__construct($expr !== null ? ['expr' => $expr] : [], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        if ( ! $this->hasNode('expr')) {
            $var = $compiler->getVarName();
            $compiler->write(sprintf("\$%s = get_defined_vars();\n", $var));
        }

        $compiler->write('\PHPStan\dumpType(');

        if ($this->hasNode('expr')) {
            $compiler->subcompile($this->getNode('expr'));
        } else {
            $compiler->raw('$')->raw($var);
        }

        $compiler->raw(");\n");

        if ( ! $this->hasNode('expr')) {
            $compiler->write(sprintf("unset(\$%s);\n", $var));
        }
    }
}
