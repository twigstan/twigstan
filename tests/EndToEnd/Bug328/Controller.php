<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Bug328;

use Twig\Environment;

final class Controller
{
    public function page(Environment $environment): string
    {
        return $environment->render('EndToEnd/Bug328/page.twig', [
            'title' => 'Hello',
        ]);
    }
}
