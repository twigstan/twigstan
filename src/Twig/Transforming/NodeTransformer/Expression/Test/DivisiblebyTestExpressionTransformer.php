<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Test;

use PhpParser\Node\Expr\BinaryOp\Mod;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Test\DivisiblebyTest;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<DivisiblebyTest>
 */
final readonly class DivisiblebyTestExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return DivisiblebyTest::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Mod
    {
        return new Mod(
            $delegator->transform($node->getNode('node'), $scope),
            $delegator->transform($node->getNode('arguments')->getNode('0'), $scope),
        );
    }
}
