<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Symfony\Component\Filesystem\Path;

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
                'twigstan' => Expect::structure([
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
                ])->castTo('array'),
            ])->castTo('array'),
        ])->castTo('array');
    }
}
