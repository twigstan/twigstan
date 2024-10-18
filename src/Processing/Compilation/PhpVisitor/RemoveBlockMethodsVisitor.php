<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class RemoveBlockMethodsVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?int
    {
        if ( ! $node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        if ( ! str_starts_with($node->name->name, 'block_')) {
            return null;
        }

        return NodeVisitor::REMOVE_NODE;
    }
}
