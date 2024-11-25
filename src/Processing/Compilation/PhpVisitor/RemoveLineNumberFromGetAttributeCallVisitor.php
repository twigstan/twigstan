<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Twig\Extension\CoreExtension;

final class RemoveLineNumberFromGetAttributeCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node\Expr\StaticCall
    {
        if ( ! $node instanceof Node\Expr\StaticCall) {
            return null;
        }

        if ( ! $node->class instanceof Node\Name\FullyQualified) {
            return null;
        }

        if ($node->class->toString() !== CoreExtension::class) {
            return null;
        }

        if ( ! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name !== 'getAttribute') {
            return null;
        }

        if ( ! isset($node->args[9])) {
            foreach ($node->args as $arg) {
                if ( ! $arg instanceof Node\Arg) {
                    continue;
                }

                if ( ! $arg->name instanceof Node\Identifier) {
                    continue;
                }

                if ($arg->name->name !== 'lineno') {
                    continue;
                }

                $arg->value = new Node\Scalar\Int_(0);

                return $node;
            }

            return null;
        }

        if ( ! $node->args[9] instanceof Node\Arg) {
            return null;
        }

        $node->args[9]->value = new Node\Scalar\Int_(0);

        return $node;
    }
}
