<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AbstractTemplate;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class Controller extends AbstractController
{
    public function renderAction(): Response
    {
        $response = new Response(status: Response::HTTP_CREATED);

        return $this->render('EndToEnd/AbstractTemplate/child.twig', [
            'title' => 'RenderAction',
            'description' => 'Description',
        ], $response);
    }

    public function renderViewAction(): Response
    {
        return new Response($this->renderView('EndToEnd/AbstractTemplate/child.twig', [
            'title' => 'RenderViewAction',
            'description' => 'Description',
        ]));
    }

    public function renderAbstractAction(): Response
    {
        return new Response($this->renderView('EndToEnd/AbstractTemplate/layout.twig'));
    }
}
