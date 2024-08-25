<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\OrBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<OrBinary>
 */
final readonly class OrBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return OrBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): BooleanOr
    {
        return new BooleanOr(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
