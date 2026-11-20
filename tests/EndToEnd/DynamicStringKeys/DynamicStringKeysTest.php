<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\DynamicStringKeys;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class DynamicStringKeysTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
