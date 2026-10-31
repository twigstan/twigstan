<?php

declare(strict_types=1);

use Symfony\Component\Security\Core\User\InMemoryUser;
use TwigStan\Config\ConfigBuilder;

/** @var ConfigBuilder $config */
$config = require __DIR__ . '/twigstan.php';

return $config
    ->twigEnvironmentLoader(__DIR__ . '/EndToEnd/AppUser/kernel-loader.php')
    ->appUserClass(InMemoryUser::class);
