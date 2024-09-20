<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bugs;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class BugsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
