<?php

declare(strict_types=1);

namespace TwigStan\Error\Baseline;

use Symfony\Component\Filesystem\Path;

final readonly class PhpBaselineDumper implements BaselineDumper
{
    public function __construct(
        private string $currentWorkingDirectory,
    ) {}

    public function dump(array $errors, string $baselineDirectory): string
    {
        $output = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";

        foreach ($errors as $error) {
            $output .= sprintf(
                "    new %s(\n",
                $error::class,
            );
            $output .= sprintf(
                "        %s,\n",
                var_export($error->message, true),
            );
            $output .= sprintf(
                "        %s,\n",
                $error->identifier === null ? 'null' : var_export($error->identifier, true),
            );
            $output .= sprintf(
                "        __DIR__ . %s,\n",
                var_export(Path::join(
                    DIRECTORY_SEPARATOR,
                    Path::makeRelative(
                        Path::makeAbsolute($error->path, $this->currentWorkingDirectory),
                        $baselineDirectory,
                    ),
                ), true),
            );
            $output .= sprintf(
                "        %s,\n",
                var_export($error->count, true),
            );
            $output .= "    ),\n";
        }

        $output .= "];\n";

        return $output;
    }
}
