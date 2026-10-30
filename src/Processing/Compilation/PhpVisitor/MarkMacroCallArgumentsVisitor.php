<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks the template and macro that macro arguments are passed to, so ContextFromMacroCallCollector can collect their types.
 *
 * Find:    $macros["forms"]->call("input", [$context["name"]], $context, 3, $this->source)
 * Replace: $macros["forms"]->call("input", \twigstan_macro_arguments("forms.twig", "input", [$context["name"]]), $context, 3, $this->source)
 */
final class MarkMacroCallArgumentsVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, null|string> The template of each imported macro namespace, or null when it is ambiguous
     */
    private array $imports = [];

    public function __construct(
        private readonly string $templateName,
    ) {}

    /**
     * @param array<Node> $nodes
     */
    public function beforeTraverse(array $nodes): null
    {
        $this->imports = [];

        // Find: $macros["forms"] = $this->macros["forms"] = $this->load("forms.twig", 1)->unwrap()->getMacroNamespace();
        foreach ((new NodeFinder())->findInstanceOf($nodes, Expr\Assign::class) as $assign) {
            $alias = $this->getAlias($assign->var);

            if ($alias === null) {
                continue;
            }

            $value = $assign->expr;
            while ($value instanceof Expr\Assign) {
                $value = $value->expr;
            }

            $template = $this->getTemplate($value);

            if ($template === null) {
                continue;
            }

            $this->imports[$alias] = array_key_exists($alias, $this->imports) && $this->imports[$alias] !== $template ? null : $template;
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Expr\MethodCall || ! $node->name instanceof Node\Identifier || $node->name->name !== 'call') {
            return null;
        }

        $alias = $this->getAlias($node->var);
        $template = $alias !== null ? $this->imports[$alias] ?? null : $this->getTemplate($node->var);

        if ($template === null) {
            return null;
        }

        $args = $node->getArgs();

        if (count($args) < 2 || ! $args[0]->value instanceof String_) {
            return null;
        }

        $args[1]->value = new Expr\FuncCall(
            new Node\Name\FullyQualified('twigstan_macro_arguments'),
            [
                new Node\Arg(new String_($template)),
                new Node\Arg(new String_($args[0]->value->value)),
                new Node\Arg($args[1]->value),
            ],
        );

        return $node;
    }

    /**
     * Find: $macros["forms"]
     */
    private function getAlias(Expr $expr): ?string
    {
        if ( ! $expr instanceof Expr\ArrayDimFetch || ! $expr->dim instanceof String_) {
            return null;
        }

        if ( ! $expr->var instanceof Expr\Variable || $expr->var->name !== 'macros') {
            return null;
        }

        return $expr->dim->value;
    }

    /**
     * Find: $this->getMacroNamespace()
     * Find: $this->load("forms.twig", 1)->unwrap()->getMacroNamespace()
     */
    private function getTemplate(Expr $expr): ?string
    {
        if ( ! $expr instanceof Expr\MethodCall || ! $expr->name instanceof Node\Identifier || $expr->name->name !== 'getMacroNamespace') {
            return null;
        }

        if ($expr->var instanceof Expr\Variable && $expr->var->name === 'this') {
            return $this->templateName;
        }

        $unwrap = $expr->var;

        if ( ! $unwrap instanceof Expr\MethodCall || ! $unwrap->name instanceof Node\Identifier || $unwrap->name->name !== 'unwrap') {
            return null;
        }

        $load = $unwrap->var;

        if ( ! $load instanceof Expr\MethodCall || ! $load->name instanceof Node\Identifier || $load->name->name !== 'load') {
            return null;
        }

        $template = $load->getArgs()[0]->value ?? null;

        return $template instanceof String_ ? $template->value : null;
    }
}
