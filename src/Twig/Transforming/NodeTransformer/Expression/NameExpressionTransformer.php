<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Expr\Variable;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<NameExpression>
 */
final readonly class NameExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return NameExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Variable
    {
        if ($node->getAttribute('name') === '_self') {
            return new Variable('this');
        }

        return new Variable($node->getAttribute('name'));
    }

}
