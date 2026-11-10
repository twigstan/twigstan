<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Visitor;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class PreserveContextVisitorTest extends TestCase
{
    public function testProtectsMethodContextsAndRebuiltContexts(): void
    {
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse(<<<'PHP'
            <?php
            class __TwigTemplate_Test {
                public function doDisplay(array $context): void {
                    $context += ['global' => true];
                    $other = [];
                }
                public function macro(): void {
                    $macro = function ($context): void {
                        $context = ['argument' => $context];
                    };
                }
            }
            PHP);
        self::assertNotNull($nodes);

        $traverser = new NodeTraverser(new PreserveContextVisitor());
        $nodes = $traverser->traverse($nodes);
        $finder = new NodeFinder();
        $calls = $finder->findInstanceOf($nodes, Node\Expr\FuncCall::class);

        self::assertCount(3, $calls);
        foreach ($calls as $call) {
            self::assertInstanceOf(Node\Name\FullyQualified::class, $call->name);
            self::assertSame('twigstan_preserve_context', $call->name->toString());
            self::assertInstanceOf(Node\Expr\Variable::class, $call->getArgs()[0]->value);
            self::assertSame('context', $call->getArgs()[0]->value->name);
        }

        $methods = $finder->findInstanceOf($nodes, Node\Stmt\ClassMethod::class);
        $statements = $methods[0]->stmts;
        self::assertNotNull($statements);
        self::assertArrayHasKey(0, $statements);
        self::assertInstanceOf(Node\Stmt\Expression::class, $statements[0]);
        self::assertInstanceOf(Node\Expr\Assign::class, $statements[0]->expr);
        self::assertInstanceOf(Node\Expr\FuncCall::class, $statements[0]->expr->expr);
    }

    public function testLeavesApplicationContextsUnchanged(): void
    {
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php class Application { public function render(array $context): void {} }');
        self::assertNotNull($nodes);
        $nodes = (new NodeTraverser(new PreserveContextVisitor()))->traverse($nodes);

        self::assertSame([], (new NodeFinder())->findInstanceOf($nodes, Node\Expr\FuncCall::class));
    }
}
