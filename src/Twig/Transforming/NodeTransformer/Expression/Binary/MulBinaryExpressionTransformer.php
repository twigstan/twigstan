<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\Mul;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\MulBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<MulBinary>
 */
final readonly class MulBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return MulBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Mul
    {
        return new Mul(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
