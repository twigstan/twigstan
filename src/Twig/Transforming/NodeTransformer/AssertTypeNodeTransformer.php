<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Comment;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Twig\Node\Node as TwigNode;
use TwigStan\Twig\Node\AssertTypeNode;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<AssertTypeNode>
 */
final readonly class AssertTypeNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return AssertTypeNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Expression
    {
        return new Expression(
            new FuncCall(
                new FullyQualified('PHPStan\Testing\assertType'),
                [
                    new Arg(new String_($node->getAttribute('expectedType'))),
                    new Arg(
                        new Variable($node->getAttribute('name')),
                        attributes: [
                            'comments' => [new Comment('// @phpstan-ignore variable.undefined')],
                        ],
                    ),
                ],
            ),
        );
    }

}
