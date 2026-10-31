<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\OnlyWithContext;

use Twig\Environment;

final class RenderPage
{
    public function render(Environment $environment): string
    {
        return $environment->render('EndToEnd/OnlyWithContext/rendered.twig', [
            'title' => 'Hello',
        ]);
    }
}
