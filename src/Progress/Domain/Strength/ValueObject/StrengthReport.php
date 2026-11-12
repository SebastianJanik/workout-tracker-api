<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

final readonly class StrengthReport
{
    /**
     * @param list<ExerciseProgress> $exercises          sorted by exercise id, then load
     * @param list<string>           $sessionsConsidered ids of the sessions inside the period, sorted (explainability)
     */
    public function __construct(
        public array $exercises,
        public array $sessionsConsidered,
    ) {
    }
}
