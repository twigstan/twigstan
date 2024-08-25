<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Test;

use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Test\NullTest;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<NullTest>
 */
final readonly class NullTestExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return NullTest::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Identical
    {
        return new Identical(
            $delegator->transform($node->getNode('node'), $scope),
            new ConstFetch(new Name('null')),
        );
    }
}
