<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Types;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class TypesTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
