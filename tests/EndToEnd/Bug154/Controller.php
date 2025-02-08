<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug154;

use Twig\Environment;

final class Controller
{
    public function page(Environment $environment): string
    {
        return $environment->render('EndToEnd/Bug154/page.twig', [
            'title' => 'Welcome',
        ]);
    }
}
