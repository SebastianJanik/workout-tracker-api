<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

/**
 * Progress's own view of a completed strength session (translated by the ACL, not the Strength model).
 * Only the calendar day of $performedOn matters.
 */
final readonly class StrengthSessionRecord
{
    /**
     * @param list<ExerciseEntry> $exercises
     */
    public function __construct(
        public SessionId $id,
        public \DateTimeImmutable $performedOn,
        public array $exercises,
    ) {
    }
}
