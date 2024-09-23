<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigStan\EndToEnd\TemplateDataExtension;

$loader = new FilesystemLoader(rootPath: __DIR__);
$loader->addPath(__DIR__);
$loader->addPath(__DIR__ . '/EndToEnd', 'EndToEnd');

$twig = new Environment($loader);

$twig->addExtension(new TemplateDataExtension());

return $twig;
