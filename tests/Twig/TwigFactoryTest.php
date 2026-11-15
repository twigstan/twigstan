<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Source;
use TwigStan\Twig\TokenParser\AssertTypeNode;
use TwigStan\Twig\TokenParser\AssertTypeTokenParser;

final class TwigFactoryTest extends TestCase
{
    public function testRegistersTokenParsersAndNodeVisitorsWhenTheLoaderAlreadyInitializedTheExtensions(): void
    {
        $visitor = new class implements NodeVisitorInterface {
            /**
             * @var list<class-string<Node>>
             */
            public array $visited = [];

            public function enterNode(Node $node, Environment $env): Node
            {
                $this->visited[] = $node::class;

                return $node;
            }

            public function leaveNode(Node $node, Environment $env): Node
            {
                return $node;
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        $twig = (new TwigFactory(
            [new AssertTypeTokenParser()],
            [$visitor],
            __DIR__ . '/TwigFactoryFixtures/warmed-twig-loader.php',
        ))->create();

        $twig->parse($twig->tokenize(new Source("{% assert_type 1 'int' %}", 'page.twig')));

        self::assertContains(AssertTypeNode::class, $visitor->visited);
    }
}
