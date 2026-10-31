<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\AppUser;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AppUserController extends AbstractController
{
    #[Route('/account/{section}', requirements: ['section' => '\d+'])]
    public function account(): Response
    {
        return $this->render('@EndToEnd/AppUser/account.twig');
    }

    #[Route('/public/home')]
    public function home(): Response
    {
        return $this->render('@EndToEnd/AppUser/public.twig');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/public/settings')]
    public function settings(): Response
    {
        return $this->render('@EndToEnd/AppUser/settings.twig');
    }

    #[Route('/account/shared')]
    public function sharedWithUser(): Response
    {
        return $this->render('@EndToEnd/AppUser/shared.twig');
    }

    #[Route('/public/shared')]
    public function sharedWithoutUser(): Response
    {
        return $this->render('@EndToEnd/AppUser/shared.twig');
    }

    #[Route('/public/navigation')]
    public function navigation(): Response
    {
        return $this->render('@EndToEnd/AppUser/navigation.twig');
    }

    #[Route('/reports')]
    public function reports(): Response
    {
        return $this->render('@EndToEnd/AppUser/reports.twig');
    }

    #[Route('/account/helper')]
    public function helper(): Response
    {
        return $this->renderHelper();
    }

    /**
     * Not a controller, so it is unknown how the template is reached.
     */
    private function renderHelper(): Response
    {
        return $this->render('@EndToEnd/AppUser/helper.twig');
    }
}
