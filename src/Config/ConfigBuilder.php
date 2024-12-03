<?php

declare(strict_types=1);

namespace TwigStan\Config;

use InvalidArgumentException;
use PhpParser\Node;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use TwigStan\Error\BaselineError;
use TwigStan\Error\IgnoreError;
use TwigStan\PHPStan\Collector\TemplateContextCollector;

final class ConfigBuilder
{
    private string $projectRootDirectory;
    private ?string $tempDirectory = null;
    private ?string $baselineFile = null;
    private bool $reportUnmatchedIgnoredErrors = true;
    private bool $onlyAnalyzeTemplatesWithContext = false;
    private ?string $phpstanBinPath = null;
    private ?string $phpstanConfigurationFile = null;
    private null | false | string $phpstanMemoryLimit = null;
    private ?string $twigEnvironmentLoader = null;
    private ?string $editorUrl = null;

    /**
     * @var list<class-string<TemplateContextCollector<Node>>>
     */
    private array $twigContextCollectors = [];

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
    private array $twigExtensions = ['twig'];

    /**
     * @var list<string>
     */
    private array $phpPaths = [];

    /**
     * @var list<string>
     */
    private array $phpExcludes = [];

    /**
     * @var list<string>
     */
    private array $phpExtensions = ['php'];

    /**
     * @var list<IgnoreError>
     */
    private array $ignoreErrors = [];

    /**
     * @var list<BaselineError>
     */
    private array $baselineErrors = [];

    public function __construct(string $projectRootDirectory)
    {
        if ( ! file_exists($projectRootDirectory)) {
            throw new InvalidArgumentException(sprintf('The project root directory path "%s" does not exist.', $projectRootDirectory));
        }

        if ( ! Path::isAbsolute($projectRootDirectory)) {
            throw new InvalidArgumentException(sprintf('The project root directory path "%s" must be an absolute path.', $projectRootDirectory));
        }

        $this->projectRootDirectory = $projectRootDirectory;
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
            throw new RuntimeException(sprintf('Configuration is not an instance of %s.', self::class));
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

        if ($this->phpstanBinPath === null) {
            $phpstanBinPath = Path::join($this->projectRootDirectory, 'vendor', 'bin', 'phpstan');

            if ( ! file_exists($phpstanBinPath)) {
                throw new InvalidArgumentException(sprintf('The "phpstanBinPath" option is required.'));
            }

            $this->phpstanBinPath($phpstanBinPath);
        }

        if ($this->tempDirectory === null) {
            $this->tempDirectory(Path::join($this->projectRootDirectory, '.twigstan'));
        }

        return new TwigStanConfig(
            $this->projectRootDirectory,
            $this->tempDirectory,
            $this->baselineFile,
            $this->reportUnmatchedIgnoredErrors,
            $this->onlyAnalyzeTemplatesWithContext,
            $this->phpstanBinPath,
            $this->phpstanConfigurationFile,
            $this->phpstanMemoryLimit,
            $this->twigEnvironmentLoader,
            $this->twigPaths,
            $this->twigExcludes,
            array_values(array_unique($this->twigExtensions)),
            $this->phpPaths,
            $this->phpExcludes,
            array_values(array_unique($this->phpExtensions)),
            $this->ignoreErrors,
            $this->baselineErrors,
            $this->twigContextCollectors,
            $this->editorUrl,
        );
    }

    public function projectRootDirectory(string $projectRootDirectory): self
    {
        if ( ! Path::isAbsolute($projectRootDirectory)) {
            throw new InvalidArgumentException(sprintf('The project root directory path "%s" must be an absolute path.', $projectRootDirectory));
        }

        $this->projectRootDirectory = $projectRootDirectory;

        return $this;
    }

    /**
     * @phpstan-assert !null $this->tempDirectory
     */
    public function tempDirectory(string $tempDirectory): self
    {
        if ( ! Path::isAbsolute($tempDirectory)) {
            throw new InvalidArgumentException(sprintf('The temp directory path "%s" must be an absolute path.', $tempDirectory));
        }

        $this->tempDirectory = $tempDirectory;

        return $this;
    }

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

    public function reportUnmatchedIgnoredErrors(bool $reportUnmatchedIgnoredErrors): self
    {
        $this->reportUnmatchedIgnoredErrors = $reportUnmatchedIgnoredErrors;

        return $this;
    }

    public function onlyAnalyzeTemplatesWithContext(): self
    {
        $this->onlyAnalyzeTemplatesWithContext = true;

        return $this;
    }

    /**
     * Path to PHPStan binary. Usually in vendor/bin/phpstan.
     *
     * @phpstan-assert !null $this->phpstanBinPath
     */
    public function phpstanBinPath(string $phpstanBinPath): self
    {
        if ( ! file_exists($phpstanBinPath)) {
            throw new InvalidArgumentException(sprintf('The PHPStan binary path "%s" does not exist.', $phpstanBinPath));
        }

        if ( ! Path::isAbsolute($phpstanBinPath)) {
            throw new InvalidArgumentException(sprintf('The PHPStan binary path "%s" must be an absolute path.', $phpstanBinPath));
        }

        $this->phpstanBinPath = $phpstanBinPath;

        return $this;
    }

    /**
     * Path to PHPStan configuration file.
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
     */
    public function phpstanMemoryLimit(false | string $phpstanMemoryLimit): self
    {
        $this->phpstanMemoryLimit = $phpstanMemoryLimit;

        return $this;
    }

    /**
     * TwigStan needs access to your Twig environment to analyze your templates.
     * Specify the file that returns Twig\Environment.
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
     */
    public function twigExcludes(string ...$excludes): self
    {
        $this->twigExcludes = [
            ...$this->twigExcludes,
            ...array_values($excludes),
        ];

        return $this;
    }

    public function twigExtensions(string ...$extensions): self
    {
        $this->twigExtensions = [
            ...$this->twigExtensions,
            ...array_values($extensions),
        ];

        return $this;
    }

    /**
     * Used to scan for PHP controllers that render Twig templates.
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
     */
    public function phpExcludes(string ...$excludes): self
    {
        $this->phpExcludes = [
            ...$this->phpExcludes,
            ...array_values($excludes),
        ];

        return $this;
    }

    public function phpExtensions(string ...$extensions): self
    {
        $this->phpExtensions = [
            ...$this->phpExtensions,
            ...array_values($extensions),
        ];

        return $this;
    }

    public function ignoreErrors(IgnoreError ...$ignoreErrors): self
    {
        $this->ignoreErrors = [
            ...$this->ignoreErrors,
            ...array_values($ignoreErrors),
        ];

        return $this;
    }

    /**
     * @param class-string<TemplateContextCollector<Node>> ...$classNames
     */
    public function twigContextCollector(string ...$classNames): self
    {
        foreach ($classNames as $className) {
            if ( ! is_a($className, TemplateContextCollector::class, true)) {
                throw new RuntimeException(sprintf('Class %s does not implement %s interface.', $className, TemplateContextCollector::class));
            }

            $this->twigContextCollectors[] = $className;
        }

        return $this;
    }

    public function editorUrl(string $editorUrl): self
    {
        $this->editorUrl = $editorUrl;

        return $this;
    }
}
