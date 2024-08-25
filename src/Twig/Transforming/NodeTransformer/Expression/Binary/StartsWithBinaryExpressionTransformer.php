<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\StartsWithBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<StartsWithBinary>
 */
final readonly class StartsWithBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return StartsWithBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): FuncCall
    {
        return new FuncCall(
            new Name('str_starts_with'),
            [
                new Arg($delegator->transform($node->getNode('left'), $scope)),
                new Arg($delegator->transform($node->getNode('right'), $scope)),
            ],
        );
    }
}
