<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Symfony\Component\Filesystem\Path;
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
                'php' => Expect::structure([
                    'paths' => Expect::listOf('string')->transform(
                        fn(array $directories) => array_map(
                            fn(string $path) => Path::makeAbsolute($path, $basePath),
                            $directories,
                        ),
                    ),
                    'excludes' => Expect::listOf('string'),
                ])->castTo('array'),
                'twig' => Expect::structure([
                    'paths' => Expect::listOf('string')->transform(
                        fn(array $directories) => array_map(
                            fn(string $path) => Path::makeAbsolute($path, $basePath),
                            $directories,
                        ),
                    ),
                    'excludes' => Expect::listOf('string'),
                ])->castTo('array'),
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
            ])->castTo('array'),
        ])->castTo('array');
    }
}
