<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

use App\SharedKernel\Domain\ValueObject\Mass;

/**
 * One recorded set. Reps, load or duration may be missing (the coach did not log them); a set
 * without the numbers its exercise is measured by is kept in the history but contributes nothing.
 */
final readonly class PerformedSet
{
    public function __construct(
        public SetType $type,
        public ?int $reps,
        public ?Mass $load,
        public ?int $durationSeconds = null,
    ) {
    }
}
