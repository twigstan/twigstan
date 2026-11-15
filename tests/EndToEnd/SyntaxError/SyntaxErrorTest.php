<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\SyntaxError;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Templates that Twig cannot parse are reported instead of stopping the analysis.
 */
final class SyntaxErrorTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
