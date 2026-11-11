<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\BlockContextAfterCondition;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class BlockContextAfterConditionTest extends AbstractEndToEndTestCase
{
    public function testAssignedVariableSurvivesUnrelatedCondition(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
