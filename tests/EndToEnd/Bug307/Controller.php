<?php

declare(strict_types=1);

namespace EndToEnd\Bug307;

use DateTimeImmutable;
use Twig\Environment;

final class Controller
{
    /**
     * @param list<array{id: int, name: string, email: string, phone: string, city: string, active: bool, score: float, rank: int, note: null|string, created: DateTimeImmutable, tags: list<string>, children: list<array{id: int, label: string, from: DateTimeImmutable, until: DateTimeImmutable, weight: float, size: int}>}> $items
     */
    public function page(Environment $environment, array $items, float $price): string
    {
        return $environment->render('EndToEnd/Bug307/page.twig', [
            'items' => $items,
            'price' => $price,
        ]);
    }
}
