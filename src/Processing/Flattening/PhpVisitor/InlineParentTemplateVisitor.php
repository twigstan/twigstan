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
        // Find: $this->parent = $this->loadTemplate("@EndToEnd/Inheritance/_layout.twig", "@__main__/EndToEnd/Inheritance/case5.twig", 1);

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        if ( ! $node->expr instanceof Node\Expr\Assign) {
            return null;
        }

        $assign = $node->expr;

        if ( ! $assign->var instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        if ( ! $assign->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($assign->var->var->name !== 'this') {
            return null;
        }

        if ( ! $assign->var->name instanceof Node\Identifier) {
            return null;
        }

        if ($assign->var->name->name !== 'parent') {
            return null;
        }

        if ( ! $assign->expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $assign->expr->var instanceof Node\Expr\Variable) {
            return null;
        }

        if ($assign->expr->var->name !== 'this') {
            return null;
        }

        if ( ! $assign->expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($assign->expr->name->name !== 'loadTemplate') {
            return null;
        }

        if (count($assign->expr->args) !== 3) {
            return null;
        }

        $sourceLocation = CommentHelper::getSourceLocationFromComments($node->getComments());

        if ($sourceLocation === null) {
            throw new LogicException('Expected source location to be set.');
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AppendSourceLocationVisitor($sourceLocation));

        return [
            $node,
            ...$traverser->traverse($this->stmts),
        ];
    }
}
