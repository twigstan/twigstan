<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LoopIncludeContext;

use Twig\Environment;

final class Controller
{
    public function index(Environment $environment, mixed $dynamic): string
    {
        return $environment->render('EndToEnd/LoopIncludeContext/list.twig', [
            'inherited' => 42,
            'dynamic' => $dynamic,
            'metadata' => array_fill(0, 90, null),
        ]);
    }
}
