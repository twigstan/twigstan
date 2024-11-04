<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Functions;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class FunctionsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
