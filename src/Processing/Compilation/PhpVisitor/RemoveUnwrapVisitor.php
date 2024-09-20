<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class RemoveUnwrapVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node | null
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name !== 'unwrap') {
            return null;
        }

        return $node->var;
    }
}
