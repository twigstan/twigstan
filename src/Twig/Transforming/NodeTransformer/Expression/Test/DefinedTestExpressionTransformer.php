<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Test;

use PhpParser\Node as PhpNode;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Test\DefinedTest;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<DefinedTest>
 */
final readonly class DefinedTestExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return DefinedTest::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): PhpNode
    {
        return $delegator->transform($node->getNode('node'), $scope);
    }
}
