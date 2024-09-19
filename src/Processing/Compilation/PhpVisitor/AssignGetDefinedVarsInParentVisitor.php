<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

final class AssignGetDefinedVarsInParentVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node | null
    {
        // Find: $context['_parent'] = $context;
        // Replace with: $context['_parent'] = get_defined_vars();

        if (!$node instanceof Node\Expr\Assign) {
            return null;
        }

        if (!$node->var instanceof ArrayDimFetch) {
            return null;
        }

        if (!$node->var->var instanceof Variable) {
            return null;
        }

        if ($node->var->var->name !== 'context') {
            return null;
        }

        if (!$node->var->dim instanceof String_) {
            return null;
        }

        if ($node->var->dim->value !== '_parent') {
            return null;
        }

        if (!$node->expr instanceof Variable) {
            return null;
        }

        if ($node->expr->name !== 'context') {
            return null;
        }

        return new Node\Expr\Assign(
            new Node\Expr\ArrayDimFetch(
                new Variable('context'),
                new String_('_parent'),
            ),
            new Node\Expr\FuncCall(
                new Node\Name('get_defined_vars'),
            ),
        );
    }
}
