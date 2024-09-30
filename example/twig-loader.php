<?php

declare(strict_types=1);

return new Twig\Environment(
    new Twig\Loader\FilesystemLoader(__DIR__, __DIR__),
);
