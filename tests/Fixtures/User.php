<?php

declare(strict_types=1);

namespace TwigStan\Fixtures;

use DateTimeImmutable;

final class User
{
    public function __construct(public string $firstName, public string $lastName) {}

    public function getName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function hasEmail(): bool
    {
        return false;
    }

    public function getLastPurchaseAt(): ?DateTimeImmutable
    {
        return mt_rand(0, 1) === 1 ? new DateTimeImmutable() : null;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles()
    {
        return ['ROLE_USER', 'ROLE_ADMIN'];
    }
}
