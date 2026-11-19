<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Aggregate\User;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\ValueObject\Email;

/**
 * Port: how the domain stores and finds users. The Doctrine adapter lives in Infrastructure.
 */
interface UserRepository
{
    /**
     * @throws EmailAlreadyRegistered when the email is taken (the unique index is the final guard)
     */
    public function add(User $user): void;

    public function existsByEmail(Email $email): bool;

    /**
     * @throws UserNotFound
     */
    public function getByEmail(Email $email): User;
}
