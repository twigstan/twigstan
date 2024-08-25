<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigStan\Testing\ExpectErrorTokenParser;

$loader = new FilesystemLoader([], __DIR__);

$loader->addPath(__DIR__ . '/Rules/ExtendsRequirements', 'ExtendsRequirements');
$loader->addPath(__DIR__ . '/Rules/IncludeRequirements', 'IncludeRequirements');
$loader->addPath(__DIR__ . '/Rules/RenderRequirements', 'RenderRequirements');
$loader->addPath(__DIR__ . '/EndToEnd', 'EndToEnd');

$env = new Environment($loader);
$env->addTokenParser(new ExpectErrorTokenParser());

return $env;
