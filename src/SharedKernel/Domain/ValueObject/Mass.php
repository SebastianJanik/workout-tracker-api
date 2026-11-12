<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

/**
 * A mass stored as whole grams, so the same weight entered in kg or lb compares equal.
 */
final readonly class Mass
{
    private const float GRAMS_PER_POUND = 453.59237;

    private function __construct(private int $grams)
    {
    }

    public static function fromKilograms(float $kilograms): self
    {
        return new self(self::toGrams($kilograms * 1000));
    }

    public static function fromPounds(float $pounds): self
    {
        return new self(self::toGrams($pounds * self::GRAMS_PER_POUND));
    }

    public function grams(): int
    {
        return $this->grams;
    }

    public function kilograms(): float
    {
        return $this->grams / 1000;
    }

    private static function toGrams(float $grams): int
    {
        if ($grams < 0) {
            throw new \InvalidArgumentException('A mass cannot be negative.');
        }

        return (int) round($grams);
    }
}
