<?php

declare(strict_types=1);

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\UX\TwigComponent\Twig\ComponentExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(rootPath: __DIR__);
$loader->addPath(__DIR__);
$loader->addPath(__DIR__ . '/EndToEnd', 'EndToEnd');

$environment = new Environment($loader);
$environment->addGlobal('app', new AppVariable());
$environment->addExtension(new FormExtension());
// Registered by the Symfony UX Twig component bundle in applications.
$environment->addExtension(new ComponentExtension()); // @phpstan-ignore new.internalClass

return $environment;
