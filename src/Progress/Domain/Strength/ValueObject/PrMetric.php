<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

enum PrMetric: string
{
    /** Estimated one-rep max in kilograms. */
    case E1rm = 'e1rm';

    /** Repetitions in a single set. */
    case Reps = 'reps';

    /** Seconds held in a single set, compared only with sets at the same load. */
    case Duration = 'duration';
}
