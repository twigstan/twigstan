<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Expr\Variable;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<TempNameExpression>
 */
final readonly class TempNameExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return TempNameExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Variable
    {
        return new Variable($node->getAttribute('name'));
    }

}
