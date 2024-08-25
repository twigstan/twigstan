<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Unary;

use PhpParser\Node\Expr\BooleanNot;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<NotUnary>
 */
final readonly class NotUnaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return NotUnary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): BooleanNot
    {
        return new BooleanNot($delegator->transform($node->getNode('node'), $scope));
    }

}
