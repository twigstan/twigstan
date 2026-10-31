<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\ShapelessContext;

use Twig\Environment;

final class ShapelessContextController
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function render(Environment $environment, array $parameters): string
    {
        return $environment->render('EndToEnd/ShapelessContext/shapeless.twig', $parameters);
    }

    public function renderWithName(Environment $environment): string
    {
        return $environment->render('EndToEnd/ShapelessContext/shapeless.twig', [
            'name' => 'Ruud',
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function renderChild(Environment $environment, array $parameters): string
    {
        return $environment->render('EndToEnd/ShapelessContext/child.twig', $parameters);
    }

    public function renderEmpty(Environment $environment): string
    {
        return $environment->render('EndToEnd/ShapelessContext/empty.twig', []);
    }
}
