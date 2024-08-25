<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Node as TwigNode;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<BlockReferenceExpression>
 */
final readonly class BlockReferenceExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return BlockReferenceExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): FuncCall
    {
        return new FuncCall(
            new Name('twigstan_has_block'),
            [
                new Arg($delegator->transform($node->getNode('name'), $scope)),
            ],
        );
    }

}
