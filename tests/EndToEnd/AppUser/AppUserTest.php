<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AppUser;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Types app.user by the security of the routes that render the templates.
 */
final class AppUserTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-app-user.php';
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
