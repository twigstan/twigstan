<?php

declare(strict_types=1);

namespace TwigStan\Rules;

use PHPStan\File\FileReader;
use Symfony\Component\Finder\Finder;

final class DataProviderHelper
{
    public static function createCasesFromDirectory(string $directory, string $pattern): iterable
    {
        $finder = Finder::create()
            ->files()
            ->sortByName(true)
            ->in($directory)
            ->name($pattern);

        foreach ($finder as $file) {
            $errorsFile = str_replace(sprintf(".%s", $file->getExtension()), '.errors', $file->getPathname());
            $expectedErrors = [];
            if (file_exists($errorsFile)) {
                $expectedErrors = explode("\n", trim(FileReader::read($errorsFile)));
                $expectedErrors = array_map(function (string $error) {
                    [$line, $message] = explode(': ', $error, 2);
                    return [$message, (int) $line];
                }, $expectedErrors);
            }
            yield $file->getRelativePathname() => [$file->getPathname(), $expectedErrors];
        }
    }
}
