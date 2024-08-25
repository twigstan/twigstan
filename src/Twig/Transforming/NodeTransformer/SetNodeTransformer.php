<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node as TwigNode;
use Twig\Node\SetNode;

/**
 * @implements TwigNodeTransformer<SetNode>
 */
final readonly class SetNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return SetNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        $functionStmts = [];

        if ($node->getNodeTag() === 'apply') {
            $scope = $scope->withOutputVariable($node->getNode('names')->getAttribute('name'));

            if ($node->getNode('values') instanceof ConstantExpression) {
                $functionStmts[] = new Expression(
                    new Assign(
                        $delegator->transform($node->getNode('names'), $scope),
                        $delegator->transform($node->getNode('values'), $scope),
                    ),
                    ['startLine' => $node->getNode('values')->getTemplateLine()],
                );
            } else {
                $functionStmts = [...$functionStmts, ...$delegator->transform($node->getNode('values'), $scope)];
            }

            return $functionStmts;
        }

        foreach ($node->getNode('names') as $index => $nameNode) {
            if ($node->getAttribute('capture') === true) {
                $scope = $scope->withOutputVariable('__capture');
                $functionStmts = [...$functionStmts, ...$delegator->transform($node->getNode('values'), $scope)];

                $functionStmts[] = new Expression(
                    new Assign(
                        $delegator->transform($nameNode, $scope),
                        new Variable($scope->getOutputVariable()),
                    ),
                    attributes: ['startLine' => $nameNode->getTemplateLine()],
                );

                continue;
            }

            $valueNode = $node->getNode('values') instanceof ConstantExpression ? $node->getNode('values') : $node->getNode('values')->getNode((string) $index);

            $functionStmts[] = new Expression(
                new Assign(
                    $delegator->transform($nameNode, $scope),
                    $delegator->transform($valueNode, $scope),
                ),
                ['startLine' => $nameNode->getTemplateLine()],
            );

            $scope->assignVariableName($nameNode->getAttribute('name'));
        }

        return $functionStmts;
    }

}
