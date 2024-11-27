<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class RenderPointsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
