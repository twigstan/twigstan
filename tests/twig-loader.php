<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader([], __DIR__);

$loader->addPath(__DIR__ . '/EndToEnd'); // __main__
$loader->addPath(__DIR__ . '/EndToEnd', 'EndToEnd');

return new Environment($loader);
