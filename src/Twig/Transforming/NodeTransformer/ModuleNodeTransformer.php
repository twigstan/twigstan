<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Comment\Doc;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Unset_;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node as TwigNode;
use TwigStan\PHP\Node\TwigLineNumberNode;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<ModuleNode>
 */
final readonly class ModuleNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return ModuleNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        if ($node->hasAttribute('requirements')) {
            $scope = $scope->withRequirements($node->getAttribute('requirements'));
        }

        $scope = $scope->withBlocks(
            $this->transformBlocks(
                $node,
                $scope,
                $delegator,
            ),
        );

        $functionStmts = [
            new Expression(new Assign(new Variable($scope->getOutputVariable()), new String_(''))),
            ...$delegator->transform($node->getNode('body'), $scope, true),
        ];

        if ($node->hasNode('parent')) {
            $functionStmts = [
                ...$functionStmts,
                ...$this->convertExtends($node->getNode('parent'), $scope, $delegator),
            ];
        }

        $functionStmts[] = new Return_(new Variable($scope->getOutputVariable()));

        $stmts = [];

        $requirements = $scope->getRequirements();

        $params = [];
        $paramDocs = [];
        foreach($requirements as $variable => $type) {
            if (str_ends_with($variable, '?')) {
                $variable = substr($variable, 0, -1);
                if (!str_contains($type, '|null') && !str_contains($type, 'null|')) {
                    $type = str_replace('|null', '', $type);
                }
                $params[] = new Param(new Variable($variable), new ConstFetch(new Name('null')), new Identifier('mixed'));
            } else {
                $params[] = new Param(new Variable($variable), type: new Identifier('mixed'));
            }

            $paramDocs[] = sprintf(' * @param %s $%s', $type, $variable);
        }

        $function = new Function_(
            'twigstan_template',
            [
                'params' => $params,
                'returnType' => new Identifier('string'),
                'stmts' => $functionStmts,
            ],
            [
                'comments' => $paramDocs !== [] ? [new Doc(sprintf("/**\n%s\n*/", implode(PHP_EOL, $paramDocs)))] : [],
            ],
        );
        $function->namespacedName = $function->name;

        $stmts[] = $function;

        return $stmts;
    }


    private function transformBlocks(ModuleNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        $blockStatements = [];
        foreach ($node->getNode('blocks') as $name => $node) {
            $stmts = [];

            $oldScope = $scope;

            $scope = $scope->enterScope();
            $contextVariable = $scope->getContextVariable();

            $stmts[] = new Expression(
                new Assign(
                    new Variable($contextVariable),
                    new Array_(
                        array_map(
                            fn(string $name) => new ArrayItem(
                                new Variable($name),
                                new String_($name),
                            ),
                            $scope->getAllVariableNames(),
                        ),
                        ['kind' => Array_::KIND_SHORT],
                    ),
                ),
            );

            $stmts = [...$stmts, ...$delegator->transform($node, $scope)];

            if ($scope->getAssignedVariableNames() !== []) {
                $stmts[] = new Unset_(
                    array_map(
                        fn(string $name) => new Variable($name),
                        $scope->getAssignedVariableNames(),
                    ),
                );
            }

            $restore = array_intersect($scope->getAssignedVariableNames(), $oldScope->getAllVariableNames(), );
            foreach ($restore as $variableName) {
                $stmts[] = new Expression(
                    new Assign(
                        new Variable($variableName),
                        new ArrayDimFetch(
                            new Variable($contextVariable),
                            new String_($variableName),
                        ),
                    ),
                );
            }

            $stmts[] = new Unset_([
                new Variable($contextVariable),
            ]);

            $blockStatements[$name] = $stmts;
        }

        return $blockStatements;
    }


    private function convertExtends(AbstractExpression $expr, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        return [
            new TwigLineNumberNode($expr->getTemplateLine()),
            new Expression(
                new FuncCall(
                    new Name('twigstan_extends'),
                    [
                        new Arg(
                            $delegator->transform($expr, $scope),
                        ),
                        new Arg(
                            new Array_(
                                array_map(
                                    fn(string $name) => new ArrayItem(
                                        new Variable($name),
                                        new String_($name),
                                    ),
                                    $scope->getAllVariableNames(),
                                ),
                                ['kind' => Array_::KIND_SHORT],
                            ),
                        ),
                    ],
                ),
                ['startLine' => $expr->getTemplateLine()],
            ),
        ];
    }
}
