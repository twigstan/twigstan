<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\InBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<InBinary>
 */
final readonly class InBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return InBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): FuncCall
    {
        // @todo this should follow CoreExtension::inFilter

        return new FuncCall(
            new Name('in_array'),
            [
                new Arg($delegator->transform($node->getNode('left'), $scope)),
                new Arg($delegator->transform($node->getNode('right'), $scope)),
                new Arg(new ConstFetch(new Name('true'))),
            ],
        );
    }
}
