<?php

declare(strict_types=1);

namespace TwigStan;

use JsonException;
use PHPUnit\Framework\Assert;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use TwigStan\Application\TwigStanAnalysisResult;
use TwigStan\Application\TwigStanError;
use TwigStan\Processing\TemplateContext;

final readonly class ErrorHelper
{
    /**
     * @param list<string> $files
     *
     * @throws JsonException
     */
    public static function assertAnalysisResultMatchesJsonFile(TwigStanAnalysisResult $result, string $directory, array $files = []): void
    {
        $filesystem = new Filesystem();
        $expectedErrors = json_decode(
            $filesystem->readFile(Path::join($directory, 'errors.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $versionSpecificErrorsFile = Path::join($directory, sprintf('errors.v%d.json', Environment::MAJOR_VERSION));

        if (file_exists($versionSpecificErrorsFile)) {
            $expectedErrors['errors'] = [
                ...$expectedErrors['errors'],
                ...json_decode(
                    $filesystem->readFile($versionSpecificErrorsFile),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            ];
        }

        // When the test only wants to check a subset of files, we should filter out other expected errors.
        // Otherwise the test will never pass. This is especially useful when debugging.
        if ($files !== []) {
            $expectedErrors['errors'] = array_filter(
                $expectedErrors['errors'],
                function ($error) use ($files) {
                    foreach ($files as $file) {
                        if (str_starts_with(sprintf('%s:', $error['twigSourceLocation']), $file)) {
                            return true;
                        }
                    }

                    return false;
                },
            );
        }

        $actual = self::toArray($result, $directory);

        $expectedButNotActual = [];
        $actualErrorsNotExpected = $actual['errors'];
        foreach ($expectedErrors['errors'] as $expectedError) {
            $key = array_search($expectedError, $actualErrorsNotExpected, true);

            if ($key !== false) {
                unset($actualErrorsNotExpected[$key]);

                continue;
            }

            $expectedButNotActual[] = $expectedError;
        }

        $actualErrorsNotExpected = array_values($actualErrorsNotExpected);

        Assert::assertTrue(
            $expectedButNotActual === [] && $actualErrorsNotExpected === [],
            sprintf(
                "The following errors were expected but not found: %s\n\nThe following errors were found but not expected: %s",
                json_encode(
                    $expectedButNotActual,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
                ),
                json_encode(
                    $actualErrorsNotExpected,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
                ),
            ),
        );

        Assert::assertEqualsCanonicalizing(
            $expectedErrors['fileSpecificErrors'],
            $actual['fileSpecificErrors'],
            sprintf(
                'FileSpecificErrors do not match with expectations. The full actual result is: %s',
                json_encode($actual, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            ),
        );

        try {
            $expectedContext = json_decode(
                $filesystem->readFile(Path::join($directory, 'context.json')),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $actualContext = ErrorHelper::contextToArray($result->context, $directory);

            Assert::assertEqualsCanonicalizing(
                $expectedContext,
                $actualContext,
                sprintf(
                    'Context do not match with expectations. The actual context is: %s',
                    json_encode($actualContext, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                ),
            );
        } catch (IOException) {
            // Ignore
        }
    }

    /**
     * @return array{
     *     errors: list<array{
     *          message: string,
     *          identifier: string|null,
     *          tip: string|null,
     *          twigSourceLocation: string|null,
     *          renderPoints: array<string>,
     *     }>,
     *     fileSpecificErrors: list<string>,
     * }
     */
    private static function toArray(TwigStanAnalysisResult $result, string $directory): array
    {
        return [
            'errors' => array_map(
                fn($error) => self::errorToArray($error, $directory),
                $result->errors,
            ),
            'fileSpecificErrors' => $result->fileSpecificErrors,
        ];
    }

    /**
     * @return array{
     *      message: string,
     *      identifier: string|null,
     *      tip: string|null,
     *      twigSourceLocation: string|null,
     *      renderPoints: array<string>,
     * }
     */
    private static function errorToArray(TwigStanError $error, string $directory): array
    {
        return [
            'message' => $error->message,
            'identifier' => $error->identifier,
            'tip' => $error->tip,
            'twigSourceLocation' => $error->twigSourceLocation?->toString($directory),
            'renderPoints' => array_map(
                fn($renderPoint) => $renderPoint->toString($directory),
                $error->renderPoints,
            ),
        ];
    }

    /**
     * @return array<string, non-empty-list<array{string, string}>>
     */
    public static function contextToArray(TemplateContext $templateContext, string $directory): array
    {
        $result = [];

        foreach ($templateContext->context as $template => $renderPoints) {
            foreach ($renderPoints as [$sourceLocation, $context]) {
                $result[Path::makeRelative($template, $directory)][] = [
                    $sourceLocation->toString($directory),
                    $context,
                ];
            }
        }

        return $result;
    }
}
