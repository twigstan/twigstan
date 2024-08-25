<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\NotEqual;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\NotEqualBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<NotEqualBinary>
 */
final readonly class NotEqualBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return NotEqualBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): NotEqual
    {
        return new NotEqual(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
