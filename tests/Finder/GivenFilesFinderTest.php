<?php

namespace TwigStan\Finder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigStan\Application\ContainerFactory;

class GivenFilesFinderTest extends TestCase
{
    private GivenFilesFinder $givenFilesFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/twigstan.neon');
        $container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');

        /**
         * @var GivenFilesFinder $givenFilesFinder
         */
        $givenFilesFinder = $container->getByType(GivenFilesFinder::class);

        $this->givenFilesFinder = $givenFilesFinder;
    }

    public function testFindFiles(): void
    {
        $files = $this->givenFilesFinder->find([
            'files',
            'files/file.php',
            'files/template.twig',
        ]);

        self::assertIsArray($files);

        self::assertCount(2, $files);

        /**
         * @var SplFileInfo $phpFile
         */
        $phpFile = $files[__DIR__ . '/files/file.php'];

        /**
         * @var SplFileInfo $twigFile
         */
        $twigFile = $files[__DIR__ . '/files/template.twig'];

        self::assertSame($phpFile->getFilename(), 'file.php');
        self::assertSame($twigFile->getFilename(), 'template.twig');
    }
}
