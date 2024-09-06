<?php

declare(strict_types=1);

namespace TwigStan\Fixtures;

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

    /**
     * @return array<int, string>
     */
    public function getRoles()
    {
        return ['ROLE_USER', 'ROLE_ADMIN'];
    }
}
