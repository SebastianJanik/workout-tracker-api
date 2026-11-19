<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

/**
 * Result of {@see RegisterUser}: primitives only.
 */
final readonly class RegisteredUser
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}
