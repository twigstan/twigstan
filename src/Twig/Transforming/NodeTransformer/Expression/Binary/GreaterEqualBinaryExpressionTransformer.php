<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\GreaterEqualBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<GreaterEqualBinary>
 */
final readonly class GreaterEqualBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return GreaterEqualBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): GreaterOrEqual
    {
        return new GreaterOrEqual(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
