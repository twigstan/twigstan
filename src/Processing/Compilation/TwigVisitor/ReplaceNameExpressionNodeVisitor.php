<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Environment;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class ReplaceNameExpressionNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if (!$node instanceof NameExpression) {
            return $node;
        }

        if ($node instanceof AssignNameExpression) {
            return SimpleAssignNameExpression::create($node);
        }

        return SimpleNameExpression::create($node);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
