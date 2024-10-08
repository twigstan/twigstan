<?php

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class InheritedRenderPointController extends AbstractController
{
    public function renderAction(): Response
    {
        $response = new Response(status: Response::HTTP_CREATED);

        return $this->render('EndToEnd/RenderPoints/child.twig', [
            'title' => 'RenderAction',
            'description' => 'Description',
        ], $response);
    }

    public function renderViewAction(): Response
    {
        return new Response($this->renderView('EndToEnd/RenderPoints/child.twig', [
            'title' => 'RenderViewAction',
            'description' => 'Description',
        ]));
    }
}
