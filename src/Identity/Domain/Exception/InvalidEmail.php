<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\SharedKernel\Domain\Exception\InvalidInput;
use App\SharedKernel\Domain\Exception\TranslatableDomainException;

final class InvalidEmail extends TranslatableDomainException implements InvalidInput
{
    public static function because(string $raw): self
    {
        return new self('identity.email.invalid', ['%email%' => $raw]);
    }
}
