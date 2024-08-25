<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Expr\AssignOp\Concat;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Node as TwigNode;
use Twig\Node\PrintNode;

/**
 * @implements TwigNodeTransformer<PrintNode>
 */
final readonly class PrintNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return PrintNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        $var = new Variable($scope->getOutputVariable());

        $stmts = [];
        foreach ($node as $expr) {
            $stmts[] = new Expression(
                new Concat($var, $delegator->transform($expr, $scope)),
                attributes: ['startLine' => $expr->getTemplateLine()],
            );
        }

        return $stmts;
    }

}
