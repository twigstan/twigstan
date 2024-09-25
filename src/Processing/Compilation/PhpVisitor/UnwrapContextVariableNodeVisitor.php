<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

final class UnwrapContextVariableNodeVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Variable
    {
        if ( ! $node instanceof ArrayDimFetch) {
            return null;
        }

        if ( ! $node->var instanceof Variable) {
            return null;
        }

        if ($node->var->name !== 'context') {
            return null;
        }

        if ( ! $node->dim instanceof String_) {
            return null;
        }

        $string = $node->dim;
        // if (str_starts_with($string->value, '_')) {
        //    return null;
        // }

        return new Variable($string->value);
    }
}
