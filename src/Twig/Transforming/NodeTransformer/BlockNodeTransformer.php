<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node as PhpNode;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\BlockNode;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<BlockNode>
 */
final readonly class BlockNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return BlockNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array|PhpNode
    {
        return $delegator->transform($node->getNode('body'), $scope);
    }

}
