<?php

declare(strict_types=1);

namespace TwigStan\Config;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use TwigStan\Error\BaselineError;
use TwigStan\Error\IgnoreError;

final class ConfigBuilder
{
    private ?string $tempDirectory = null;
    private ?string $baselineFile = null;
    private bool $reportUnmatchedIgnoredErrors = true;
    private ?string $phpstanConfigurationFile = null;
    private null | false | string $phpstanMemoryLimit = null;
    private ?string $twigEnvironmentLoader = null;

    /**
     * @var list<string>
     */
    private array $twigPaths = [];

    /**
     * @var list<string>
     */
    private array $twigExcludes = [];

    /**
     * @var list<string>
     */
    private array $phpPaths = [];

    /**
     * @var list<string>
     */
    private array $phpExcludes = [];

    /**
     * @var list<IgnoreError>
     */
    private array $ignoreErrors;

    /**
     * @var list<BaselineError>
     */
    private array $baselineErrors = [];

    public function __construct()
    {
        $this->ignoreErrors = [
            IgnoreError::identifier('isset.variable'),

            // It's perfectly fine to do `a == b ? 'yes' : 'no'` in Twig.
            IgnoreError::identifier('equal.notAllowed'),

            // It's perfectly fine to do `a != b ? 'no' : 'yes'` in Twig.
            IgnoreError::identifier('notEqual.notAllowed'),

            // The context is backed up before a loop and restored after it.
            // Therefore this is a non-issue in Twig templates.
            IgnoreError::identifier('foreach.valueOverwrite'),

            // We cannot guarantee that a short arrow closure uses the macros variable.
            IgnoreError::messageAndIdentifier('#Anonymous function has an unused use \$macros\.#', 'closure.unusedUse'),

            // When the variable that is passed does not exist, this produces an error.
            IgnoreError::messageAndIdentifier('#CoreExtension::ensureTraversable#', 'argument.templateType'),
        ];
    }

    public static function extend(string $configurationFile): self
    {
        if ( ! file_exists($configurationFile)) {
            throw new InvalidArgumentException(sprintf('The configuration file "%s" does not exist.', $configurationFile));
        }

        if ( ! Path::isAbsolute($configurationFile)) {
            throw new InvalidArgumentException(sprintf('The configuration file "%s" must be an absolute path.', $configurationFile));
        }

        $configuration = include $configurationFile;

        if ( ! $configuration instanceof ConfigBuilder) {
            throw new RuntimeException(sprintf('Configuration is not an instance of %s', self::class));
        }

        return $configuration;
    }

    public function create(): TwigStanConfig
    {
        if ($this->phpstanConfigurationFile === null) {
            throw new InvalidArgumentException(sprintf('The "phpstanConfigurationFile" option is required.'));
        }

        if ($this->twigEnvironmentLoader === null) {
            throw new InvalidArgumentException(sprintf('The "twigEnvironmentLoader" option is required.'));
        }

        return new TwigStanConfig(
            $this->tempDirectory,
            $this->baselineFile,
            $this->reportUnmatchedIgnoredErrors,
            $this->phpstanConfigurationFile,
            $this->phpstanMemoryLimit,
            $this->twigEnvironmentLoader,
            $this->twigPaths,
            $this->twigExcludes,
            $this->phpPaths,
            $this->phpExcludes,
            $this->ignoreErrors,
            $this->baselineErrors,
        );
    }

    /**
     * @return $this
     */
    public function tempDirectory(string $tempDirectory): self
    {
        $this->tempDirectory = $tempDirectory;

        return $this;
    }

    /**
     * @return $this
     */
    public function baselineFile(string $baselineFile): self
    {
        if ( ! file_exists($baselineFile)) {
            throw new InvalidArgumentException(sprintf('The baseline file "%s" does not exist.', $baselineFile));
        }

        if ( ! Path::isAbsolute($baselineFile)) {
            throw new InvalidArgumentException(sprintf('The baseline file "%s" must be an absolute path.', $baselineFile));
        }

        if (Path::getExtension($baselineFile) !== 'php') {
            throw new InvalidArgumentException(sprintf('The baseline file "%s" must be a PHP file.', $baselineFile));
        }

        $baselineErrors = include $baselineFile;

        if ( ! array_is_list($baselineErrors)) {
            throw new InvalidArgumentException(sprintf('The baseline file "%s" must return an array.', $baselineFile));
        }

        foreach ($baselineErrors as $baselineError) {
            if ( ! $baselineError instanceof BaselineError) {
                throw new InvalidArgumentException(sprintf('The baseline file "%s" must return a list of %s.', $baselineFile, BaselineError::class));
            }
        }

        $this->baselineFile = $baselineFile;
        $this->baselineErrors = $baselineErrors;

        return $this;
    }

