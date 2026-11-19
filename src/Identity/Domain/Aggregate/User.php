<?php

declare(strict_types=1);

namespace App\Identity\Domain\Aggregate;

use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\SharedKernel\Domain\ValueObject\UserId;

/**
 * Aggregate root of the Identity context: an account that can log in.
 */
final class User
{
    private const string DEFAULT_ROLE = 'ROLE_USER';

    /** @var list<string> */
    private array $roles;

    private function __construct(
        private readonly UserId $id,
        private readonly Email $email,
        private readonly HashedPassword $password,
        private readonly \DateTimeImmutable $registeredAt,
    ) {
        $this->roles = [self::DEFAULT_ROLE];
    }

    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $password,
        \DateTimeImmutable $registeredAt,
    ): self {
        return new self($id, $email, $password, $registeredAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function registeredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
