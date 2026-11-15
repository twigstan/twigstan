<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\TwigComponents;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class MyComponent
{
    public string $content;

    public function getFormattedContent(): string
    {
        if ($this->content === '') {
            return '';
        }

        return $this->content;
    }
}
