<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RecursiveInclude;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class RecursiveIncludeTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
