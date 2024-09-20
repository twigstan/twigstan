<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class RefactorLoadTemplateYieldVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node | null
    {
        // Find: $this->parent = $this->loadTemplate("@EndToEnd/_layout.twig", "@EndToEnd/case5.twig", 1, ... maybe more);
        // Replace: yield from $this->yieldTemplate(get_defined_vars(), "@EndToEnd/_layout.twig", "@EndToEnd/case5.twig", 1, .. maybe more);

        if (!$node instanceof Node\Expr\Assign) {
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

        if ($node->var->name->name !== 'parent') {
            return null;
        }

        if (!$node->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->expr->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->expr->var->name !== 'this') {
            return null;
        }

        // Check if $node->expr->name is loadTemplate

        if (!$node->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->expr->name->name !== 'loadTemplate') {
            return null;
        }

        return new Node\Expr\YieldFrom(
            new Node\Expr\MethodCall(
                new Node\Expr\Variable('this'),
                new Node\Identifier('yieldTemplate'),
                [
                    new Node\Arg(
                        new Node\Expr\FuncCall(
                            new Node\Name('get_defined_vars'),
                        ),
                    ),
                    ...$node->expr->args,
                ],
            ),
        );
    }
}
