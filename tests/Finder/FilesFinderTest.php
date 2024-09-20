<?php

namespace TwigStan\Finder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigStan\Application\ContainerFactory;

class FilesFinderTest extends TestCase
{
    private FilesFinder $phpFileFinder;

    private FilesFinder $twigFileFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/twigstan.neon');
        $container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');

        /** @var FilesFinder $phpFilesFinderService */
        $phpFilesFinderService = $container->getService('twigstan.files_finder.php');

        /** @var FilesFinder $twigFilesFinderService */
        $twigFilesFinderService = $container->getService('twigstan.files_finder.twig');

        $this->phpFileFinder = $phpFilesFinderService;
        $this->twigFileFinder = $twigFilesFinderService;
    }

    public function testFindPhpFiles(): void
    {
        $files = $this->phpFileFinder->find();

        self::assertIsArray($files);

        self::assertCount(1, $files);

        /** @var SplFileInfo $firstFile */
        $firstFile = $files[__DIR__ . '/files/file.php'];

        self::assertSame($firstFile->getFilename(), 'file.php');
    }

    public function testFindTwigFiles(): void
    {
        $files = $this->twigFileFinder->find();

        self::assertIsArray($files);

        self::assertCount(1, $files);

        /** @var SplFileInfo $firstFile */
        $firstFile = $files[__DIR__ . '/files/template.twig'];

        self::assertSame($firstFile->getFilename(), 'template.twig');
    }
}
