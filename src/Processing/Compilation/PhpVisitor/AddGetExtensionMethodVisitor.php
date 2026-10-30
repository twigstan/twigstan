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
                    // Twig\Template::$extensions is untyped, while Environment::getExtension() is generic.
                    new Stmt\Return_(
                        new Node\Expr\MethodCall(
                            new Node\Expr\PropertyFetch(
                                new Node\Expr\Variable('this'),
                                new Node\Identifier('env'),
                            ),
                            new Node\Identifier('getExtension'),
                            [new Node\Arg(new Node\Expr\Variable('class'))],
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
