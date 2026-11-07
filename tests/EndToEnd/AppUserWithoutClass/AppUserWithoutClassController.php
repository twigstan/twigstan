<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AppUserWithoutClass;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AppUserWithoutClassController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/public/without-class/account')]
    public function account(): Response
    {
        return $this->render('@EndToEnd/AppUserWithoutClass/account.twig');
    }

    #[Route('/public/without-class/home')]
    public function home(): Response
    {
        return $this->render('@EndToEnd/AppUserWithoutClass/public.twig');
    }
}
