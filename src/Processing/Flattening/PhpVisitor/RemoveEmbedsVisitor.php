<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Removes the embedded templates of a parent template that is inlined into a child, in place:
 *     yield from \twigstan_embed(1, [...$context]);
 * becomes:
 *     yield from [];
 *
 * Embedded templates are numbered per template, and their classes are in the file of the parent,
 * so the context of the parent's first embedded template would be given to the child's first one.
 * The parent's embedded templates get their context when the parent is analysed.
 */
final class RemoveEmbedsVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): null
    {
        if (
            $node instanceof Node\Stmt\Expression
            && $node->expr instanceof Node\Expr\YieldFrom
            && $node->expr->expr instanceof Node\Expr\FuncCall
            && $node->expr->expr->name instanceof Node\Name
            && $node->expr->expr->name->toString() === 'twigstan_embed'
        ) {
            $node->expr->expr = new Node\Expr\Array_();
        }

        return null;
    }
}
