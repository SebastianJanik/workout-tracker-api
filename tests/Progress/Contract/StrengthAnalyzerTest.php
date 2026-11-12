<?php

declare(strict_types=1);

namespace App\Tests\Progress\Contract;

use App\Progress\Domain\Strength\Service\EpleyFormula;
use App\Progress\Domain\Strength\Service\StrengthAnalyzer;
use App\Progress\Domain\Strength\ValueObject\AnalysisPeriod;
use App\Progress\Domain\Strength\ValueObject\ExerciseEntry;
use App\Progress\Domain\Strength\ValueObject\ExerciseId;
use App\Progress\Domain\Strength\ValueObject\ExerciseProgress;
use App\Progress\Domain\Strength\ValueObject\Measurement;
use App\Progress\Domain\Strength\ValueObject\PerformedSet;
use App\Progress\Domain\Strength\ValueObject\PrMetric;
use App\Progress\Domain\Strength\ValueObject\SessionId;
use App\Progress\Domain\Strength\ValueObject\SetType;
use App\Progress\Domain\Strength\ValueObject\StrengthReport;
use App\Progress\Domain\Strength\ValueObject\StrengthSessionRecord;
use App\SharedKernel\Domain\ValueObject\Mass;
use PHPUnit\Framework\TestCase;

/**
 * Golden scenarios for the strength analytics. They are not changed to make an implementation pass.
 */
final class StrengthAnalyzerTest extends TestCase
{
    private function createAnalyzer(): StrengthAnalyzer
    {
        return new StrengthAnalyzer(new EpleyFormula());
    }

    public function testEmptyHistoryGivesEmptyReport(): void
    {
        $report = $this->analyze([]);

        self::assertSame([], $report->exercises);
        self::assertSame([], $report->sessionsConsidered);
    }

