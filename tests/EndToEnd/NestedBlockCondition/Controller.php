<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\NestedBlockCondition;

use Twig\Environment;

final class Controller
{
    public function render(Environment $twig, bool $enabled): void
    {
        $twig->render('@EndToEnd/NestedBlockCondition/nested.twig', ['enabled' => $enabled]);
        $twig->render('@EndToEnd/NestedBlockCondition/without_condition.twig', ['enabled' => $enabled]);
        $twig->render('@EndToEnd/NestedBlockCondition/without_inner.twig', ['enabled' => $enabled]);
        $twig->render('@EndToEnd/NestedBlockCondition/without_middle.twig', ['enabled' => $enabled]);
        $twig->render('@EndToEnd/NestedBlockCondition/derived_assignment.twig');
    }
}
