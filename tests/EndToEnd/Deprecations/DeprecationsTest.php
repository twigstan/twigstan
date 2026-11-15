<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Deprecations;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Deprecations that Twig triggers while compiling a template are reported.
 */
final class DeprecationsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
