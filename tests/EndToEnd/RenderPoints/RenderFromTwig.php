<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Twig\Environment;

final class RenderFromTwig
{
    public function generateContent(Environment $environment): string
    {
        return $environment->render('EndToEnd/RenderPoints/render.html.twig', [
            'title' => 'RenderAction',
            'artists' => ['Adele', 'Kanye West'],
        ]);
    }
}
