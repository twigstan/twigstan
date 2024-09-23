<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ComplexTemplateAttributeController
{
    /**
     * @return array<mixed>|RedirectResponse
     */
    #[Route('/blaat')]
    #[Template('@EndToEnd/RenderPoints/complex.html.twig')]
    public function listAction(Request $request): array | RedirectResponse
    {
        if (mt_rand(0, 1)) {
            return new RedirectResponse('https://www.example.com');
        }

        if ($request->isMethod('POST')) {
            return [
                'title' => 'PostAction',
                'error' => sha1('error'),
            ];
        }

        if ($request->isMethod('PUT')) {
            return [
                'title' => 'PutAction',
                'error' => sha1('error'),
            ];
        }

        return [
            'title' => 'GetAction',
        ];
    }
}
