<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Inheritance;

use Twig\Environment;

final class Case4Controller
{
    public function __invoke(Environment $environment): string
    {
        return $environment->render('EndToEnd/Inheritance/case4.twig', [
            'title' => 'Welcome',
            'subtitle' => 'Welcome',
        ]);
    }
}
