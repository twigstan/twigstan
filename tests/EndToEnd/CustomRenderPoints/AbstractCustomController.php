<?php

namespace TwigStan\EndToEnd\CustomRenderPoints;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

// In some cases, I extend standard behavior and add a custom render point
class AbstractCustomController extends AbstractController
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function renderStorefront(string $view, array $parameters = [], ?Response $response = null): Response
    {
        // ...customizations

        return $this->render($view, $parameters, $response);
    }
}
