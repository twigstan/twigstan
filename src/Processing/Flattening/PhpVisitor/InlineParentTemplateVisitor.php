<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use LogicException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Twig\CommentHelper;

final class InlineParentTemplateVisitor extends NodeVisitorAbstract
{
    /**
     * @param list<Node\Stmt> $stmts
     */
    public function __construct(
        private array $stmts,
    ) {}

    /**
     * @return null|array<Node>
     */
    public function leaveNode(Node $node): ?array
    {
        // Find: yield from $this->yieldTemplate($context, "@EndToEnd/_layout.twig", "@EndToEnd/case5.twig", 1);

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if ( ! $node->expr instanceof Node\Expr\YieldFrom) {
            return null;
        }

        $expr = $node->expr->expr;

        if ( ! $expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($expr->name->name !== 'yieldTemplate') {
            return null;
        }

        if (count($expr->args) !== 4) {
            return null;
        }

        $sourceLocation = CommentHelper::getSourceLocationFromComments($node->getComments());

        if ($sourceLocation === null) {
            throw new LogicException('Expected source location to be set.');
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AppendSourceLocationVisitor($sourceLocation));

        return $traverser->traverse($this->stmts);
    }
}
