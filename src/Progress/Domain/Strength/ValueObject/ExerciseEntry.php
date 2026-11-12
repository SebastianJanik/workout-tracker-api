<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

final readonly class ExerciseEntry
{
    /**
     * @param list<PerformedSet> $sets
     */
    public function __construct(
        public ExerciseId $exerciseId,
        public Measurement $measurement,
        public array $sets,
    ) {
    }
}
