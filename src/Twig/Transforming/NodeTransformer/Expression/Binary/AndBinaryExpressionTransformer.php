<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\AndBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<AndBinary>
 */
final readonly class AndBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return AndBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): BooleanAnd
    {
        return new BooleanAnd(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
