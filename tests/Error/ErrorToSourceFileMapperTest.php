<?php

declare(strict_types=1);

namespace TwigStan\Error;

use PHPUnit\Framework\TestCase;
use TwigStan\PHPStan\Analysis\Error;
use TwigStan\Processing\ScopeInjection\ScopeInjectionResult;
use TwigStan\Processing\ScopeInjection\ScopeInjectionResultCollection;
use TwigStan\Twig\SourceLocation;

final class ErrorToSourceFileMapperTest extends TestCase
{
    /**
     * Errors in code that Twig generates before the first template line, such as the properties of the template class,
     * belong to the template, so they can be ignored by path and baselined.
     *
     * @see https://github.com/twigstan/twigstan/issues/339
     */
    public function testErrorBeforeFirstTemplateLineBelongsToTemplate(): void
    {
        $mapping = new ScopeInjectionResultCollection(
            new ScopeInjectionResult('/templates/other.html.twig', '/compiled/other.php', [20 => new SourceLocation('/templates/other.html.twig', 1)]),
            new ScopeInjectionResult('/templates/page.html.twig', '/compiled/page.php', [20 => new SourceLocation('/templates/page.html.twig', 3)]),
        );

        [$property, $body] = (new ErrorToSourceFileMapper())->map($mapping, [
            new Error('Property $escaper is never read, only written.', '/compiled/page.php', 11, identifier: 'property.onlyWritten'),
            new Error('If condition is always false.', '/compiled/page.php', 21, identifier: 'if.alwaysFalse'),
        ]);

        self::assertSame('/templates/page.html.twig', $property->twigFile);
        self::assertNull($property->sourceLocation);
        self::assertTrue((new BaselineError('Property $escaper is never read, only written.', 'property.onlyWritten', '/templates/page.html.twig'))->shouldIgnore($property));

        self::assertSame('/templates/page.html.twig', $body->twigFile);
        self::assertEquals(new SourceLocation('/templates/page.html.twig', 3), $body->sourceLocation);
    }
}
