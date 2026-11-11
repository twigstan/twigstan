<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug307;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Keeps the types of the context after nested loops over large array shapes, with PHPStan's default polluteScopeWithAlwaysIterableForeach.
 */
final class Bug307Test extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-pollute-scope.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
