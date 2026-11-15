<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\ArrayLoader;

// Symfony's TemplateCacheWarmer compiles the templates when a kernel with a separate build directory boots,
// which initializes the extensions before TwigStan registers its token parsers and node visitors.
$twig = new Environment(new ArrayLoader(['warm.twig' => '{{ 1 + 1 }}']));
$twig->load('warm.twig');

return $twig;
