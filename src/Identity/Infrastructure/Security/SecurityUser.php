<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Aggregate\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapter: what Symfony Security sees of a domain {@see User}. The domain class stays free of Symfony.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $email
     * @param list<string>     $roles
     */
    public function __construct(
        private string $id,
        private string $email,
        private string $passwordHash,
        private array $roles,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self($user->id()->value(), $user->email()->value(), $user->password()->value(), $user->roles());
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function email(): string
    {
        return $this->email;
    }

    /** The email is the login name and the JWT `username` claim. */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
