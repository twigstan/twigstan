<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\CaptureNode;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<CaptureNode>
 */
final readonly class CaptureNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return CaptureNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        return [
            new Expression(
                new Assign(
                    new Variable($scope->getOutputVariable()),
                    new String_(''),
                ),
                attributes: ['startLine' => $node->getTemplateLine()],
            ),
            ...$delegator->transform($node->getNode('body'), $scope),
        ];
    }

}
