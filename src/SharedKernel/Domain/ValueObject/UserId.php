<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

/**
 * Identifier of a user (UUID, generated as v7 by the application layer).
 * Kept as a plain validated string so the domain needs no Symfony or Doctrine class.
 */
final readonly class UserId implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalised = strtolower($value);
        if (1 !== preg_match(self::PATTERN, $normalised)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid user id (UUID expected).', $value));
        }

        return new self($normalised);
    }

    public function value(): string
    {
        return $this->value;
    }

    /** Doctrine's identity map needs identifiers to be castable to string. */
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
