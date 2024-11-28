<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute\Fixtures;

use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        private string $firstName,
        private ?string $email,
        private bool $isAdmin,
        private bool $hasPhoneNumber,
        private ?DateTimeImmutable $lastPurchaseAt,
    ) {}

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function hasEmail(): bool
    {
        return $this->email !== null;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function hasPhoneNumber(): bool
    {
        return $this->hasPhoneNumber;
    }

    public function getLastPurchaseAt(): ?DateTimeImmutable
    {
        return $this->lastPurchaseAt;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles()
    {
        return ['ROLE_USER', 'ROLE_ADMIN'];
    }

    public function getRelativeBirthday(DateTimeImmutable $relativeTo): int
    {
        return 11;
    }
}
