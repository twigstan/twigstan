<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;

final class RenderFromAbstractController extends AbstractController
{
    public function renderAction(): Response
    {
        $response = new Response(status: Response::HTTP_CREATED);

        return $this->render('EndToEnd/RenderPoints/render.html.twig', [
            'title' => 'RenderAction',
            'artists' => ['Adele', 'Kanye West'],
        ], $response);
    }

    public function renderViewAction(): Response
    {
        return new Response($this->renderView('EndToEnd/RenderPoints/render.html.twig', [
            'title' => 'RenderViewAction',
            'artists' => ['Adele', 'Kanye West'],
        ]));
    }

    public function formAction(): Response
    {
        $form1 = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Your Name',
            ])
            ->getForm();

        $form2 = $this->createForm(RegisterForm::class, new RegisterFormDto());

        return $this->render('EndToEnd/RenderPoints/form.html.twig', [
            'form1' => $form1,
            'form2' => $form2,
        ]);
    }
}
