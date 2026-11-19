<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\Email;
use App\SharedKernel\Domain\Exception\NotFound;
use App\SharedKernel\Domain\Exception\TranslatableDomainException;

final class UserNotFound extends TranslatableDomainException implements NotFound
{
    public static function withEmail(Email $email): self
    {
        return new self('identity.user.not_found', ['%email%' => $email->value()]);
    }
}
