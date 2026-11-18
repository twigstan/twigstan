<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GuardedVariableAccess;

use Twig\Environment;

final class Controller
{
    public function render(Environment $twig): void
    {
        $twig->render('@EndToEnd/GuardedVariableAccess/direct/is-defined.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/direct/coalesce.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/direct/default.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/attribute/is-defined.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/attribute/coalesce.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/attribute/default.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/method/is-defined.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/method/coalesce.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/method/default.twig');
        $twig->render('@EndToEnd/GuardedVariableAccess/unguarded.twig');
    }
}
