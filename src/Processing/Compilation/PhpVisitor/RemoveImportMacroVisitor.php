<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class RemoveImportMacroVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?int
    {
        // Find something looking like this:
        //      // line 2
        //      $macros["layout"] = $this->macros["layout"] = $this->loadTemplate("_layout.twig", "@EndToEnd/case5.twig", 2);
        // Remove the whole statement including the comment above

        if (! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if (! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        if (! $node->expr->var instanceof Node\Expr\ArrayDimFetch) {
            return null;
        }

        if (! $node->expr->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($node->expr->var->var->name !== 'macros') {
            return null;
        }

        return NodeTraverser::REMOVE_NODE;

    }
}
