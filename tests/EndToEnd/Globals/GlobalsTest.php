<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Globals;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class GlobalsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
