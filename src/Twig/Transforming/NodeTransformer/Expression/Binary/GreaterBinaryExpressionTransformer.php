<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\Greater;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\GreaterBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<GreaterBinary>
 */
final readonly class GreaterBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return GreaterBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Greater
    {
        return new Greater(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
