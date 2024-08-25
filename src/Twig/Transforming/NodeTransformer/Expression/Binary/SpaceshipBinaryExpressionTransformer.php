<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BinaryOp\Spaceship;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\SpaceshipBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<SpaceshipBinary>
 */
final readonly class SpaceshipBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return SpaceshipBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Spaceship
    {
        return new Spaceship(
            $delegator->transform($node->getNode('left'), $scope),
            $delegator->transform($node->getNode('right'), $scope),
        );
    }
}
