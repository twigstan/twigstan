<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class ReplaceExtensionsArrayDimFetchToMethodCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node | null
    {
        // Find: $this->extensions['Symfony\\Bridge\\Twig\\Extension\\AssetExtension']
        // Replace: $this->env->getExtension('Symfony\\Bridge\\Twig\\Extension\\AssetExtension')

        if (!$node instanceof Node\Expr\ArrayDimFetch) {
            return null;
        }

        if (!$node->var instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        if (!$node->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->var->var->name !== 'this') {
            return null;
        }

        if (!$node->var->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->var->name->name !== 'extensions') {
            return null;
        }

        if ($node->dim === null) {
            return null;
        }

        return new Node\Expr\MethodCall(
            new Node\Expr\PropertyFetch(
                new Node\Expr\Variable('this'),
                'env',
            ),
            'getExtension',
            [
                new Node\Arg($node->dim),
            ],
        );
    }
}
