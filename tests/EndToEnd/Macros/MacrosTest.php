<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Macros;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class MacrosTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
