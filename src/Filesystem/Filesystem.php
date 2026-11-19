<?php

declare(strict_types=1);

namespace TwigStan\Filesystem;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Wrapper around Symfony Filesystem to provide additional functionality.
 */
final class Filesystem extends SymfonyFilesystem
{
    /**
     * Returns the content of a file as a string.
     *
     * @throws IOException If the file cannot be read
     */
    public function readFile(string $filename): string
    {
        // @phpstan-ignore function.alreadyNarrowedType
        if (\method_exists(SymfonyFilesystem::class, 'readFile')) {
            return parent::readFile($filename);
        }

        if ( ! \function_exists('file_get_contents')) {
            throw new IOException('Unable to perform filesystem operation because the "file_get_contents()" function has been disabled.');
        }

        if (is_dir($filename)) {
            throw new IOException(\sprintf('Failed to read file "%s": File is a directory.', $filename));
        }

        // @phpstan-ignore argument.type
        set_error_handler(self::handleError(...));

        try {
            $content = @file_get_contents($filename);
        } finally {
            restore_error_handler();
        }

        if ($content === false) {
            throw new IOException(sprintf('Failed to read file "%s".', $filename), 0, null, $filename);
        }

        return $content;
    }
}
