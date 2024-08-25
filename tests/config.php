<?php

return [
    'includes' => [
        '../config/phpstan.neon',
    ],
    'parameters' => [
        'twigstan' => [
            // We need this PHP configuration file because we have to use an absolute path.
            // There is currently no easy way for an extension to register something as a "path" parameter type.
            // The path should be relative to the config file it's referenced. Then made absolute.
            // It seems that PHPStan has a hardcoded list of these "path" parameter types:
            // https://github.com/phpstan/phpstan-src/blob/3175c81f26fd5bcb4a161b24e774921870ed2533/src/DependencyInjection/NeonAdapter.php#L122-L146
            'environmentLoader' => __DIR__ . '/twig-loader.php',
            'analysisResultJsonFile' => null,
        ],
    ],
];
