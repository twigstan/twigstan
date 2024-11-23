<?php

declare(strict_types=1);

namespace EndToEnd\IncludeFromTwig;

use Twig\Environment;

final class Controller
{
    public function homepage(Environment $environment, ?string $name): string
    {
        return $environment->render('EndToEnd/IncludeFromTwig/homepage.twig', [
            'body' => 'Welcome to the homepage',
            'name' => $name,
        ]);
    }

    public function footer(Environment $environment, ?string $name): string
    {
        return $environment->render('EndToEnd/IncludeFromTwig/footer.twig', [
            'title' => 'Footer',
            'name' => $name,
        ]);
    }
}
