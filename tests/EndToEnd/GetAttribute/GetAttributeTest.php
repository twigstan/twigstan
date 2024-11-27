<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class GetAttributeTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
