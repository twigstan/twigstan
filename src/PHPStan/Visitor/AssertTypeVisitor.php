<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class AssertTypeVisitor extends NodeVisitorAbstract
{
    public const string ATTRIBUTE_NAME = 'isInAssertType';

    public function enterNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Expr\FuncCall) {
            return null;
        }

        if ( ! $node->name instanceof Node\Name\FullyQualified) {
            return null;
        }

        if ($node->name->toString() !== 'PHPStan\Testing\assertType') {
            return null;
        }

        if ( ! isset($node->args[1])) {
            return null;
        }

        if ( ! $node->args[1] instanceof Node\Arg) {
            return null;
        }

        $node->args[1]->value->setAttribute(self::ATTRIBUTE_NAME, true);

        return $node;
    }
}
