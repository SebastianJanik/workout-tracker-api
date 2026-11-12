<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

/**
 * How an exercise is measured. It is fixed per exercise: pull-ups are always Reps, weighted pull-ups
 * are a different exercise (LoadAndReps). It decides which numbers of a set are used and what a PR means.
 */
enum Measurement: string
{
    /** Reps with a load (barbell squat, weighted pull-up). PR: estimated one-rep max. */
    case LoadAndReps = 'load_and_reps';

    /** Reps only (pull-up, push-up). Load is not part of the exercise. PR: most reps in one set. */
    case Reps = 'reps';

    /** A hold without load (plank, dead hang). PR: longest hold. */
    case Duration = 'duration';

    /** A hold with a load (weighted dead hang). PR: longest hold, compared only at the same load. */
    case LoadAndDuration = 'load_and_duration';

    public function metric(): PrMetric
    {
        return match ($this) {
            self::LoadAndReps => PrMetric::E1rm,
            self::Reps => PrMetric::Reps,
            self::Duration, self::LoadAndDuration => PrMetric::Duration,
        };
    }
}
