<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Nette\Neon\Neon;

if ($_SERVER['PHAR_CHECKSUM'] ?? false) {
    $prefix = 'TwigStanPrefixChecksum';
} else {
    exec('git rev-parse --short HEAD', $gitCommitOutputLines, $gitExitCode);

    if ($gitExitCode !== 0) {
        exit('Could not get Git commit');
    }

    $prefix = sprintf('TwigStanPrefix%s', $gitCommitOutputLines[0]);
}

return [
    'prefix' => $prefix,
    'exclude-namespaces' => [
        'TwigStan',
        'Twig',
        'PHPStan',
        'PhpParser',
    ],
    'expose-global-functions' => false,
    'expose-global-classes' => false,
    'patchers' => [
        function (string $filePath, string $prefix, string $content): string {
            if ($filePath !== 'vendor/nette/di/src/DI/Compiler.php') {
                return $content;
            }

            return str_replace('|Nette\\\\DI\\\\Statement', sprintf('|\\\\%s\\\\Nette\\\\DI\\\\Statement', $prefix), $content);
        },
        function (string $filePath, string $prefix, string $content): string {
            if ($filePath !== 'vendor/nette/di/src/DI/Extensions/DefinitionSchema.php') {
                return $content;
            }

            $content = str_replace(
                sprintf('\'%s\\\\callable', $prefix),
                '\'callable',
                $content,
            );
            $content = str_replace(
                '|Nette\\\\DI\\\\Definitions\\\\Statement',
                sprintf('|%s\\\\Nette\\\\DI\\\\Definitions\\\\Statement', $prefix),
                $content,
            );

            return $content;
        },
        function (string $filePath, string $prefix, string $content): string {
            if ($filePath !== 'vendor/nette/di/src/DI/Extensions/ExtensionsExtension.php') {
                return $content;
            }

            $content = str_replace(
                sprintf('\'%s\\\\string', $prefix),
                '\'string',
                $content,
            );
            $content = str_replace(
                '|Nette\\\\DI\\\\Definitions\\\\Statement',
                sprintf('|%s\\\\Nette\\\\DI\\\\Definitions\\\\Statement', $prefix),
                $content,
            );

            return $content;
        },
        function (string $filePath, string $prefix, string $content): string {
            if ( ! str_ends_with($filePath, '.neon')) {
                return $content;
            }

            if ($content === '') {
                return $content;
            }

            $prefixClass = function (string $class) use ($prefix): string {
                if (preg_match('/^@?(TwigStan|Twig|PHPStan)\\\/', $class) === 1) {
                    return $class;
                }

                if (str_starts_with($class, '@')) {
                    return '@' . $prefix . '\\' . substr($class, 1);
                }

                return $prefix . '\\' . $class;
            };

            $neon = Neon::decode($content);

            if ( ! array_key_exists('services', $neon)) {
                return $content;
            }

            $updatedNeon = $neon;
            foreach ($neon['services'] ?? [] as $key => $service) {
                if (array_key_exists('class', $service) && is_string($service['class'])) {
                    $service['class'] = $prefixClass($service['class']);
                }

                if (array_key_exists('factory', $service) && is_string($service['factory'])) {
                    $service['factory'] = $prefixClass($service['factory']);
                }

                if (array_key_exists('autowired', $service) && is_array($service['autowired'])) {
                    foreach ($service['autowired'] as $i => $autowiredName) {
                        $service['autowired'][$i] = $prefixClass($autowiredName);
                    }
                }

                $updatedNeon['services'][$key] = $service;
            }

            return Neon::encode($updatedNeon, true);
        },
    ],
];
