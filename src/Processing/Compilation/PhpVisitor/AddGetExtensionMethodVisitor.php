<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use Twig\Extension\ExtensionInterface;

final class AddGetExtensionMethodVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        if ( ! $node instanceof Stmt\Class_) {
            return null;
        }

        $node->stmts[] = new Stmt\ClassMethod(
            'getExtension',
            [
                'flags' => Modifiers::PROTECTED,
                'returnType' => new Node\Name\FullyQualified(ExtensionInterface::class),
                'params' => [
                    new Node\Param(new Node\Expr\Variable('class'), type: new Node\Identifier('string')),
                ],
                'stmts' => [
                    new Stmt\Return_(
                        new Node\Expr\ArrayDimFetch(
                            new Node\Expr\PropertyFetch(
                                new Node\Expr\Variable('this'),
                                new Node\Identifier('extensions'),
                            ),
                            new Node\Expr\Variable('class'),
                        ),
                    ),
                ],
            ],
            [
                'comments' => [
                    new Doc(
                        <<<'DOC'
                            /**
                             * @template TExtension of \Twig\Extension\ExtensionInterface
                             *
                             * @param class-string<TExtension> $class
                             *
                             * @return TExtension
                             */
                            DOC
                    ),
                ],
            ],
        );

        return $node;
    }
}
