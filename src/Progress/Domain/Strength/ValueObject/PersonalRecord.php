<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

final readonly class PersonalRecord
{
    public function __construct(
        public SessionId $sessionId,
        public \DateTimeImmutable $performedOn,
        public float $value,
    ) {
    }
}
