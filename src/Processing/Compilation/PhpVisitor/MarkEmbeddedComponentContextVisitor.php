<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Symfony UX Twig components render the blocks of their body with the context where they are used,
 * the props of the component, and the component itself.
 *
 * Find:    $preRenderEvent = $runtime->startEmbedComponent("Alert", $props, $context, "page.twig", 123);
 *          ...
 *          $this->load("page.twig", 9, 123)->display($embeddedContext, $embeddedBlocks);
 * Insert before the load:
 *          // line 9
 *          yield from \twigstan_embed(123, [...$context, ...$props, 'this' => $preRenderEvent->getComponent(), ...]);
 */
final class MarkEmbeddedComponentContextVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Stmt\Else_) {
            return null;
        }

        $startEmbed = null;
        $stmts = [];
        foreach ($node->stmts as $stmt) {
            $startEmbed ??= $this->getStartEmbedComponent($stmt);

            $load = $startEmbed !== null ? $this->getLoad($stmt) : null;

            if ($startEmbed !== null && $load !== null) {
                [$event, $props, $context] = $startEmbed;
                [$line, $index] = $load;

                $stmts[] = new Node\Stmt\Expression(
                    new Expr\YieldFrom(new Expr\FuncCall(
                        new Node\Name\FullyQualified('twigstan_embed'),
                        [
                            new Node\Arg($index),
                            new Node\Arg(new Expr\Array_([
                                new Node\ArrayItem($context, unpack: true),
                                new Node\ArrayItem($props, unpack: true),
                                new Node\ArrayItem(new Expr\MethodCall($event, 'getComponent'), new Node\Scalar\String_('this')),
                                new Node\ArrayItem(
                                    new Expr\MethodCall(new Expr\MethodCall($event, 'getMountedComponent'), 'getAttributes'),
                                    new Node\Scalar\String_('attributes'),
                                ),
                                new Node\ArrayItem($context, new Node\Scalar\String_('outerScope')),
                            ])),
                        ],
                    )),
                    ['comments' => [new Comment(sprintf('// line %d', $line))]],
                );

                $startEmbed = null;
            }

            $stmts[] = $stmt;
        }

        $node->stmts = $stmts;

        return $node;
    }

    /**
     * @return null|array{Expr, Expr, Expr} The event, the props and the context
     */
    private function getStartEmbedComponent(Node\Stmt $stmt): ?array
    {
        if ( ! $stmt instanceof Node\Stmt\Expression || ! $stmt->expr instanceof Expr\Assign) {
            return null;
        }

        $call = $stmt->expr->expr;

        if ( ! $call instanceof Expr\MethodCall || ! $call->name instanceof Node\Identifier || $call->name->name !== 'startEmbedComponent') {
            return null;
        }

        $args = $call->getArgs();

        if (count($args) < 3) {
            return null;
        }

        $props = $args[1]->value;

        // The props are converted with CoreExtension::toArray(), which loses the array shape of a literal.
        // Props that are not a literal would lose the array shape of the whole context, so they are left out.
        if ($props instanceof Expr\StaticCall && $props->name instanceof Node\Identifier && $props->name->name === 'toArray') {
            $props = $props->getArgs()[0]->value ?? null;
        }

        if ( ! $props instanceof Expr\Array_) {
            $props = new Expr\Array_();
        }

        return [$stmt->expr->var, $props, $args[2]->value];
    }

    /**
     * @return null|array{int, Expr} The line and the index of the embedded template
     */
    private function getLoad(Node\Stmt $stmt): ?array
    {
        $load = (new NodeFinder())->findFirst(
            $stmt,
            fn(Node $node) => $node instanceof Expr\MethodCall && $node->name instanceof Node\Identifier && $node->name->name === 'load',
        );

        if ( ! $load instanceof Expr\MethodCall) {
            return null;
        }

        $args = $load->getArgs();

        if (count($args) !== 3 || ! $args[1]->value instanceof Node\Scalar\Int_) {
            return null;
        }

        return [$args[1]->value->value, $args[2]->value];
    }
}
