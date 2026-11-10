<?php

declare(strict_types=1);

namespace EndToEnd\Bug317;

use Twig\Environment;

final class Controller
{
    public function twoLoops(Environment $environment, mixed $dynamic): string
    {
        return $environment->render('EndToEnd/Bug317/two_loops.twig', [
            'known' => 42,
            'dynamic' => $dynamic,
            'metadata' => array_fill(0, 5, null),
        ]);
    }

    public function fourLoops(Environment $environment, mixed $dynamic): string
    {
        return $environment->render('EndToEnd/Bug317/four_loops.twig', [
            'known' => 42,
            'dynamic' => $dynamic,
            'metadata' => array_fill(0, 90, null),
        ]);
    }
}
