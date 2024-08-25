<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node as PhpNode;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPStan\ShouldNotHappenException;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<ConstantExpression>
 */
final readonly class ConstantExpressionTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return ConstantExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): PhpNode
    {
        if ($node->getAttribute('value') === true) {
            return new ConstFetch(
                new Name('true'),
            );
        }

        if ($node->getAttribute('value') === false) {
            return new ConstFetch(
                new Name('false'),
            );
        }

        if ($node->getAttribute('value') === null) {
            return new ConstFetch(
                new Name('null'),
            );
        }

        if (is_string($node->getAttribute('value'))) {
            return new String_(
                $node->getAttribute('value'),
            );
        }

        if (is_int($node->getAttribute('value'))) {
            return new LNumber(
                $node->getAttribute('value'),
            );
        }

        if (is_float($node->getAttribute('value'))) {
            return new DNumber(
                $node->getAttribute('value'),
            );
        }

        throw new ShouldNotHappenException(
            sprintf('Unsupported ConstantExpression "%s"', get_debug_type($node->getAttribute('value'))),
        );
    }

}
