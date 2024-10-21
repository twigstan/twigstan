<?php

declare(strict_types=1);

namespace EndToEnd\RenderPoints\App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class RenderFromSelfClassInAbstractController extends AbstractController
{
    public function __invoke(): Response
    {
        return new Response($this->renderView(self::class . '.html.twig', [
            'title' => 'FromInvoke',
        ]));
    }
}
