<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<ArrayExpression>
 */
final readonly class ArrayExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return ArrayExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Array_
    {
        $items = [];
        foreach (array_chunk(iterator_to_array($node), 2) as [$keyNode, $valueNode]) {
            $items[] = new ArrayItem(
                $delegator->transform($valueNode, $scope),
                $keyNode instanceof ConstantExpression && is_int($keyNode->getAttribute('value')) ? null : $delegator->transform($keyNode, $scope),
            );
        }

        return new Array_(
            $items,
            [
                'kind' => Array_::KIND_SHORT,
            ],
        );
    }

}
