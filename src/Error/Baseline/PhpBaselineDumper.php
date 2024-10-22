<?php

declare(strict_types=1);

namespace TwigStan\Error\Baseline;

final readonly class PhpBaselineDumper implements BaselineDumper
{
    public function dump(array $errors, string $baselineDirectory): string
    {
        $output = "<?php\n\n";
        $output .= "declare(strict_types=1);\n\n";
        $output .= "use TwigStan\Error\BaselineError;\n\n";
        $output .= "return [\n";

        foreach ($errors as $error) {
            $output .= "    new BaselineError(\n";
            $output .= sprintf(
                "        %s,\n",
                var_export($error->message, true),
            );
            $output .= sprintf(
                "        %s,\n",
                $error->identifier === null ? 'null' : var_export($error->identifier, true),
            );
            $output .= sprintf(
                "        %s,\n",
                var_export($error->file, true),
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
