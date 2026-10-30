<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\OnlyWithContext;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Only reports errors in templates that are rendered with a known context.
 */
final class OnlyWithContextTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-with-context.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
