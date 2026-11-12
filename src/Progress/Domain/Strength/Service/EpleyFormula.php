<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\Service;

use App\SharedKernel\Domain\ValueObject\Mass;

/**
 * Estimates the one-rep max, in kilograms, from a load lifted for a number of reps.
 */
final readonly class EpleyFormula
{
    /**
     * @param positive-int $reps
     */
    public function estimate(Mass $load, int $reps): float
    {
        if (1 === $reps) {
            return $load->kilograms();
        }

        return $load->kilograms() * (1 + $reps / 30);
    }
}
