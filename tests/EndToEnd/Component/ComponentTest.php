<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Component;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Templates that embed Symfony UX Twig components.
 */
final class ComponentTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
