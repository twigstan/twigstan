<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node as PhpNode;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Name;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Node as TwigNode;
use Twig\Template;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<GetAttrExpression>
 */
final readonly class GetAttrExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return GetAttrExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): PhpNode
    {
        if ($node->getAttribute('type') === Template::METHOD_CALL) {
            return new NullsafeMethodCall(
                $delegator->transform($node->getNode('node'), $scope),
                $node->getNode('attribute')->getAttribute('value'),
                array_map(
                    fn(array $pair) => new Arg($delegator->transform($pair['value'], $scope)),
                    $node->hasNode('arguments') ? $node->getNode('arguments')->getKeyValuePairs() : [],
                ),
            );
        }

        if ($node->getAttribute('type') === Template::ARRAY_CALL) {
            return new ArrayDimFetch(
                $delegator->transform($node->getNode('node'), $scope),
                $delegator->transform($node->getNode('attribute'), $scope),
            );
        }

        return new FuncCall(
            new Name('twigstan_get_property_or_call_method'),
            [
                new Arg($delegator->transform($node->getNode('node'), $scope)),
                new Arg($delegator->transform($node->getNode('attribute'), $scope)),
            ],
        );
    }

}
