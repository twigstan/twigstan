<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Keeps the modification times of generated files that did not change. PHPStan rebuilds its container when the
 * modification time of a configuration file changes, and re-hashes the analysed files whose modification time changed.
 */
final readonly class GeneratedFiles
{
    public function __construct(
        private Filesystem $filesystem,
    ) {}

    public function write(string $file, string $content): void
    {
        if (is_file($file) && $this->filesystem->readFile($file) === $content) {
            return;
        }

        $this->filesystem->dumpFile($file, $content);
    }

    /**
     * Removes the files of an earlier analysis that this analysis did not generate.
     *
     * @param list<string> $files
     */
    public function removeAllExcept(string $directory, array $files): void
    {
        foreach (Finder::create()->files()->in($directory) as $file) {
            if ( ! in_array($file->getPathname(), $files, true)) {
                $this->filesystem->remove($file->getPathname());
            }
        }
    }
}
