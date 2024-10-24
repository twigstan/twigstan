<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class MakeFinalVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Stmt\Class_) {
            return null;
        }

        $node->flags |= Modifiers::FINAL;

        return $node;
    }
}
