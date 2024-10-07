<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class RefactorYieldBlockVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        // Find: $this->yieldBlock("other", $context, $blocks);
        // Replace: $this->yieldBlock("other", $context, []);

        // Find: $this->yieldParentBlock("other", $context, $blocks);
        // Replace: $this->yieldParentBlock("other", $context, []);

        if ( ! $node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $node->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->var->name !== 'this') {
            return null;
        }

        if ( ! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ( ! in_array($node->name->name, ['yieldBlock', 'yieldParentBlock'], true)) {
            return null;
        }

        if (count($node->args) !== 3) {
            return null;
        }

        if ( ! $node->args[2] instanceof Node\Arg) {
            return null;
        }

        if ( ! $node->args[2]->value instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->args[2]->value->name !== 'blocks') {
            return null;
        }

        $node->args[2]->value = new Node\Expr\Array_(attributes: ['kind' => Node\Expr\Array_::KIND_SHORT]);

        return $node;
    }
}
