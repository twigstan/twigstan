<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Twig\Environment;

class RenderFromTwig
{
    public function generateContent(Environment $environment): string
    {
        return $environment->render('RenderPoints/render.html.twig', [
            'title' => 'RenderAction',
            'artists' => ['Adele', 'Kanye West'],
        ]);
    }
}
