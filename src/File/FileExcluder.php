<?php

declare(strict_types=1);

namespace TwigStan\File;

use Symfony\Component\Filesystem\Path;

use function fnmatch;
use function in_array;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
use const FNM_CASEFOLD;
use const FNM_NOESCAPE;

final class FileExcluder
{
    /**
     * Paths to exclude from analysing
     *
     * @var string[]
     */
    private array $literalAnalyseExcludes = [];

    /**
     * fnmatch() patterns to use for excluding files and directories from analysing
     * @var string[]
     */
    private array $fnmatchAnalyseExcludes = [];

    private int $fnmatchFlags;

    /**
     * @param string[] $analyseExcludes
     */
    public function __construct(
        private readonly string $workingDirectory,
        array $analyseExcludes,
    ) {
        foreach ($analyseExcludes as $exclude) {
            $len = strlen($exclude);
            $trailingDirSeparator = ($len > 0 && in_array($exclude[$len - 1], ['\\', '/'], true));

            $normalized = Path::normalize($exclude);

            if ($trailingDirSeparator) {
                $normalized .= DIRECTORY_SEPARATOR;
            }

            if (self::isFnmatchPattern($normalized)) {
                $this->fnmatchAnalyseExcludes[] = $normalized;
            } else {
                $this->literalAnalyseExcludes[] = Path::makeAbsolute($normalized, $this->workingDirectory);
            }
        }

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        if ($isWindows) {
            $this->fnmatchFlags = FNM_NOESCAPE | FNM_CASEFOLD;
        } else {
            $this->fnmatchFlags = 0;
        }
    }

    public function isExcludedFromAnalysing(string $file): bool
    {
        foreach ($this->literalAnalyseExcludes as $exclude) {
            if (str_starts_with($file, $exclude)) {
                return true;
            }
        }
        foreach ($this->fnmatchAnalyseExcludes as $exclude) {
            if (fnmatch($exclude, $file, $this->fnmatchFlags)) {
                return true;
            }
        }

        return false;
    }

    public static function isAbsolutePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '/') {
            if (str_starts_with($path, '/')) {
                return true;
            }
        } elseif (substr($path, 1, 1) === ':') {
            return true;
        }

        return false;
    }

    public static function isFnmatchPattern(string $path): bool
    {
        return preg_match('~[*?[\]]~', $path) > 0;
    }

}