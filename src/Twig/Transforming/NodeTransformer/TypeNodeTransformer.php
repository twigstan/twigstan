<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Twig\Node\Node as TwigNode;
use TwigStan\Twig\Node\TypeNode;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<TypeNode>
 */
final readonly class TypeNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return TypeNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        $stmts = [];

        foreach ($node->getAttribute('types') as $name => $type) {
            $stmts[] = new Expression(
                new FuncCall(
                    new Name('twigstan_type_hint'),
                    [
                        new Arg(new String_($name)),
                        new Arg(new String_($type)),
                    ],
                ),
            );
        }

        return $stmts;
    }

}
