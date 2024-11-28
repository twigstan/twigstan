<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute;

use PHPUnit\Framework\Attributes\Test;
use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class GetAttributeTest extends AbstractEndToEndTestCase
{
    #[Test]
    public function analyze(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
