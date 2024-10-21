<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Twig\Environment;

final class OptionalityController
{
    public function firstAction(Environment $environment, ?string $name): string
    {
        return $environment->render('EndToEnd/RenderPoints/optionality.html.twig', [
            'name' => $name,
        ]);
    }

    public function secondAction(Environment $environment): string
    {
        return $environment->render('EndToEnd/RenderPoints/optionality.html.twig');
    }
}
