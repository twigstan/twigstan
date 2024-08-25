<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\Concat;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<ConcatBinary>
 */
final readonly class ConcatBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return ConcatBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Concat
    {
        return new Concat(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
