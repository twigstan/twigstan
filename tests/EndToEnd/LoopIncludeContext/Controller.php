<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LoopIncludeContext;

use Twig\Environment;

final class Controller
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function index(Environment $environment, array $parameters): string
    {
        return $environment->render('EndToEnd/LoopIncludeContext/list.twig', $parameters);
    }
}
