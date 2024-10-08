<?php

namespace TwigStan\EndToEnd;

use Symfony\Component\HttpFoundation\Request;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TemplateDataExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private ?Request $request = null,
    ) {}

    public function getGlobals(): array
    {
        if ($this->request === null) {
            return [];
        }

        return [
            'test_global' => $this->request->attributes->get('_test_global'),
        ];
    }
}
