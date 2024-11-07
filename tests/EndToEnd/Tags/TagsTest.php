<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Tags;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class TagsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runTests(__DIR__);
    }
}
