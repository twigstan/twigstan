<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\BlockContextAfterCondition;

use Twig\Environment;

final class Controller
{
    public function render(Environment $environment, bool $unrelated): string
    {
        return $environment->render('EndToEnd/BlockContextAfterCondition/template.twig', [
            'unrelated' => $unrelated,
        ]);
    }
}
