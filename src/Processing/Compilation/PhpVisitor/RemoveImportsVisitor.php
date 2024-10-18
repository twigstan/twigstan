<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class RemoveImportsVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?int
    {
        if ( ! $node instanceof Node\Stmt\Use_) {
            return null;
        }

        return NodeVisitor::REMOVE_NODE;
    }
}
