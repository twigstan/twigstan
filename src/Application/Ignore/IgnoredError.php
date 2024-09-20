<?php

declare(strict_types=1);

namespace TwigStan\Application\Ignore;

use TwigStan\Application\TwigStanError;
use TwigStan\File\FileExcluder;

final class IgnoredError
{
    /**
     * @param string|mixed[] $ignoredError
     * @return string Representation of the ignored error
     */
    public static function stringifyPattern(array | string $ignoredError): string
    {
        if (!is_array($ignoredError)) {
            return $ignoredError;
        }

        $message = '';
        if (isset($ignoredError['message'])) {
            $message = $ignoredError['message'];
        }
        if (isset($ignoredError['identifier'])) {
            if ($message === '') {
                $message = $ignoredError['identifier'];
            } else {
                $message = sprintf('%s (%s)', $message, $ignoredError['identifier']);
            }
        }

        if ($message === '') {
            throw new \RuntimeException('Internal Error.');
        }

        // ignore by path
        if (isset($ignoredError['path'])) {
            return sprintf('%s in path %s', $message, $ignoredError['path']);
        }

        if (isset($ignoredError['paths'])) {
            if (count($ignoredError['paths']) === 1) {
                return sprintf('%s in path %s', $message, implode(', ', $ignoredError['paths']));

            }
            return sprintf('%s in paths: %s', $message, implode(', ', $ignoredError['paths']));
        }

        return $message;
    }

    /**
     * @return bool To ignore or not to ignore?
     */
    public static function shouldIgnore(
        TwigStanError $error,
        ?string $ignoredErrorPattern,
        ?string $identifier,
        ?string $path,
        string $workingDirectory,
    ): bool {
        if ($identifier !== null) {
            if ($error->identifier !== $identifier) {
                return false;
            }
        }

        if ($ignoredErrorPattern !== null) {
            // normalize newlines to allow working with ignore-patterns independent of used OS newline-format
            $errorMessage = $error->message;
            $errorMessage = str_replace(['\r\n', '\r'], '\n', $errorMessage);
            $ignoredErrorPattern = str_replace([preg_quote('\r\n'), preg_quote('\r')], preg_quote('\n'), $ignoredErrorPattern);

            if ((int) preg_match($ignoredErrorPattern, $errorMessage) === 0) {
                return false;
            }
        }

        if ($path !== null) {
            return (new FileExcluder($workingDirectory, [$path]))->isExcludedFromAnalysing($error->twigSourceLocation->fileName ?? $error->phpFile);
        }

        return true;
    }

}
