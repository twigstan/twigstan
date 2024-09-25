<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

final class RemoveParentUnsetVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Stmt\Unset_) {
            return null;
        }

        $node->vars = array_filter(
            $node->vars,
            function ($var): bool {
                // Filter: $context['_parent']

                if ( ! $var instanceof Node\Expr\ArrayDimFetch) {
                    return true;
                }

                if ( ! $var->var instanceof Variable) {
                    return true;
                }

                if ($var->var->name !== 'context') {
                    return true;
                }

                if ( ! $var->dim instanceof Node\Scalar\String_) {
                    return true;
                }

                if ($var->dim->value !== '_parent') {
                    return true;
                }

                return false;
            },
        );

        return $node;
    }
}
