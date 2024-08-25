<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use TwigStan\Twig\Node\NodeMapper;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\BodyNode;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<BodyNode>
 */
final readonly class BodyNodeTransformer implements TwigNodeTransformer
{
    public function __construct(private NodeMapper $nodeMapper) {}

    public static function getType(): string
    {
        return BodyNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        return $this->nodeMapper->map(
            $node,
            fn($node) => $delegator->transform($node, $scope),
        );
    }

}
