<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Twig\Extension\CoreExtension;

final class RefactorStaticIncludeCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node | null
    {
        // Find: \Twig\Extension\CoreExtension::include($this->env, $context, "_random_footer.twig"))
        // Replace: $this->include(get_defined_vars(), "_random_footer.twig"))

        if (!$node instanceof Node\Expr\StaticCall) {
            return null;
        }

        if (!$node->class instanceof Node\Name) {
            return null;
        }

        if ($node->class->toString() !== CoreExtension::class) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name !== 'include') {
            return null;
        }

        if (count($node->args) < 3) {
            return null;
        }

        if (!$node->args[1] instanceof Node\Arg) {
            return null;
        }

        if (!$node->args[1]->value instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->args[1]->value->name !== 'context') {
            return null;
        }

        unset($node->args[0]);

        $node->args[1]->value = new Node\Expr\FuncCall(
            new Node\Name('get_defined_vars'),
        );

        return new Node\Expr\MethodCall(
            new Node\Expr\Variable('this'),
            'include',
            array_values($node->args),
        );
    }
}
