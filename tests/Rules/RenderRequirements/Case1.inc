<?php

declare(strict_types=1);

namespace TwigStan\Rules\RenderRequirements;

use RedirectResponse;
use Request;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Case1
{
    #[Route('/blaat')]
    #[Template('@RenderRequirements/list.html.twig')]
    public function listAction(Request $request): array | RedirectResponse
    {
        if (mt_rand(0, 1)) {
            return new RedirectResponse('https://www.example.com');
        }

        if ($request->isMethod() === 'POST') {
            return [
                'title' => 'Artists',
            ];
        }

        if ($request->isMethod() === 'PUT') {
            return [
                'somethingElse' => true,
            ];
        }

        return [
            'title' => 'Artists',
            'artists' => ['Adele', 'Kanye West'],
            'logos' => [],
        ];
    }
}
