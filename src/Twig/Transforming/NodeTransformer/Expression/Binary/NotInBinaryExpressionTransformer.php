<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression\Binary;

use PhpParser\Node\Expr\BooleanNot;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\Binary\InBinary;
use Twig\Node\Expression\Binary\NotInBinary;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<NotInBinary>
 */
final readonly class NotInBinaryExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return NotInBinary::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): BooleanNot
    {
        return new BooleanNot(
            $delegator->transform(
                new InBinary(
                    $node->getNode('left'),
                    $node->getNode('right'),
                    $node->getTemplateLine(),
                ),
                $scope,
            ),
        );
    }
}
