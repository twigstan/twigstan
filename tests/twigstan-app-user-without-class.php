<?php

declare(strict_types=1);

use TwigStan\Config\ConfigBuilder;

/** @var ConfigBuilder $config */
$config = require __DIR__ . '/twigstan.php';

return $config
    ->twigEnvironmentLoader(__DIR__ . '/EndToEnd/AppUser/kernel-loader.php');
