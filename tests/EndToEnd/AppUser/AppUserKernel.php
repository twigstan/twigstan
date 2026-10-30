<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AppUser;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class AppUserKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/tests/.twigstan/app-user-kernel/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/tests/.twigstan/app-user-kernel/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('twig', [
            'default_path' => '%kernel.project_dir%/tests',
            'paths' => ['%kernel.project_dir%/tests/EndToEnd' => 'EndToEnd'],
        ]);

        $container->extension('security', [
            'providers' => ['users' => ['memory' => ['users' => ['jane' => ['password' => 'secret', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users', 'http_basic' => null]],
            'access_control' => [
                ['path' => '^/public', 'roles' => 'PUBLIC_ACCESS'],
                // A voter decides, which does not imply that there is a user.
                ['path' => '^/reports', 'roles' => 'CAN_SEE_REPORTS'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/AppUserController.php', 'attribute');
    }
}
