<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class NamedArgumentsController extends AbstractController
{
    public function renderAction(): Response
    {
        return $this->render(parameters: ['name' => 'NamedArguments'], view: 'EndToEnd/RenderPoints/optionality.html.twig');
    }

    public function renderWithResponseAction(): Response
    {
        return $this->render('EndToEnd/RenderPoints/optionality.html.twig', response: new Response(status: Response::HTTP_GONE));
    }

    public function renderFromTwigAction(Environment $environment): string
    {
        return $environment->render(context: ['name' => null], name: 'EndToEnd/RenderPoints/optionality.html.twig');
    }

    /**
     * @return array<mixed>
     */
    #[Route('/named-arguments')]
    #[Template(vars: ['title'], template: '@EndToEnd/RenderPoints/simple.html.twig')]
    public function templateAttributeAction(): array
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
