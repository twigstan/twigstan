<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GuardedVariableAccess;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class GuardedVariableAccessTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
