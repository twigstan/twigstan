<?php

declare(strict_types=1);

namespace EndToEnd\RecursiveInclude;

use Twig\Environment;
use TwigStan\EndToEnd\RecursiveInclude\Category;

final class Controller
{
    /**
     * @param list<Category> $categories
     */
    public function navigation(Environment $environment, array $categories): string
    {
        return $environment->render('EndToEnd/RecursiveInclude/category_navigation.twig', [
            'categories' => $categories,
            'level' => 1,
        ]);
    }
}
