<?php

declare(strict_types=1);

namespace TwigStan\Application\Ignore;

use Symfony\Component\Filesystem\Path;
use TwigStan\Application\TwigStanError;
use TwigStan\Twig\SourceLocation;

final class IgnoredErrorHelperResult
{
    /**
     * @param list<string> $errors
     * @param array<array<mixed>> $otherIgnoreErrors
     * @param array<string, array<array<mixed>>> $ignoreErrorsByFile
     * @param (string|mixed[])[] $ignoreErrors
     */
    public function __construct(
        private array $errors,
        private array $otherIgnoreErrors,
        private array $ignoreErrorsByFile,
        private array $ignoreErrors,
        private bool $reportUnmatchedIgnoredErrors,
        private string $workingDirectory,
    ) {}

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param TwigStanError[] $errors
     * @param string[] $analysedFiles
     */
    public function process(
        array $errors,
        bool $onlyFiles,
        array $analysedFiles,
        bool $hasInternalErrors,
    ): IgnoredErrorHelperProcessedResult {
        $unmatchedIgnoredErrors = $this->ignoreErrors;

        $processIgnoreError = function (TwigStanError $error, int $i, $ignore) use (&$unmatchedIgnoredErrors): bool {
            $shouldBeIgnored = false;
            if (is_string($ignore)) {
                $shouldBeIgnored = IgnoredError::shouldIgnore($error, $ignore, null, null, $this->workingDirectory);
                if ($shouldBeIgnored) {
                    unset($unmatchedIgnoredErrors[$i]);
                }
            } else {
                if (isset($ignore['path'])) {
                    $shouldBeIgnored = IgnoredError::shouldIgnore($error, $ignore['message'] ?? null, $ignore['identifier'] ?? null, $ignore['path'], $this->workingDirectory);

                    if ($shouldBeIgnored) {
                        if (isset($ignore['count'])) {
                            $realCount = $unmatchedIgnoredErrors[$i]['realCount'] ?? 0;
                            $realCount++;
                            /** @phpstan-ignore-next-line */
                            $unmatchedIgnoredErrors[$i]['realCount'] = $realCount;
                            if (!isset($unmatchedIgnoredErrors[$i]['file'])) {
                                $unmatchedIgnoredErrors[$i]['file'] = $error->twigSourceLocation->fileName ?? $error->phpFile;
                                $unmatchedIgnoredErrors[$i]['line'] = $error->twigSourceLocation->lineNumber ?? $error->phpLine;
                            }

                            if ($realCount > $ignore['count']) {
                                $shouldBeIgnored = false;
                            }
                        } else {
                            unset($unmatchedIgnoredErrors[$i]);
                        }
                    }
                } elseif (isset($ignore['paths'])) {
                    foreach ($ignore['paths'] as $j => $ignorePath) {
                        $shouldBeIgnored = IgnoredError::shouldIgnore($error, $ignore['message'] ?? null, $ignore['identifier'] ?? null, $ignorePath, $this->workingDirectory);
                        if (!$shouldBeIgnored) {
                            continue;
                        }

                        if (isset($unmatchedIgnoredErrors[$i])) {
                            if (!is_array($unmatchedIgnoredErrors[$i])) {
                                throw new \RuntimeException('Internal Error.');
                            }
                            unset($unmatchedIgnoredErrors[$i]['paths'][$j]);
                            if (isset($unmatchedIgnoredErrors[$i]['paths']) && count($unmatchedIgnoredErrors[$i]['paths']) === 0) {
                                unset($unmatchedIgnoredErrors[$i]);
                            }
                        }
                        break;
                    }
                } else {
                    $shouldBeIgnored = IgnoredError::shouldIgnore($error, $ignore['message'] ?? null, $ignore['identifier'] ?? null, null, $this->workingDirectory);
                    if ($shouldBeIgnored) {
                        unset($unmatchedIgnoredErrors[$i]);
                    }
                }
            }

            if ($shouldBeIgnored) {
                return false;
            }

            return true;
        };

        $ignoredErrors = [];

        foreach ($errors as $errorIndex => $error) {
            $filePath = Path::normalize($error->twigSourceLocation->fileName ?? $error->phpFile);
            if (isset($this->ignoreErrorsByFile[$filePath])) {
                foreach ($this->ignoreErrorsByFile[$filePath] as $ignoreError) {
                    $i = $ignoreError['index'];
                    $ignore = $ignoreError['ignoreError'];
                    $result = $processIgnoreError($error, $i, $ignore);
                    if (!$result) {
                        unset($errors[$errorIndex]);
                        $ignoredErrors[] = [$error, $ignore];
                        continue 2;
                    }
                }
            }

            foreach ($this->otherIgnoreErrors as $ignoreError) {
                $i = $ignoreError['index'];
                $ignore = $ignoreError['ignoreError'];

                $result = $processIgnoreError($error, $i, $ignore);

                if (!$result) {
                    unset($errors[$errorIndex]);
                    $ignoredErrors[] = [$error, $ignore];
                    continue 2;
                }
            }
        }

        $errors = array_values($errors);

        foreach ($unmatchedIgnoredErrors as $unmatchedIgnoredError) {
            if (!isset($unmatchedIgnoredError['count']) || !isset($unmatchedIgnoredError['realCount'])) {
                continue;
            }

            if ($unmatchedIgnoredError['realCount'] <= $unmatchedIgnoredError['count']) {
                continue;
            }

            $errors[] = new TwigStanError(
                sprintf(
                    'Ignored error pattern %s is expected to occur %d %s, but occurred %d %s.',
                    IgnoredError::stringifyPattern($unmatchedIgnoredError),
                    $unmatchedIgnoredError['count'],
                    $unmatchedIgnoredError['count'] === 1 ? 'time' : 'times',
                    $unmatchedIgnoredError['realCount'],
                    $unmatchedIgnoredError['realCount'] === 1 ? 'time' : 'times',
                ),
                'ignore.count',
                null,
                $unmatchedIgnoredError['file'],
                $unmatchedIgnoredError['line'],
                new SourceLocation($unmatchedIgnoredError['file'], $unmatchedIgnoredError['line']),
                [],
            );
        }

        $analysedFilesKeys = array_fill_keys($analysedFiles, true);

        if (!$hasInternalErrors) {
            foreach ($unmatchedIgnoredErrors as $unmatchedIgnoredError) {
                $reportUnmatched = $unmatchedIgnoredError['reportUnmatched'] ?? $this->reportUnmatchedIgnoredErrors;
                if ($reportUnmatched === false) {
                    continue;
                }
                if (
                    isset($unmatchedIgnoredError['count'])
                    && isset($unmatchedIgnoredError['realCount'])
                    && (isset($unmatchedIgnoredError['realPath']) || !$onlyFiles)
                ) {
                    if ($unmatchedIgnoredError['realCount'] < $unmatchedIgnoredError['count']) {
                        $errors[] = (new TwigStanError(
                            sprintf(
                                'Ignored error pattern %s is expected to occur %d %s, but occurred %d %s.',
                                IgnoredError::stringifyPattern($unmatchedIgnoredError),
                                $unmatchedIgnoredError['count'],
                                $unmatchedIgnoredError['count'] === 1 ? 'time' : 'times',
                                $unmatchedIgnoredError['realCount'],
                                $unmatchedIgnoredError['realCount'] === 1 ? 'time' : 'times',
                            ),
                            'ignore.count',
                            null,
                            $unmatchedIgnoredError['file'],
                            $unmatchedIgnoredError['line'],
                            new SourceLocation($unmatchedIgnoredError['file'], $unmatchedIgnoredError['line']),
                            [],
                        )
                        );
                    }
                } elseif (isset($unmatchedIgnoredError['realPath'])) {
                    if (!array_key_exists($unmatchedIgnoredError['realPath'], $analysedFilesKeys)) {
                        continue;
                    }

                    $errors[] = (new TwigStanError(
                        sprintf(
                            'Ignored error pattern %s was not matched in reported errors.',
                            IgnoredError::stringifyPattern($unmatchedIgnoredError),
                        ),
                        'ignore.unmatched',
                        null,
                        $unmatchedIgnoredError['realPath'],
                        $unmatchedIgnoredError['line'],
                        new SourceLocation($unmatchedIgnoredError['realPath'], $unmatchedIgnoredError['line']),
                        [],
                    )
                    );
                }
            }
        }

        return new IgnoredErrorHelperProcessedResult($errors, $ignoredErrors);
    }

}
