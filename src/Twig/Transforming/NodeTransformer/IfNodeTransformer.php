<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\If_;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\IfNode;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<IfNode>
 */
final readonly class IfNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return IfNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): If_
    {
        $subnodes = [];

        $tests = array_chunk(iterator_to_array($node->getNode('tests')), 2);

        [$condition,$body] = array_shift($tests);

        $cond = $delegator->transform($condition, $scope);
        $subnodes['stmts'] = $delegator->transform($body, $scope, );

        foreach ($tests as [$condition, $body]) {
            $subnodes['elseifs'][] = new ElseIf_(
                $delegator->transform($condition, $scope),
                $delegator->transform($body, $scope, ),
                ['startLine' => $condition->getTemplateLine()],
            );
        }

        if ($node->hasNode('else')) {
            $body = $node->getNode('else');

            $subnodes['else'] = new Else_(
                $delegator->transform($body, $scope, ),
                ['startLine' => $node->getNode('else')->getTemplateLine()],
            );
        }

        return new If_(
            $cond,
            $subnodes,
            ['startLine' => $node->getTemplateLine()],
        );
    }

}
