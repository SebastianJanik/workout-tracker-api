<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\Exception\NotFound;

final class UserNotFound extends \DomainException implements NotFound
{
    public static function withEmail(Email $email): self
    {
        return new self(sprintf('No user with email "%s".', $email->value()));
    }
}
