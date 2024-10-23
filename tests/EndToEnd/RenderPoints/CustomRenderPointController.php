<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class CustomRenderPointController extends AbstractCustomController
{
    #[Route('/blaat')]
    public function __invoke(): Response
    {
        return $this->customRender('@EndToEnd/RenderPoints/simple.html.twig', [
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
