<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AbstractTemplate;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class AbstractTemplateTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
