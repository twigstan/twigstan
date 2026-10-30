<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Twig guards imported macros in case the template body that imports them was not rendered.
 * TwigStan knows which macros are imported, so PHPStan reports the guard as unnecessary.
 *
 * Find:    ($macros["greeter"] ?? $this->throwUninitializedMacroNamespace(7))
 * Replace: $macros["greeter"]
 */
final class RemoveUninitializedMacroNamespaceGuardVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Expr\BinaryOp\Coalesce) {
            return null;
        }

        if ( ! $node->right instanceof Expr\MethodCall) {
            return null;
        }

        if ( ! $node->right->var instanceof Expr\Variable || $node->right->var->name !== 'this') {
            return null;
        }

        if ( ! $node->right->name instanceof Node\Identifier || $node->right->name->name !== 'throwUninitializedMacroNamespace') {
            return null;
        }

        return $node->left;
    }
}
