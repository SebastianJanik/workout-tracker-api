<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\ValueObject\HashedPassword;

/**
 * Port: turns a plain password into a hash. The Symfony adapter lives in Infrastructure,
 * so the application layer never sees the Security component.
 */
interface PasswordHasher
{
    public function hash(string $plainPassword): HashedPassword;
}
