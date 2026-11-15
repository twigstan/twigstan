<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class CustomRouteTemplateAttributeController
{
    /**
     * @return array<mixed>
     */
    #[CustomRoute('/custom-route')]
    #[Template('@EndToEnd/RenderPoints/simple.html.twig')]
    public function __invoke(): array
    {
        return [
            'title' => 'SimpleAction',
            'rows' => $this->getRows(),
        ];
    }

    /**
     * @return non-empty-list<string>
     */
    private function getRows(): array
    {
        return ['A', 'B'];
    }
}
