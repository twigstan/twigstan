<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\CustomRenderPoints;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class SimpleController extends AbstractCustomController
{
    #[Route('/blaat')]
    public function __invoke(): Response
    {
        return $this->renderStorefront('@EndToEnd/CustomRenderPoints/template.html.twig', [
            'title' => 'SimpleAction',
            'rows' => $this->getRows(),
        ]);
    }

    /**
     * @return non-empty-list<string>
     */
    private function getRows(): array
    {
        return ['A', 'B'];
    }
}
