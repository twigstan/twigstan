<?php

namespace TwigStan\EndToEnd\Baseline;

use Nette\Neon\Neon;
use RuntimeException;

use TwigStan\EndToEnd\AbstractEndToEndTestCase;

final class GenerationBaselineTest extends AbstractEndToEndTestCase
{
    public const string BASELINE_PATH = __DIR__ . '/../../../twigstan-baseline.neon';
    private const string ALTER_BASELINE_PATH = __DIR__ . '/../../another-baseline.neon';
    private const string ALTER_BASELINE_PATH_NOT_SUPPORTED = __DIR__ . '/../../another-baseline.xyz';
    public const string TWIGSTAN_CONFIG_PATH = __DIR__ . '/../../twigstan.neon';

    private const bool BASELINE_GENERATION_ENABLED = true;

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_file(self::BASELINE_PATH)) {
            unlink(self::BASELINE_PATH);
        }

        if (is_file(self::ALTER_BASELINE_PATH)) {
            unlink(self::ALTER_BASELINE_PATH);
        }
    }

    public function testEmpty(): void
    {
        parent::runTests(__DIR__ . '/Empty', self::BASELINE_GENERATION_ENABLED);

        self::assertFileExists(self::BASELINE_PATH);
    }

    public function testNotEmpty(): void
    {
        parent::runTests(__DIR__ . '/NotEmpty', self::BASELINE_GENERATION_ENABLED);

        self::assertFileExists(self::BASELINE_PATH);

        $content = file_get_contents(self::BASELINE_PATH);

        self::assertIsString($content);

        $contentDecode = Neon::decode($content);

        self::assertCount(2, $contentDecode['parameters']['twigstan']['ignoreErrors']);
    }

    public function testCustomBaselineFilename(): void
    {
        parent::runTests(__DIR__ . '/NotEmpty', self::ALTER_BASELINE_PATH);

        self::assertFileExists(self::ALTER_BASELINE_PATH);
    }

    public function testCustomBaselineFilenameNotSupported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('xyz extension is not supported yet.');

        parent::runTests(__DIR__ . '/NotEmpty', self::ALTER_BASELINE_PATH_NOT_SUPPORTED);

        self::assertFileDoesNotExist(self::ALTER_BASELINE_PATH_NOT_SUPPORTED);
    }
}
