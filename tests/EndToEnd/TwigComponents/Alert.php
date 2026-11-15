<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\TwigComponents;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent('Alert', template: 'components/alert/custom.html.twig', exposePublicProps: false)]
final class Alert
{
    public string $type = 'success';

    #[ExposeInTemplate]
    private string $message = '';

    public function getMessage(): string
    {
        return $this->message;
    }

    #[ExposeInTemplate('icon')]
    public function getIconName(): string
    {
        return 'info';
    }

    #[ExposeInTemplate]
    public function getDismissible(): bool
    {
        return true;
    }
}
