<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Inheritance;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class InheritanceTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
