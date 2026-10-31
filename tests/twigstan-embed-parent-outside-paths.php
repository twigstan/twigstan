<?php

declare(strict_types=1);

use TwigStan\Config\ConfigBuilder;

/** @var ConfigBuilder $config */
$config = require __DIR__ . '/twigstan.php';

return $config
    ->phpPaths(__DIR__ . '/EndToEnd/EmbedParentOutsidePaths')
    ->twigPaths(__DIR__ . '/EndToEnd/EmbedParentOutsidePaths');
