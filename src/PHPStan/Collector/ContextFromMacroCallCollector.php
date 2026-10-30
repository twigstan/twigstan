<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use TwigStan\Twig\CommentHelper;

/**
 * Collects the types of the arguments that macros are called with, marked by MarkMacroCallArgumentsVisitor.
 *
 * @implements TemplateContextCollector<Node\Stmt>
 */
final readonly class ContextFromMacroCallCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        // Find: \twigstan_macro_arguments("forms.twig", "input", [$context["name"]])
        $calls = $this->findMacroArguments($node);

        if ($calls === []) {
            return null;
        }

        $sourceLocation = CommentHelper::getSourceLocationFromComments($node->getComments());

        if ($sourceLocation === null) {
            return null;
        }

        $result = [];
        foreach ($calls as $call) {
            $args = $call->getArgs();

            if (count($args) !== 3 || ! $args[0]->value instanceof Node\Scalar\String_ || ! $args[1]->value instanceof Node\Scalar\String_) {
                continue;
            }

            $arguments = $scope->getType($args[2]->value)->getConstantArrays();

            if (count($arguments) !== 1) {
                continue;
            }

            $result[] = [
                'sourceLocation' => $sourceLocation,
                'template' => $args[0]->value->value,
                'macro' => $args[1]->value->value,
                'context' => (new Printer())->print($arguments[0]->toPhpDocNode()),
            ];
        }

        return $result === [] ? null : $result;
    }

    /**
     * Searches the expressions of the statement. Nested statements and closures are visited separately, with their own scope.
     *
     * @return list<Node\Expr\FuncCall>
     */
    private function findMacroArguments(Node\Stmt $node): array
    {
        $visitor = new class($node) extends NodeVisitorAbstract {
            /**
             * @var list<Node\Expr\FuncCall>
             */
            public array $calls = [];

            public function __construct(
                private readonly Node\Stmt $statement,
            ) {}

            public function enterNode(Node $node): ?int
            {
                if (($node instanceof Node\Stmt && $node !== $this->statement) || $node instanceof Node\FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if (
                    $node instanceof Node\Expr\FuncCall
                    && $node->name instanceof Node\Name
                    && $node->name->toString() === 'twigstan_macro_arguments'
                ) {
                    $this->calls[] = $node;
                }

                return null;
            }
        };

        (new NodeTraverser($visitor))->traverse([$node]);

        return $visitor->calls;
    }
}
