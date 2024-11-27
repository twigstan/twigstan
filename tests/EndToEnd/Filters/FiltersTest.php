<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Filters;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class FiltersTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
