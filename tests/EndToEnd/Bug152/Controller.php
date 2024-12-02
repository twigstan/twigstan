<?php

declare(strict_types=1);

namespace EndToEnd\Bug152;

use Twig\Environment;

final class Controller
{
    public function homepage(Environment $environment): string
    {
        return $environment->render('EndToEnd/Bug152/homepage.twig', [
            'title' => 'Welcome',
        ]);
    }
}
