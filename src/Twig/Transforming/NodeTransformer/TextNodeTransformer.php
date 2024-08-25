<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Expr\AssignOp\Concat;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Node as TwigNode;
use Twig\Node\TextNode;

/**
 * @implements TwigNodeTransformer<TextNode>
 */
final readonly class TextNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return TextNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): Expression
    {
        return new Expression(
            new Concat(
                new Variable($scope->getOutputVariable()),
                new String_($node->getAttribute('data')),
            ),
            attributes: ['startLine' => $node->getTemplateLine()],
        );
    }

}
