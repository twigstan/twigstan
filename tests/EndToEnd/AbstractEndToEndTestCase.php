<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Throwable;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\Application\ContainerFactory;
use TwigStan\Application\TwigStanAnalysisResult;
use TwigStan\Application\TwigStanError;

abstract class AbstractEndToEndTestCase extends TestCase
{
    private const string tempDirectory = __DIR__ . '/../../.twigstan';

    private AnalyzeCommand $command;
    private BufferedOutput $output;
    private BufferedOutput $errorOutput;

    protected function setUp(): void
    {
        $containerFactory = new ContainerFactory(
            dirname(__DIR__, 2),
            __DIR__ . '/../twigstan.neon',
        );
        $container = $containerFactory->create(self::tempDirectory);
        $this->command = $container->getByType(AnalyzeCommand::class);

        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();
    }

    protected function onNotSuccessfulTest(Throwable $t): never
    {
        echo $this->output->fetch();
        echo $this->errorOutput->fetch();

        throw $t;
    }

    protected function runTests(string $directory, string | bool $generateBaseline = false): void
    {
        $filesystem = new Filesystem();
        $expectedErrors = json_decode(
            $filesystem->readFile(Path::join($directory, 'errors.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $result = $this->command->analyze(
            [Path::makeRelative($directory, dirname(__DIR__, 2))],
            $this->output,
            $this->errorOutput,
            extension_loaded('xdebug') ? true : false,
            extension_loaded('xdebug') ? true : false,
            $generateBaseline,
        );

        $actual = $this->toArray($result, $directory);

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

        self::assertTrue(
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

        self::assertEqualsCanonicalizing(
            $expectedErrors['fileSpecificErrors'],
            $actual['fileSpecificErrors'],
            sprintf(
                'FileSpecificErrors do not match with expectations. The full actual result is: %s',
                json_encode($actual, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            ),
        );
    }

    /**
     *
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
    private function toArray(TwigStanAnalysisResult $result, string $directory): array
    {
        return [
            'errors' => array_map(
                fn($error) => $this->errorToArray($error, $directory),
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
    private function errorToArray(TwigStanError $error, string $directory): array
    {
        return [
            'message' => $error->message,
            'identifier' => $error->identifier,
            'tip' => $error->tip,
            'twigSourceLocation' => $error->twigSourceLocation?->toString($directory),
            'renderPoints' => array_map(
                fn($sourceLocation) => $sourceLocation->toString($directory),
                $error->renderPoints,
            ),
        ];
    }
}
