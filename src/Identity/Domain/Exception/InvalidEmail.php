<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\SharedKernel\Domain\Exception\InvalidInput;

final class InvalidEmail extends \DomainException implements InvalidInput
{
    public static function because(string $raw): self
    {
        return new self(sprintf('"%s" is not a valid email address.', $raw));
    }
}
