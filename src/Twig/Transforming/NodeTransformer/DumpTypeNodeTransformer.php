<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use TwigStan\Twig\Node\DumpTypeNode;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<DumpTypeNode>
 */
final readonly class DumpTypeNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return DumpTypeNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Expression
    {
        return new Expression(
            new FuncCall(
                new FullyQualified('PHPStan\dumpType'),
                [
                    new Arg(
                        $node->hasNode('expr') ? $delegator->transform($node->getNode('expr'), $scope) : new Variable($scope->getContextVariable()),
                    ),
                ],
            ),
            ['startLine' => $node->getTemplateLine()],
        );
    }

}
