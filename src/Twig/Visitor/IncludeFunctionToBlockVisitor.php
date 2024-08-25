<?php

declare(strict_types=1);

namespace TwigStan\Twig\Visitor;

use PhpParser\Node\Expr\Instanceof_;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class IncludeFunctionToBlockVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof PrintNode) {
            return $node;
        }

        $expr = $node->getNode('expr');

        if (! $expr instanceof FunctionExpression) {
            return $node;
        }

        if ($expr->getAttribute('name') !== 'include') {
            return $node;
        }

        $arguments = iterator_to_array($expr->getNode('arguments'));

        return new IncludeNode(
            $arguments[0],
            $arguments[1] ?? null,
            isset($arguments['with_context']) && $arguments['with_context'] instanceof ConstantExpression ? !$arguments['with_context']->getAttribute('value') : false,
            isset($arguments['ignore_missing']) && $arguments['ignore_missing'] instanceof ConstantExpression ? !$arguments['ignore_missing']->getAttribute('value') : false,
            $node->getTemplateLine(),
        );
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
