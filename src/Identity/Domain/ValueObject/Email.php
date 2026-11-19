<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidEmail;

/**
 * An email address, trimmed and lower-cased so lookups and the unique index are case-insensitive.
 */
final readonly class Email
{
    /**
     * @param non-empty-string $value
     */
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $normalised = strtolower(trim($raw));
        if ('' === $normalised || false === filter_var($normalised, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmail::because($raw);
        }

        return new self($normalised);
    }

    /** @return non-empty-string */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
