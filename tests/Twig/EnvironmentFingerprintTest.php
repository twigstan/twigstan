<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class EnvironmentFingerprintTest extends TestCase
{
    public function testChangesWithTheCodeOfAClosure(): void
    {
        $file = sys_get_temp_dir() . '/twigstan-fingerprint-closure.php';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($file, '<?php return fn(string $code): string => $code;');

        $twig = new Environment(new ArrayLoader());
        $twig->addFunction(new TwigFunction('code', require $file));

        $before = (new EnvironmentFingerprint($twig, null))->get();
        $filesystem->dumpFile($file, '<?php return fn(?string $code): string => (string) $code;');
        $after = (new EnvironmentFingerprint($twig, null))->get();
        $again = (new EnvironmentFingerprint($twig, null))->get();

        $filesystem->remove($file);

        self::assertNotSame($before, $after);
        self::assertSame($after, $again);
    }
}
