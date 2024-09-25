<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Symfony\Component\Filesystem\Path;
use TwigStan\Error\BaselineError;
use TwigStan\Error\IgnoreError;

final readonly class SchemaFactory
{
    public function create(string $configurationFile): Structure
    {
        $basePath = Path::getDirectory($configurationFile);

        return Expect::structure([
            'includes' => Expect::listOf('string')->transform(
                fn(array $files) => array_map(
                    fn(string $path) => Path::makeAbsolute($path, $basePath),
                    $files,
                ),
            ),
            'services' => Expect::array(),
            'parameters' => Expect::structure([
                'reportUnmatchedIgnoredErrors' => Expect::anyOf(Expect::bool(), Expect::null()),
                'php' => Expect::structure([
                    'paths' => Expect::listOf('string')->transform(
                        fn(array $directories) => array_map(
                            fn(string $path) => Path::makeAbsolute($path, $basePath),
                            $directories,
                        ),
                    ),
                    'excludes' => Expect::listOf('string'),
                ])->skipDefaults()->castTo('array'),
                'twig' => Expect::structure([
                    'paths' => Expect::listOf('string')->transform(
                        fn(array $directories) => array_map(
                            fn(string $path) => Path::makeAbsolute($path, $basePath),
                            $directories,
                        ),
                    ),
                    'excludes' => Expect::listOf('string'),
                ])->skipDefaults()->castTo('array'),
                'phpstan' => Expect::structure([
                    'configurationFile' => Expect::string()->transform(
                        fn(string $path) => Path::makeAbsolute($path, $basePath),
                    ),
                    'memoryLimit' => Expect::anyOf(
                        Expect::string(),
                        Expect::bool()->assert(fn($value) => $value === false, 'Only false is accepted')->transform(fn() => '-1'),
                        Expect::null(),
                    ),
                ])->skipDefaults()->castTo('array'),
                'tempDir' => Expect::string()->transform(
                    fn(string $path) => Path::makeAbsolute($path, $basePath),
                ),
                'environmentLoader' => Expect::string()->transform(
                    fn(string $path) => Path::makeAbsolute($path, $basePath),
                ),
                'ignoreErrors' => Expect::listOf(Expect::structure([
                    'message' => Expect::anyOf(Expect::string(), Expect::null()),
                    'identifier' => Expect::anyOf(Expect::string(), Expect::null()),
                    'path' => Expect::anyOf(
                        Expect::string()->transform(
                            fn(string $path) => str_contains($path, '*') ? $path : Path::makeAbsolute($path, $basePath),
                        ),
                        Expect::null(),
                    ),
                ])->castTo(IgnoreError::class)),
                'baselineErrors' => Expect::listOf(Expect::structure([
                    'message' => Expect::string(),
                    'identifier' => Expect::anyOf(Expect::string(), Expect::null()),
                    'path' => Expect::string(),
                    'count' => Expect::int(),
                ])->castTo(BaselineError::class)),
            ])->skipDefaults()->castTo('array'),
        ])->skipDefaults()->castTo('array');
    }
}
