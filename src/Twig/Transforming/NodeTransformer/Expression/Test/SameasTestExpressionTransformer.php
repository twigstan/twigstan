<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Test;

use PhpParser\Node\Expr\BinaryOp\Identical;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Test\SameasTest;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<SameasTest>
 */
final readonly class SameasTestExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return SameasTest::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Identical
    {
        return new Identical(
            $delegator->transform($node->getNode('node'), $scope),
            $delegator->transform($node->getNode('arguments')->getNode('0'), $scope),
        );
    }
}
