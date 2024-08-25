<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node as PhpNode;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Twig\Node\IncludeNode;
use Twig\Node\Node as TwigNode;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<IncludeNode>
 */
final readonly class IncludeNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return IncludeNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): PhpNode
    {
        return new Expression(
            new FuncCall(
                new Name('twigstan_include'),
                [
                    new Arg($delegator->transform($node->getNode('expr'), $scope)),
                    new Arg(new Array_(
                        array_map(
                            fn(string $name) => new ArrayItem(
                                new Variable($name),
                                new String_($name),
                            ),
                            $node->getAttribute('only') ? [] : $scope->getAllVariableNames(),
                        ),
                        ['kind' => Array_::KIND_SHORT],
                    )),
                    new Arg($node->hasNode('variables') ? $delegator->transform($node->getNode('variables'), $scope) : new Array_(attributes: ['kind' => Array_::KIND_SHORT])),
                    new Arg(new ConstFetch(new Name($node->getAttribute('only') ? 'true' : 'false'))),
                ],
            ),
            ['startLine' => $node->getTemplateLine()],
        );
    }

}
