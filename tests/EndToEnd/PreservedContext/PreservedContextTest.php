<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\PreservedContext;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class PreservedContextTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
