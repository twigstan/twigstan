<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\Override\vendor\foo\src\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class MyAwesomeController
{
    /**
     * @return array<mixed>
     */
    #[Route('/blaat')]
    #[Template('@FooPackage/bar/template.html.twig')]
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
