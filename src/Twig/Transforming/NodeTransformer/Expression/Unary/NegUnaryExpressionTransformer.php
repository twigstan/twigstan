<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Unary;

use PhpParser\Node\Expr\UnaryMinus;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<NegUnary>
 */
final readonly class NegUnaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return NegUnary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): UnaryMinus
    {
        return new UnaryMinus($delegator->transform($node->getNode('node'), $scope));
    }

}
