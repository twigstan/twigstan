<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Return_;
use TwigStan\Twig\Node\NodeMapper;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\ArrowFunctionExpression;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<ArrowFunctionExpression>
 */
final readonly class ArrowFunctionExpressionTransformer implements TwigNodeTransformer
{
    public function __construct(private NodeMapper $nodeMapper) {}

    public static function getType(): string
    {
        return ArrowFunctionExpression::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Closure
    {
        return new Closure(
            [
                'params' => $this->nodeMapper->map(
                    $node->getNode('names'),
                    callback: fn(AssignNameExpression $expr) => new Param(
                        new Variable($node->getAttribute('name')),
                    ),
                ),
                'returnType' => null,
                'stmts' => [
                    new Return_($delegator->transform($node->getNode('expr'), $scope)),
                ],
            ],
        );
    }

}
