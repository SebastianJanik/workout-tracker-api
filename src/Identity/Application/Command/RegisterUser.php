<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

/**
 * Command: create an account. Carries the PLAIN password, so it is handled synchronously on
 * `command.bus` and must never be routed to a queue (see config/packages/messenger.yaml).
 */
final readonly class RegisterUser
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $plainPassword,
    ) {
    }
}
