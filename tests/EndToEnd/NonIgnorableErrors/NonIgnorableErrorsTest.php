<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\NonIgnorableErrors;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Errors that cannot be ignored are reported even when ignoreErrors matches them, such as the ignore of templates starting with an underscore in tests/twigstan.php.
 */
final class NonIgnorableErrorsTest extends AbstractEndToEndTestCase
{
    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }
}
