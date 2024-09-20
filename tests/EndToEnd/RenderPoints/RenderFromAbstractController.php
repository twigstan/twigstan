<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class RenderFromAbstractController extends AbstractController
{
    public function renderAction(): Response
    {
        $response = new Response(status: Response::HTTP_CREATED);

        return $this->render('RenderPoints/render.html.twig', [
            'title' => 'RenderAction',
            'artists' => ['Adele', 'Kanye West'],
        ], $response);
    }

    public function renderViewAction(): Response
    {
        return new Response($this->renderView('RenderPoints/render.html.twig', [
            'title' => 'RenderViewAction',
            'artists' => ['Adele', 'Kanye West'],
        ]));
    }
}
