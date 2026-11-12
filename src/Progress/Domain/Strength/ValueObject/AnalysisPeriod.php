<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

/**
 * An inclusive range of calendar days.
 */
final readonly class AnalysisPeriod
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
        if (self::day($from) > self::day($to)) {
            throw new \InvalidArgumentException('The period must not end before it starts.');
        }
    }

    public function includes(\DateTimeImmutable $day): bool
    {
        return self::day($day) >= self::day($this->from) && !$this->endsBefore($day);
    }

    public function endsBefore(\DateTimeImmutable $day): bool
    {
        return self::day($day) > self::day($this->to);
    }

    public static function day(\DateTimeImmutable $moment): string
    {
        return $moment->format('Y-m-d');
    }
}
