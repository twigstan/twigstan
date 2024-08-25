<?php

declare(strict_types=1);

namespace TwigStan\Rules\RenderRequirements;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Case2
{
    #[Route('/blaat')]
    #[Template('@RenderRequirements/list.html.twig')]
    public function listAction(): array
    {
        return [
            'title' => 'Artists',
            'artists' => ['Adele', 'Kanye West'],
        ];
    }
}
