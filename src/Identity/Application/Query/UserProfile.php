<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

/**
 * Result of {@see GetUserProfile}: primitives only.
 */
final readonly class UserProfile
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}
