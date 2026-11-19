<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

/**
 * Query: the public profile of the user with this email (the authenticated user's login name).
 */
final readonly class GetUserProfile
{
    public function __construct(public string $email)
    {
    }
}
