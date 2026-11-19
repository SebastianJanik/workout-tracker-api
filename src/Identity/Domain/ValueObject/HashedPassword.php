<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * A password hash. The plain password never enters the domain.
 */
final readonly class HashedPassword
{
    private function __construct(private string $value)
    {
    }

    public static function fromHash(string $hash): self
    {
        if ('' === $hash) {
            throw new \InvalidArgumentException('A password hash cannot be empty.');
        }

        return new self($hash);
    }

    public function value(): string
    {
        return $this->value;
    }
}
