<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Override;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class OverrideTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
