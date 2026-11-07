<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AppUserWithoutClass;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Types app.user by the security of the routes that render the templates, when no user class is configured.
 */
final class AppUserWithoutClassTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-app-user-without-class.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }

    protected function tearDown(): void
    {
        // Booting the kernel registers Symfony's ErrorHandler, see KernelTestCase::ensureKernelShutdown()
        restore_exception_handler();

        parent::tearDown();
    }
}
