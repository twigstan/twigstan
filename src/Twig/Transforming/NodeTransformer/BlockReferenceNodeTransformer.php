<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Comment;
use PhpParser\Node\Stmt\Nop;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\BlockReferenceNode;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<BlockReferenceNode>
 */
final readonly class BlockReferenceNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return BlockReferenceNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        return [
            new Nop(
                attributes: [
                    'startLine' => $node->getTemplateLine(),
                    'comments' => [new Comment(sprintf('// Block: %s', $node->getAttribute('name')))],
                ],
            ),
            ...$scope->getBlock($node->getAttribute('name')),
        ];
    }

}
