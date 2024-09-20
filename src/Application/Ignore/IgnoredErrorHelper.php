<?php

declare(strict_types=1);

namespace TwigStan\Application\Ignore;

use Symfony\Component\Filesystem\Path;

final class IgnoredErrorHelper
{
    /**
     * @param (string|mixed[])[] $ignoreErrors
     */
    public function __construct(
        private array $ignoreErrors,
        private bool $reportUnmatchedIgnoredErrors,
        private string $workingDirectory,
    ) {}

    public function initialize(): IgnoredErrorHelperResult
    {
        $otherIgnoreErrors = [];
        $ignoreErrorsByFile = [];
        $errors = [];

        $expandedIgnoreErrors = [];
        foreach ($this->ignoreErrors as $ignoreError) {
            if (is_array($ignoreError)) {
                if (!isset($ignoreError['message']) && !isset($ignoreError['messages']) && !isset($ignoreError['identifier'])) {
                    continue;
                }
                if (isset($ignoreError['messages'])) {
                    foreach ($ignoreError['messages'] as $message) {
                        $expandedIgnoreError = $ignoreError;
                        unset($expandedIgnoreError['messages']);
                        $expandedIgnoreError['message'] = $message;
                        $expandedIgnoreErrors[] = $expandedIgnoreError;
                    }
                } else {
                    $expandedIgnoreErrors[] = $ignoreError;
                }
            } else {
                $expandedIgnoreErrors[] = $ignoreError;
            }
        }

        $uniquedExpandedIgnoreErrors = [];
        foreach ($expandedIgnoreErrors as $ignoreError) {
            if (!isset($ignoreError['message']) && !isset($ignoreError['identifier'])) {
                $uniquedExpandedIgnoreErrors[] = $ignoreError;
                continue;
            }
            if (!isset($ignoreError['path'])) {
                $uniquedExpandedIgnoreErrors[] = $ignoreError;
                continue;
            }

            $key = $ignoreError['path'];
            if (isset($ignoreError['message'])) {
                $key = sprintf("%s\n%s", $key, $ignoreError['message']);
            }
            if (isset($ignoreError['identifier'])) {
                $key = sprintf("%s\n%s", $key, $ignoreError['identifier']);
            }
            if ($key === '') {
                throw new \RuntimeException('Internal Error.');
            }

            if (!array_key_exists($key, $uniquedExpandedIgnoreErrors)) {
                $uniquedExpandedIgnoreErrors[$key] = $ignoreError;
                continue;
            }

            $uniquedExpandedIgnoreErrors[$key] = [
                'message' => $ignoreError['message'] ?? null,
                'path' => $ignoreError['path'],
                'identifier' => $ignoreError['identifier'] ?? null,
                'count' => ($uniquedExpandedIgnoreErrors[$key]['count'] ?? 1) + ($ignoreError['count'] ?? 1),
                'reportUnmatched' => ($uniquedExpandedIgnoreErrors[$key]['reportUnmatched'] ?? $this->reportUnmatchedIgnoredErrors) || ($ignoreError['reportUnmatched'] ?? $this->reportUnmatchedIgnoredErrors),
            ];
        }

        $expandedIgnoreErrors = array_values($uniquedExpandedIgnoreErrors);

        foreach ($expandedIgnoreErrors as $i => $ignoreError) {
            $ignoreErrorEntry = [
                'index' => $i,
                'ignoreError' => $ignoreError,
            ];

            if (is_array($ignoreError)) {
                if (!isset($ignoreError['message']) && !isset($ignoreError['identifier'])) {
                    continue;
                }
                if (!isset($ignoreError['path'])) {
                    $otherIgnoreErrors[] = $ignoreErrorEntry;
                } elseif (@is_file($ignoreError['path'])) {
                    $normalizedPath = Path::normalize($ignoreError['path']);
                    $ignoreError['path'] = $normalizedPath;
                    $ignoreErrorsByFile[$normalizedPath][] = $ignoreErrorEntry;
                    $ignoreError['realPath'] = $normalizedPath;
                    $expandedIgnoreErrors[$i] = $ignoreError;
                } else {
                    $otherIgnoreErrors[] = $ignoreErrorEntry;
                }
            } else {
                $otherIgnoreErrors[] = $ignoreErrorEntry;
            }
        }

        return new IgnoredErrorHelperResult(
            $errors,
            $otherIgnoreErrors,
            $ignoreErrorsByFile,
            $expandedIgnoreErrors,
            $this->reportUnmatchedIgnoredErrors,
            $this->workingDirectory,
        );
    }
}
