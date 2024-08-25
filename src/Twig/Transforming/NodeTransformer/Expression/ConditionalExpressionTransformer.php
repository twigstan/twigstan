<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Expr\Ternary;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<ConditionalExpression>
 */
final readonly class ConditionalExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return ConditionalExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Ternary
    {
        return new Ternary(
            $delegator->transform($node->getNode('expr1'), $scope),
            $delegator->transform($node->getNode('expr2'), $scope),
            $delegator->transform($node->getNode('expr3'), $scope),
        );
    }

}