    /**
     * @return $this
     */
    public function reportUnmatchedIgnoredErrors(bool $reportUnmatchedIgnoredErrors): self
    {
        $this->reportUnmatchedIgnoredErrors = $reportUnmatchedIgnoredErrors;

        return $this;
    }

    /**
     * Path to PHPStan configuration file.
     *
     * @return $this
     */
    public function phpstanConfigurationFile(string $phpstanConfigurationFile): self
    {
        if ( ! file_exists($phpstanConfigurationFile)) {
            throw new InvalidArgumentException(sprintf('The PHPStan configuration file "%s" does not exist.', $phpstanConfigurationFile));
        }

        if ( ! Path::isAbsolute($phpstanConfigurationFile)) {
            throw new InvalidArgumentException(sprintf('The PHPStan configuration file "%s" must be an absolute path.', $phpstanConfigurationFile));
        }

        $this->phpstanConfigurationFile = $phpstanConfigurationFile;

        return $this;
    }

    /**
     * Memory limit for when running PHPStan.
     * Should be a value accepted by PHP (e.g. 512M).
     * Use false for no limit.
     *
     * @return $this
     */
    public function phpstanMemoryLimit(false | string $phpstanMemoryLimit): self
    {
        $this->phpstanMemoryLimit = $phpstanMemoryLimit;

        return $this;
    }

    /**
     * TwigStan needs access to your Twig environment to analyze your templates.
     * Specify the file that returns Twig\Environment.
     *
     * @return $this
     */
    public function twigEnvironmentLoader(string $twigEnvironmentLoader): self
    {
        if ( ! file_exists($twigEnvironmentLoader)) {
            throw new InvalidArgumentException(sprintf('The Twig environment loader file "%s" does not exist.', $twigEnvironmentLoader));
        }

        if ( ! Path::isAbsolute($twigEnvironmentLoader)) {
            throw new InvalidArgumentException(sprintf('The Twig environment loader file "%s" must be an absolute path.', $twigEnvironmentLoader));
        }

        $this->twigEnvironmentLoader = $twigEnvironmentLoader;

        return $this;
    }

    /**
     * Used to scan for Twig templates.
     *
     * @return $this
     */
    public function twigPaths(string ...$paths): self
    {
        $this->twigPaths = [
            ...$this->twigPaths,
            ...array_values(
                array_map(
                    function (string $path) {
                        if ( ! file_exists($path)) {
                            throw new InvalidArgumentException(sprintf('The path "%s" does not exist.', $path));
                        }

                        if ( ! Path::isAbsolute($path)) {
                            throw new InvalidArgumentException(sprintf('The path "%s" must be an absolute path.', $path));
                        }

                        return $path;
                    },
                    $paths,
                ),
            ),
        ];

        return $this;
    }

    /**
     * If you want to exclude certain directories/files from scanning, you can define them below.
     * You can use * as a wildcard.
     *
     * @return $this
     */
    public function twigExcludes(string ...$excludes): self
    {
        $this->twigExcludes = [
            ...$this->twigExcludes,
            ...array_values($excludes),
        ];

        return $this;
    }

    /**
     * Used to scan for PHP controllers that render Twig templates.
     *
     * @return $this
     */
    public function phpPaths(string ...$paths): self
    {
        $this->phpPaths = [
            ...$this->phpPaths,
            ...array_values(
                array_map(
                    function (string $path) {
                        if ( ! file_exists($path)) {
                            throw new InvalidArgumentException(sprintf('The path "%s" does not exist.', $path));
                        }

                        if ( ! Path::isAbsolute($path)) {
                            throw new InvalidArgumentException(sprintf('The path "%s" must be an absolute path.', $path));
                        }

                        return $path;
                    },
                    $paths,
                ),
            ),
        ];

        return $this;
    }

    /**
     * If you want to exclude certain directories/files from scanning, you can define them below.
     * You can use * as a wildcard.
     *
     * @return $this
     */
    public function phpExcludes(string ...$excludes): self
    {
        $this->phpExcludes = [
            ...$this->phpExcludes,
            ...array_values($excludes),
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreErrors(IgnoreError ...$ignoreErrors): self
    {
        $this->ignoreErrors = [
            ...$this->ignoreErrors,
            ...array_values($ignoreErrors),
        ];

        return $this;
    }
}
