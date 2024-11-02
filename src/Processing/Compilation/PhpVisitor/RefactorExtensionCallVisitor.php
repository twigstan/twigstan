<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class RefactorExtensionCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        // Find: $this->extensions['Twig\Extension\CoreExtension']->modifyDate
        // Replace: $this->getExtension('Twig\Extension\CoreExtension')->modifyDate

        if ( ! $node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $node->var instanceof Node\Expr\ArrayDimFetch) {
            return null;
        }

        if ( ! $node->var->var instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        if ( ! $node->var->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->var->var->var->name !== 'this') {
            return null;
        }

        if ( ! $node->var->var->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->var->var->name->name !== 'extensions') {
            return null;
        }

        if ( ! $node->var->dim instanceof Node\Scalar\String_) {
            return null;
        }

        $node->var = new Node\Expr\MethodCall(
            $node->var->var->var,
            'getExtension',
            [
                new Node\Arg($node->var->dim),
            ],
        );

        return $node;
    }
}
