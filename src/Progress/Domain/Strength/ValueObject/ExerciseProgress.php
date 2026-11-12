<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

use App\SharedKernel\Domain\ValueObject\Mass;

/**
 * One series of results. An exercise has one series, except a loaded hold (LoadAndDuration): every
 * load is its own series, so 45 s at 20 kg is never compared with 60 s at 10 kg.
 */
final readonly class ExerciseProgress
{
    /**
     * @param Mass|null            $load            the load of the series; only set for LoadAndDuration
     * @param float                $best            best value up to the end of the period (unit depends on $metric)
     * @param float                $volumeKg        sum of reps x load of counted sets inside the period; 0 unless LoadAndReps
     * @param int                  $totalSeconds    sum of held seconds of counted sets inside the period; 0 unless a hold
     * @param list<PersonalRecord> $personalRecords records set inside the period, oldest first
     */
    public function __construct(
        public ExerciseId $exerciseId,
        public PrMetric $metric,
        public ?Mass $load,
        public float $best,
        public float $volumeKg,
        public int $totalSeconds,
        public array $personalRecords,
    ) {
    }
}
