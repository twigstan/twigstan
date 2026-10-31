<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\BlockContext;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class BlockContextTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
