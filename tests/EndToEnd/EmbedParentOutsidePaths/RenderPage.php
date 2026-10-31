<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\EmbedParentOutsidePaths;

use Twig\Environment;

final class RenderPage
{
    public function render(Environment $environment): string
    {
        return $environment->render('EndToEnd/EmbedParentOutsidePaths/page.twig', [
            'name' => 'Ruud',
        ]);
    }
}
