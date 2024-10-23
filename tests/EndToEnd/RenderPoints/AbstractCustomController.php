<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractCustomController extends AbstractController
{
    /**
     * @param array<string, mixed> $parameters
     */
    protected function customRender(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return parent::render($view, $parameters, $response);
    }
}
