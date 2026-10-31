<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\EmbedParentOutsidePaths;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Embeds a template that is not in the analysed paths, such as one of a bundle.
 */
final class EmbedParentOutsidePathsTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-embed-parent-outside-paths.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
