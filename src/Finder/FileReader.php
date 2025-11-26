<?php

declare(strict_types=1);

namespace TwigStan\Finder;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * File reading utility to bypass missing methods in certain Filesystem implementations for older Symfony versions.
 * Filesystem::readFile was introduced in Symfony 7.1.
 * https://symfony.com/doc/current/components/filesystem.html#readfile
 */
final class FileReader
{
    public static function readFile(Filesystem $filesystem, string $path): string
    {
        // @phpstan-ignore function.alreadyNarrowedType
        if (method_exists($filesystem, 'readFile')) {
            return $filesystem->readFile($path);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read file: %s', $path));
        }

        return $content;
    }
}
