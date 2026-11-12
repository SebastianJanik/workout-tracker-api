<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

final readonly class ExerciseId
{
    public function __construct(public string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('An exercise id cannot be empty.');
        }
    }
}
