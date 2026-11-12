<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

enum SetType: string
{
    /** A set ramping up to the working load. Never counts towards PRs, e1RM or volume. */
    case RampUp = 'ramp_up';
    case Working = 'working';
    case Drop = 'drop';
    case ToFailure = 'to_failure';

    public function counts(): bool
    {
        return self::RampUp !== $this;
    }
}
