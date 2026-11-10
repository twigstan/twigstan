<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LevelMax;

use DateTimeImmutable;
use Twig\Environment;

final class DefinedController
{
    public function withDeadline(Environment $environment, bool $isOptional): string
    {
        return $environment->render('EndToEnd/LevelMax/defined_with_deadline.twig', ['is_optional' => $isOptional, 'deadline' => new DateTimeImmutable()]);
    }

    public function withoutDeadline(Environment $environment, bool $isOptional): string
    {
        return $environment->render('EndToEnd/LevelMax/defined_without_deadline.twig', ['is_optional' => $isOptional]);
    }
}
