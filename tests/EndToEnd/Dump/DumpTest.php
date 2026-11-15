<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Dump;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Calls to dump() are reported, so debug output does not reach production.
 */
final class DumpTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