    public function testSameInputGivesIdenticalReport(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->squat([$this->working(5, 100), $this->working(5, 100)])]),
            $this->session('s2', '2026-01-12', [$this->squat([$this->working(5, 105)])]),
        ];

        self::assertEquals($this->analyze($sessions), $this->analyze($sessions));
    }

    public function testRampUpSetsDoNotAffectResults(): void
    {
        $withRampUp = [
            $this->session('s1', '2026-01-05', [$this->squat([
                new PerformedSet(SetType::RampUp, 5, Mass::fromKilograms(200)),
                $this->working(5, 100),
            ])]),
        ];
        $withoutRampUp = [
            $this->session('s1', '2026-01-05', [$this->squat([$this->working(5, 100)])]),
        ];

        self::assertEquals($this->exercise($this->analyze($withoutRampUp), 'squat'), $this->exercise($this->analyze($withRampUp), 'squat'));
    }

    public function testSameLoadInKilogramsAndPoundsGivesSameResult(): void
    {
        $kilograms = [
            $this->session('s1', '2026-01-05', [$this->squat([
                new PerformedSet(SetType::Working, 5, Mass::fromKilograms(100)),
            ])]),
        ];
        $pounds = [
            $this->session('s1', '2026-01-05', [$this->squat([
                new PerformedSet(SetType::Working, 5, Mass::fromPounds(220.462)),
            ])]),
        ];

        self::assertEquals($this->analyze($kilograms), $this->analyze($pounds));
    }

    public function testPersonalRecordOnlyWhenStrictlyGreaterThanPreviousBest(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->squat([$this->working(5, 100)])]),
            $this->session('s2', '2026-01-12', [$this->squat([$this->working(5, 100)])]),
            $this->session('s3', '2026-01-19', [$this->squat([$this->working(5, 105)])]),
        ];

        $squat = $this->exercise($this->analyze($sessions), 'squat');

        self::assertCount(1, $squat->personalRecords);
        self::assertSame('s3', $squat->personalRecords[0]->sessionId->value);
        self::assertEqualsWithDelta(105 * (1 + 5 / 30), $squat->best, 0.01);
    }

    public function testBodyweightExerciseUsesRepsForPersonalRecord(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->pullUp([new PerformedSet(SetType::Working, 8, null)])]),
            $this->session('s2', '2026-01-12', [$this->pullUp([new PerformedSet(SetType::Working, 10, null)])]),
        ];

        $pullUp = $this->exercise($this->analyze($sessions), 'pull-up');

        self::assertSame(PrMetric::Reps, $pullUp->metric);
        self::assertSame(10.0, $pullUp->best);
        self::assertCount(1, $pullUp->personalRecords);
        self::assertSame('s2', $pullUp->personalRecords[0]->sessionId->value);
    }

    public function testOrderOfSessionsOnTheSameDayDoesNotChangeTheResult(): void
    {
        $light = $this->session('s1', '2026-01-12', [$this->squat([$this->working(5, 100)])]);
        $heavy = $this->session('s2', '2026-01-12', [$this->squat([$this->working(5, 110)])]);
        $before = $this->session('s0', '2026-01-05', [$this->squat([$this->working(5, 90)])]);

        $a = $this->analyze([$before, $light, $heavy]);
        $b = $this->analyze([$heavy, $before, $light]);

        self::assertEquals($a, $b);
        self::assertCount(1, $this->exercise($a, 'squat')->personalRecords);
    }

    public function testSetsWithZeroRepsOrNoLoadDoNotBreakTheCalculation(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->squat([
                new PerformedSet(SetType::Working, 0, Mass::fromKilograms(100)),
                new PerformedSet(SetType::Working, 5, null),
                new PerformedSet(SetType::Working, null, Mass::fromKilograms(100)),
                new PerformedSet(SetType::Working, 5, Mass::fromKilograms(0)),
            ])]),
            $this->session('s2', '2026-01-12', [$this->squat([$this->working(5, 100)])]),
        ];

        $report = $this->analyze($sessions);
        $squat = $this->exercise($report, 'squat');

        self::assertEqualsWithDelta(500, $squat->volumeKg, 0.01);
        self::assertEqualsWithDelta(100 * (1 + 5 / 30), $squat->best, 0.01);
        self::assertSame(['s1', 's2'], $report->sessionsConsidered);
    }

    public function testTimedExerciseUsesLongestHoldForPersonalRecord(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->plank([$this->hold(40), $this->hold(30)])]),
            $this->session('s2', '2026-01-12', [$this->plank([$this->hold(40)])]),
            $this->session('s3', '2026-01-19', [$this->plank([$this->hold(55)])]),
        ];

        $plank = $this->series($this->analyze($sessions), 'plank', null);

        self::assertSame(PrMetric::Duration, $plank->metric);
        self::assertSame(55.0, $plank->best);
        self::assertSame(40 + 30 + 40 + 55, $plank->totalSeconds);
        self::assertSame(0.0, $plank->volumeKg);
        self::assertCount(1, $plank->personalRecords);
        self::assertSame('s3', $plank->personalRecords[0]->sessionId->value);
    }

    public function testLoadedHoldKeepsASeparateSeriesPerLoad(): void
    {
        $sessions = [
            // The set without a load is not a weighted hold and is ignored.
            $this->session('s1', '2026-01-05', [$this->weightedHang([$this->hold(30, 20), $this->hold(20, 10), $this->hold(60)])]),
            // 45 s at 20 kg beats 30 s at 20 kg, even though it is shorter than the 60 s above.
            $this->session('s2', '2026-01-12', [$this->weightedHang([$this->hold(45, 20), $this->hold(15, 10)])]),
        ];

        $report = $this->analyze($sessions);
        $plus10 = $this->series($report, 'weighted-dead-hang', 10);
        $plus20 = $this->series($report, 'weighted-dead-hang', 20);

        self::assertCount(2, $report->exercises);
        self::assertSame(20.0, $plus10->best);
        self::assertSame([], $plus10->personalRecords);
        self::assertSame(20 + 15, $plus10->totalSeconds);
        self::assertSame(45.0, $plus20->best);
        self::assertCount(1, $plus20->personalRecords);
        self::assertSame('s2', $plus20->personalRecords[0]->sessionId->value);
    }

    public function testRepsExerciseIgnoresLoad(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->pullUp([new PerformedSet(SetType::Working, 8, Mass::fromKilograms(10))])]),
            $this->session('s2', '2026-01-12', [$this->pullUp([new PerformedSet(SetType::Working, 9, null)])]),
        ];

        $pullUp = $this->exercise($this->analyze($sessions), 'pull-up');

        self::assertSame(9.0, $pullUp->best);
        self::assertSame(0.0, $pullUp->volumeKg);
        self::assertNull($pullUp->load);
        self::assertCount(1, $pullUp->personalRecords);
    }

    public function testExerciseMeasuredInDifferentWaysIsRejected(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [new ExerciseEntry(new ExerciseId('curl'), Measurement::LoadAndReps, [$this->working(5, 20)])]),
            $this->session('s2', '2026-01-12', [new ExerciseEntry(new ExerciseId('curl'), Measurement::Reps, [new PerformedSet(SetType::Working, 12, null)])]),
        ];

        $this->expectException(\DomainException::class);
        $this->analyze($sessions);
    }

    public function testTimedSetsWithoutDurationAreIgnored(): void
    {
        $sessions = [
            $this->session('s1', '2026-01-05', [$this->plank([
                new PerformedSet(SetType::Working, 10, null),
                new PerformedSet(SetType::Working, null, null, 0),
                new PerformedSet(SetType::Working, null, null, null),
            ])]),
        ];

        $report = $this->analyze($sessions);

        self::assertSame([], $report->exercises);
        self::assertSame(['s1'], $report->sessionsConsidered);
    }

    /**
     * @param list<StrengthSessionRecord> $sessions
     */
    private function analyze(array $sessions): StrengthReport
    {
        return $this->createAnalyzer()->analyze(
            $sessions,
            new AnalysisPeriod(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31')),
        );
    }

    private function exercise(StrengthReport $report, string $id): ExerciseProgress
    {
        foreach ($report->exercises as $exercise) {
            if ($exercise->exerciseId->value === $id) {
                return $exercise;
            }
        }

        self::fail(sprintf('Exercise "%s" is missing from the report.', $id));
    }

    private function series(StrengthReport $report, string $id, ?float $kilograms): ExerciseProgress
    {
        foreach ($report->exercises as $exercise) {
            if ($exercise->exerciseId->value === $id && $exercise->load?->grams() === (null === $kilograms ? null : Mass::fromKilograms($kilograms)->grams())) {
                return $exercise;
            }
        }

        self::fail(sprintf('Exercise "%s" with load %s is missing from the report.', $id, $kilograms ?? 'none'));
    }

    /**
     * @param list<ExerciseEntry> $exercises
     */
    private function session(string $id, string $day, array $exercises): StrengthSessionRecord
    {
        return new StrengthSessionRecord(new SessionId($id), new \DateTimeImmutable($day), $exercises);
    }

    /**
     * @param list<PerformedSet> $sets
     */
    private function squat(array $sets): ExerciseEntry
    {
        return new ExerciseEntry(new ExerciseId('squat'), Measurement::LoadAndReps, $sets);
    }

    /**
     * @param list<PerformedSet> $sets
     */
    private function pullUp(array $sets): ExerciseEntry
    {
        return new ExerciseEntry(new ExerciseId('pull-up'), Measurement::Reps, $sets);
    }

    /**
     * @param list<PerformedSet> $sets
     */
    private function plank(array $sets): ExerciseEntry
    {
        return new ExerciseEntry(new ExerciseId('plank'), Measurement::Duration, $sets);
    }

    /**
     * @param list<PerformedSet> $sets
     */
    private function weightedHang(array $sets): ExerciseEntry
    {
        return new ExerciseEntry(new ExerciseId('weighted-dead-hang'), Measurement::LoadAndDuration, $sets);
    }

    private function working(int $reps, float $kilograms): PerformedSet
    {
        return new PerformedSet(SetType::Working, $reps, Mass::fromKilograms($kilograms));
    }

    private function hold(int $seconds, ?float $kilograms = null): PerformedSet
    {
        return new PerformedSet(SetType::Working, null, null === $kilograms ? null : Mass::fromKilograms($kilograms), $seconds);
    }
}
