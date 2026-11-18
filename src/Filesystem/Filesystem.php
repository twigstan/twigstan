<?php

declare(strict_types=1);

namespace TwigStan\Filesystem;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class Filesystem extends SymfonyFilesystem
{
    /**
     * Returns the content of a file as a string.
     *
     * @throws IOException If the file cannot be read
     */
    public function readFile(string $filename): string
    {
        if (is_dir($filename)) {
            throw new IOException(\sprintf('Failed to read file "%s": File is a directory.', $filename));
        }

        $content = @file_get_contents($filename);

        if ($content === false) {
            throw new IOException(sprintf('Failed to read file "%s".', $filename), 0, null, $filename);
        }

        return $content;
    }
}
