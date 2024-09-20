<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Twig\Extension\CoreExtension;

final class RefactorStaticMacroCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node | null
    {
        // Find: CoreExtension::callMacro($macros["layout"], "macro_hello", [$name], 9, $context, $this->getSourceContext());
        // Replace: self::callMacro("layout", "macro_hello", [$name], 9, get_defined_vars())

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

        if ($node->name->name !== 'callMacro') {
            return null;
        }

        if (count($node->args) !== 6) {
            return null;
        }

        if (!$node->args[0] instanceof Node\Arg) {
            return null;
        }

        if (!$node->args[0]->value instanceof Node\Expr\ArrayDimFetch) {
            return null;
        }

        if (!$node->args[0]->value->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->args[0]->value->var->name !== 'macros') {
            return null;
        }

        if ($node->args[0]->value->dim === null) {
            return null;
        }

        $node->args[0]->value = $node->args[0]->value->dim;

        if (!$node->args[4] instanceof Node\Arg) {
            return null;
        }

        $node->args[4]->value = new Node\Expr\FuncCall(
            new Node\Name('get_defined_vars'),
        );

        unset($node->args[5]);

        return new Node\Expr\MethodCall(
            new Node\Expr\Variable('this'),
            'callMacro',
            $node->args,
        );

    }
}
