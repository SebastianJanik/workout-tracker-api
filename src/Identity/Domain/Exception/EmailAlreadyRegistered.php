<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\Exception\Conflict;
use App\SharedKernel\Domain\Exception\TranslatableDomainException;

final class EmailAlreadyRegistered extends TranslatableDomainException implements Conflict
{
    public static function withEmail(Email $email): self
    {
        return new self('identity.email.already_registered', ['%email%' => $email->value()]);
    }
}
