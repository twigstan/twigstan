<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug152;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class Bug152Test extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
