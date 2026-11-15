<?php

declare(strict_types=1);

namespace TwigStan\Baseline;

use Twig\Environment;

final class RenderAbstract
{
    public function render(Environment $environment): string
    {
        return $environment->render('Baseline/abstract.html.twig');
    }
}
