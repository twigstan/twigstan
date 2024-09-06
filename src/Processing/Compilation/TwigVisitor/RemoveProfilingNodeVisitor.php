<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Profiler\Node\EnterProfileNode;
use Twig\Profiler\Node\LeaveProfileNode;

final readonly class RemoveProfilingNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if (!$node instanceof EnterProfileNode && !$node instanceof LeaveProfileNode) {
            return $node;
        }

        return null;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
