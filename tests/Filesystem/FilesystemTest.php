<?php

declare(strict_types=1);

namespace TwigStan\Filesystem;

use PHPUnit\Framework\TestCase;

final class FilesystemTest extends TestCase
{
    public function testReadFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'twigstan-');

        self::assertNotFalse($file);

        try {
            file_put_contents($file, 'content');

            self::assertSame('content', (new Filesystem())->readFile($file));
        } finally {
            unlink($file);
        }
    }
}
