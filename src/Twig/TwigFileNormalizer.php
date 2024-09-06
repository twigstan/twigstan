<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use Twig\Environment;
use TwigStan\Twig\Loader\AbsolutePathLoader;

final readonly class TwigFileNormalizer
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function normalize(string $twigFileName): string
    {
        if (str_starts_with($twigFileName, '@')) {
            return $twigFileName;
        }

        /**
         * @var AbsolutePathLoader $loader
         */
        $loader = $this->twig->getLoader();

        return $loader->maybeResolveAbsolutePath($twigFileName);
    }
}
