<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug154;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class Bug154Test extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
