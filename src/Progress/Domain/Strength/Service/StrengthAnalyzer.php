<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\Service;

use App\Progress\Domain\Strength\ValueObject\AnalysisPeriod;
use App\Progress\Domain\Strength\ValueObject\ExerciseId;
use App\Progress\Domain\Strength\ValueObject\ExerciseProgress;
use App\Progress\Domain\Strength\ValueObject\Measurement;
use App\Progress\Domain\Strength\ValueObject\PersonalRecord;
use App\Progress\Domain\Strength\ValueObject\PrMetric;
use App\Progress\Domain\Strength\ValueObject\StrengthReport;
use App\Progress\Domain\Strength\ValueObject\StrengthSessionRecord;
use App\SharedKernel\Domain\ValueObject\Mass;

/**
 * e1RM through the Epley formula, PR as the best value of a series, volume as the sum of reps x load
 * (or held seconds) over counted sets. See docs/progress/DECISIONS.md.
 */
final readonly class StrengthAnalyzer
{
    public function __construct(private EpleyFormula $formula)
    {
    }

    /**
     * Pure core: no I/O, no clock, no randomness. The same input always gives the same report.
     *
     * @param list<StrengthSessionRecord> $sessions the athlete's sessions, one record per session
     */
    public function analyze(array $sessions, AnalysisPeriod $period): StrengthReport
    {
        $sessions = array_values(array_filter(
            $sessions,
            static fn (StrengthSessionRecord $session): bool => !$period->endsBefore($session->performedOn),
        ));
        usort($sessions, static fn (StrengthSessionRecord $a, StrengthSessionRecord $b): int => [AnalysisPeriod::day($a->performedOn), $a->id->value] <=> [AnalysisPeriod::day($b->performedOn), $b->id->value]);

        $considered = [];
        foreach ($sessions as $session) {
            if ($period->includes($session->performedOn)) {
                $considered[] = $session->id->value;
            }
        }
        sort($considered);

        return new StrengthReport($this->progress($sessions, $period), $considered);
    }

    /**
     * @param list<StrengthSessionRecord> $sessions sorted by day, then id; none ends after the period
     *
     * @return list<ExerciseProgress>
     */
    private function progress(array $sessions, AnalysisPeriod $period): array
    {
        /** @var array<string, array{exerciseId: ExerciseId, metric: PrMetric, load: ?Mass, volumeKg: float, seconds: int, results: array<string, list<array{value: float, session: StrengthSessionRecord}>>}> $series */
        $series = [];

        foreach ($sessions as $session) {
            $day = AnalysisPeriod::day($session->performedOn);
            $inPeriod = $period->includes($session->performedOn);

            foreach ($session->exercises as $entry) {
                $measurement = $entry->measurement;
                $metric = $measurement->metric();

                foreach ($entry->sets as $set) {
                    if (!$set->type->counts()) {
                        continue;
                    }
                    $reps = null !== $set->reps && $set->reps > 0 ? $set->reps : null;
                    $seconds = null !== $set->durationSeconds && $set->durationSeconds > 0 ? $set->durationSeconds : null;
                    $load = null !== $set->load && $set->load->grams() > 0 ? $set->load : null;

                    // Only the numbers the exercise is measured by are used; the rest of the set is ignored.
                    [$value, $seriesLoad] = match ($measurement) {
                        Measurement::LoadAndReps => [null !== $reps && null !== $load ? round($this->formula->estimate($load, $reps), 2) : null, null],
                        Measurement::Reps => [null !== $reps ? (float) $reps : null, null],
                        Measurement::Duration => [null !== $seconds ? (float) $seconds : null, null],
                        Measurement::LoadAndDuration => [null !== $seconds && null !== $load ? (float) $seconds : null, $load],
                    };
                    if (null === $value) {
                        continue;
                    }

                    // One series per exercise; a loaded hold has one series per load.
                    $key = sprintf('%s|%d', $entry->exerciseId->value, $seriesLoad?->grams() ?? 0);
                    $series[$key] ??= ['exerciseId' => $entry->exerciseId, 'metric' => $metric, 'load' => $seriesLoad, 'volumeKg' => 0.0, 'seconds' => 0, 'results' => []];
                    if ($series[$key]['metric'] !== $metric) {
                        throw new \DomainException(sprintf('Exercise "%s" is measured in different ways; an exercise has one measurement.', $entry->exerciseId->value));
                    }

                    if ($inPeriod) {
                        if (Measurement::LoadAndReps === $measurement) {
                            $series[$key]['volumeKg'] += (int) $reps * (float) $load?->kilograms();
                        } elseif (PrMetric::Duration === $metric) {
                            $series[$key]['seconds'] += (int) $seconds;
                        }
                    }
                    $series[$key]['results'][$day][] = ['value' => $value, 'session' => $session];
                }
            }
        }

        uasort($series, static fn (array $a, array $b): int => [$a['exerciseId']->value, $a['load']?->grams() ?? 0] <=> [$b['exerciseId']->value, $b['load']?->grams() ?? 0]);

        return array_values(array_map(fn (array $data): ExerciseProgress => $this->progressFor($data, $period), $series));
    }

    /**
     * @param array{exerciseId: ExerciseId, metric: PrMetric, load: ?Mass, volumeKg: float, seconds: int, results: array<string, list<array{value: float, session: StrengthSessionRecord}>>} $data
     */
    private function progressFor(array $data, AnalysisPeriod $period): ExerciseProgress
    {
        $resultsByDay = $data['results'];
        ksort($resultsByDay);
        $best = null;
        $records = [];

        foreach ($resultsByDay as $results) {
            // Same-day results are compared with each other first, so their input order cannot matter.
            usort($results, static fn (array $a, array $b): int => [$b['value'], $a['session']->id->value] <=> [$a['value'], $b['session']->id->value]);
            $top = $results[0];

            if (null !== $best && $top['value'] > $best && $period->includes($top['session']->performedOn)) {
                $records[] = new PersonalRecord($top['session']->id, $top['session']->performedOn, $top['value']);
            }
            $best = null === $best ? $top['value'] : max($best, $top['value']);
        }

        return new ExerciseProgress($data['exerciseId'], $data['metric'], $data['load'], (float) $best, round($data['volumeKg'], 2), $data['seconds'], $records);
    }
}
