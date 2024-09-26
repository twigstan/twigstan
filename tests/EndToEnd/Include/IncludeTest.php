<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Include;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class IncludeTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
