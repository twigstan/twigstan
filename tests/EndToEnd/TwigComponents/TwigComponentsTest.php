<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\TwigComponents;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class TwigComponentsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
