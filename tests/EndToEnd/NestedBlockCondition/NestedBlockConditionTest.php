<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\NestedBlockCondition;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class NestedBlockConditionTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
