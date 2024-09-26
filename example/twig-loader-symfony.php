<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use App\Kernel;

require __DIR__ . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
$kernel->boot();

// echo "Make sure that the `twig` service is public in your Symfony application
// by adding a compiler pass to your Kernel:
//
// protected function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container) : void
// {
//     $container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
//         public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container) : void
//         {
//             $container->getDefinition('twig')->setPublic(true);
//         }
//     });
// }

return $kernel->getContainer()->get('twig');
