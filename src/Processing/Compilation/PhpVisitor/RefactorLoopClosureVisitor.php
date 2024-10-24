<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;

final class RefactorLoopClosureVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        // Find: yield from ($_v1 = function ($iterator, &$context, $blocks, $recurseFunc, $depth) {
        // Replace: yield from ($_v1 = function ($iterator, $__context, $blocks, $recurseFunc, $depth) use (&$context) {

        if ( ! $node instanceof Node\Expr\YieldFrom) {
            return null;
        }

        $funcCall = $node->expr;

        if ( ! $funcCall instanceof Node\Expr\FuncCall) {
            return null;
        }

        if ( ! $funcCall->name instanceof Node\Expr\Assign) {
            return null;
        }

        if ( ! $funcCall->name->var instanceof Variable) {
            return null;
        }

        if ( ! is_string($funcCall->name->var->name)) {
            return null;
        }

        if ( ! str_starts_with($funcCall->name->var->name, '_v')) {
            return null;
        }

        if ( ! $funcCall->name->expr instanceof Node\Expr\Closure) {
            return null;
        }

        $closure = $funcCall->name->expr;

        if (count($closure->params) !== 5) {
            return null;
        }

        if ( ! $closure->params[0]->var instanceof Variable) {
            return null;
        }

        if ($closure->params[0]->var->name !== 'iterator') {
            return null;
        }

        if ( ! $closure->params[1]->var instanceof Variable) {
            return null;
        }

        if ($closure->params[1]->var->name !== 'context') {
            return null;
        }

        $closure->params[1]->var->name = '__context';
        $closure->params[1]->byRef = false;

        $closure->uses[] = new Node\ClosureUse(new Variable('context'), true);

        return $node;
    }
}
