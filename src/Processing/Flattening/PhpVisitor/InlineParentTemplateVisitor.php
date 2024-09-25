<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

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
     * @return null|array<Node\Stmt\Expression>
     */
    public function leaveNode(Node $node): ?array
    {
        // Find: yield from $this->yieldTemplate(get_defined_vars(), "@EndToEnd/_layout.twig", "@EndToEnd/case5.twig", 1);

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        $node = $node->expr;
        if ( ! $node instanceof Node\Expr\YieldFrom) {
            return null;
        }

        $expr = $node->expr;
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
            // @todo how to handle?
            return null;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AppendSourceLocationVisitor($sourceLocation));
        $stmts = $traverser->traverse($this->stmts);

        return [
            new Node\Stmt\Expression(
                new Node\Expr\FuncCall(
                    new Node\Expr\Closure(
                        [
                            'stmts' => $stmts,
                            'params' => [
                                new Node\Param(
                                    var: new Node\Expr\Variable('__twigstan_context'),
                                    type: new Node\Name('array'),
                                ),
                            ],
                        ],
                    ),
                    [
                        $expr->args[0],
                    ],
                ),
                attributes: ['comments' => $node->getComments()],
            ),
        ];
    }
}
