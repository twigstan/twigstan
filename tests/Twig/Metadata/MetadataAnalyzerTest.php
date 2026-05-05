<?php

declare(strict_types=1);

namespace TwigStan\Twig\Metadata;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Ternary\ConditionalTernary;
use Twig\Node\Expression\Variable\ContextVariable;

final class MetadataAnalyzerTest extends TestCase
{
    public function testItExtractsTemplateNamesFromConditionalTernary(): void
    {
        $node = new ConditionalTernary(
            new ContextVariable('is_mobile', 1),
            new ConstantExpression('mobile.twig', 1),
            new ConstantExpression('desktop.twig', 1),
            1,
        );

        $reflection = new ReflectionClass(MetadataAnalyzer::class);
        $method = $reflection->getMethod('getStringsFromExpression');

        self::assertSame(
            ['mobile.twig', 'desktop.twig'],
            $method->invoke($reflection->newInstanceWithoutConstructor(), $node),
        );
    }
}
