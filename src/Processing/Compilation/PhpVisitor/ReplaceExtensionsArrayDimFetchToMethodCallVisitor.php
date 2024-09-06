<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class ReplaceExtensionsArrayDimFetchToMethodCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node|null
    {
        // Find: $this->extensions['Symfony\\Bridge\\Twig\\Extension\\AssetExtension']
        // Replace: $this->env->getExtension('Symfony\\Bridge\\Twig\\Extension\\AssetExtension')

        if (!$node instanceof Node\Expr\ArrayDimFetch) {
            return $node;
        }

        if (!$node->var instanceof Node\Expr\PropertyFetch) {
            return $node;
        }

        if (!$node->var->var instanceof Node\Expr\Variable) {
            return $node;
        }

        if ($node->var->var->name !== 'this') {
            return $node;
        }

        if (!$node->var->name instanceof Node\Identifier) {
            return $node;
        }

        if ($node->var->name->name !== 'extensions') {
            return $node;
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
