<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Inheritance;

use Twig\Environment;

final class Case4Controller
{
    public function case4(Environment $environment): string
    {
        return $environment->render('EndToEnd/Inheritance/case4.twig', [
            'title' => 'Welcome',
            'subtitle' => 'Welcome',
        ]);
    }

    public function bug164(Environment $environment): string
    {
        return $environment->render('EndToEnd/Inheritance/bug_164.twig', [
            'template' => '@EndToEnd/Inheritance/layout.twig',
        ]);
    }
}
