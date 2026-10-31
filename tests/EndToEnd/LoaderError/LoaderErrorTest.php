<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LoaderError;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Templates that Twig cannot load are reported instead of stopping the analysis.
 */
final class LoaderErrorTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
