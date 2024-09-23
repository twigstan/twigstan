<?php

namespace TwigStan\EndToEnd\Error;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class IgnoreErrorTest extends AbstractEndToEndTestCase
{
    public function testIgnoreError(): void
    {
        parent::runTests(__DIR__);
    }

    public function testIgnoreErrorOnlyMessage(): void
    {
        parent::runTests(__DIR__);
    }
}
