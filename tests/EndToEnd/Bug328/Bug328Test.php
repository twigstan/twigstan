<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug328;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Keeps the globals and the variables set in a parent block in its nested blocks, when an intermediate template calls parent().
 */
final class Bug328Test extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
