<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug317;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class Bug317Test extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
