<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

// Returning the kernel instead of the Twig environment lets TwigStan read the routes and their security.
return $kernel;
