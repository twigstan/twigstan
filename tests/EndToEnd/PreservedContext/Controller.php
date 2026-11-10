<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\PreservedContext;

use Twig\Environment;

final class Controller
{
    public function index(Environment $twig, mixed $dynamic): string
    {
        return $twig->render('EndToEnd/PreservedContext/list.twig', [
            'known' => 42,
            'dynamic' => $dynamic,
            'metadata' => array_fill(0, 90, null),
        ]);
    }
}
