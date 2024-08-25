<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use TwigStan\Twig\Node\NodeMapper;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\MethodCallExpression;
use Twig\Node\Node;

/**
 * @implements TwigNodeTransformer<MethodCallExpression>
 */
final readonly class MethodCallExpressionTransformer implements TwigNodeTransformer
{
    public function __construct(private NodeMapper $nodeMapper) {}

    public static function getType(): string
    {
        return MethodCallExpression::class;
    }

    public function transform(Node $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): MethodCall
    {
        return new MethodCall(
            $delegator->transform($node->getNode('node'), $scope),
            $node->getAttribute('method'),
            $this->nodeMapper->map(
                $node->hasNode('arguments') ? $node->getNode('arguments') : new Node(),
                fn(AbstractExpression $expr) => new Arg($delegator->transform($expr, $scope)),
            ),
        );
    }

}
