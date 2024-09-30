<?php

declare(strict_types=1);

namespace TwigStan\Config;

use TwigStan\Error\BaselineError;
use TwigStan\Error\IgnoreError;

final readonly class TwigStanConfig
{
    /**
     * @param list<string> $twigPaths
     * @param list<string> $twigExcludes
     * @param list<string> $phpPaths
     * @param list<string> $phpExcludes
     * @param list<IgnoreError> $ignoreErrors
     * @param list<BaselineError> $baselineErrors
     */
    public function __construct(
        public ?string $tempDirectory,
        public ?string $baselineFile,
        public bool $reportUnmatchedIgnoredErrors,
        public string $phpstanConfigurationFile,
        public null | false | string $phpstanMemoryLimit,
        public string $twigEnvironmentLoader,
        public array $twigPaths,
        public array $twigExcludes,
        public array $phpPaths,
        public array $phpExcludes,
        public array $ignoreErrors,
        public array $baselineErrors,
    ) {}

    public static function configure(): ConfigBuilder
    {
        return new ConfigBuilder();
    }

    public static function extend(string $configurationFile): ConfigBuilder
    {
        return ConfigBuilder::extend($configurationFile);
    }
}
