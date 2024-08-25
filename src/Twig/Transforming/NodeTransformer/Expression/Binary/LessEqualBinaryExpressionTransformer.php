<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\SmallerOrEqual;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\LessEqualBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<LessEqualBinary>
 */
final readonly class LessEqualBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return LessEqualBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): SmallerOrEqual
    {
        return new SmallerOrEqual(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
