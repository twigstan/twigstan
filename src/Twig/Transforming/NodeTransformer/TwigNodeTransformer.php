<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node as PhpNode;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Node as TwigNode;

/**
 * @template TwigNode of TwigNode
 */
interface TwigNodeTransformer
{
    /**
     * @return class-string|array<class-string>
     */
    public static function getType(): string | array;

    /**
     * @return PhpNode|list<PhpNode>
     */
    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array | PhpNode;
}
