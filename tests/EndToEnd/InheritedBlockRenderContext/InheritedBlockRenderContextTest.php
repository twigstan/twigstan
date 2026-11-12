<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\InheritedBlockRenderContext;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class InheritedBlockRenderContextTest extends AbstractEndToEndTestCase
{
    public function testChildKeepsItsContextWhenParentIsRenderedSeparately(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
