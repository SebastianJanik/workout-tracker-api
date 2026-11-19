<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\Exception\Conflict;

final class EmailAlreadyRegistered extends \DomainException implements Conflict
{
    public static function withEmail(Email $email): self
    {
        return new self(sprintf('A user with email "%s" is already registered.', $email->value()));
    }
}
