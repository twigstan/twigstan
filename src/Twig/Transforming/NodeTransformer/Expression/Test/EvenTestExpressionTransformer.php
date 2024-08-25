<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Test;

use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\Mod;
use PhpParser\Node\Scalar\LNumber;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Test\EvenTest;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<EvenTest>
 */
final readonly class EvenTestExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return EvenTest::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Identical
    {
        return new Identical(
            new Mod(
                $delegator->transform($node->getNode('node'), $scope),
                new LNumber(2),
            ),
            new LNumber(0),
        );
    }
}
