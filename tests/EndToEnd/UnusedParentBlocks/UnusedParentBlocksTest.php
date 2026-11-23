<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\UnusedParentBlocks;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class UnusedParentBlocksTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
