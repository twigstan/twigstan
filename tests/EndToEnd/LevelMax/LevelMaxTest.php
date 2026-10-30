<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LevelMax;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Analyzes the templates with PHPStan at level max, which reports mixed types.
 */
final class LevelMaxTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-max.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
