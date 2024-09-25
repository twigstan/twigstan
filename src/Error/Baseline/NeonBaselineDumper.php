<?php

declare(strict_types=1);

namespace TwigStan\Error\Baseline;

use Nette\Neon\Neon;
use TwigStan\Error\BaselineError;

final readonly class NeonBaselineDumper implements BaselineDumper
{
    public function dump(array $errors): string
    {
        return Neon::encode([
            'parameters' => [
                'baselineErrors' => array_map(
                    fn(BaselineError $error): array => [
                        'message' => $error->message,
                        'identifier' => $error->identifier,
                        'path' => $error->path,
                        'count' => $error->count,
                    ],
                    $errors,
                ),
            ],
        ], true);
    }
}
