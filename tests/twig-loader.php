<?php

declare(strict_types=1);

use Symfony\Bridge\Twig\AppVariable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(rootPath: __DIR__);
$loader->addPath(__DIR__);
$loader->addPath(__DIR__ . '/EndToEnd', 'EndToEnd');

$environment = new Environment($loader);
$environment->addGlobal('app', new AppVariable());

return $environment;
