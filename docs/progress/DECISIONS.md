# Progress analytics: decisions

Current decisions behind `App\Progress\Domain\Strength`. Only decisions that are in force are listed; a changed decision is edited or removed (git history keeps the past). Every analytics change needs a contract test update (`tests/Progress/Contract`, suite `progress-contract`) and an update here.

## Scope and structure

| Topic | Decision | Notes |x
|---|---|---|
| Scope | Progress analyses strength sessions only | Padel is not analysed: success can't be measured during a game. A padel session is goals with tips and a note written at the end, kept as a plain list in the Padel context. Warm-ups live in `TrainingLibrary` and are copied into sessions, so they are not part of Progress either |
| Source of data | Analysis runs on persisted data, never on a request body | The read model is fed by `StrengthSessionCompleted`/`Corrected`. It is not built yet, so nothing in Progress is exposed over HTTP |
| Entry point | `StrengthAnalyzer::analyze(list<StrengthSessionRecord>, AnalysisPeriod): StrengthReport`, used directly | No interface: an abstraction is added when a second analyzer exists. Progress keeps its own copies of the data it needs (ACL), on purpose |
| Records | The analyzer receives one current record per session | Upserting and redelivery are storage concerns (read model, inbox). No versions or corrections in the domain |
| Confidence | None | `sessionsConsidered` already tells the caller how much data was used |
| Folder layout | `Domain/Strength` is grouped by building block: `ValueObject/` (ids, `AnalysisPeriod`, enums, records, report, `PersonalRecord`, ...) and `Service/` (`StrengthAnalyzer`, `EpleyFormula`) | No entities or aggregates in this pure core. The convention applies to every context, see `docs/project/00-project-context.md` §6 |
| Contract tests | Cover: empty history, determinism, ramp-up sets, kg/lb equality, strict PRs, bodyweight PRs, same-day order, zero reps or missing load, timed exercises, loaded holds, reps ignoring load, mixed measurements, timed sets without duration | Scenarios change only deliberately, with an update here |

## Calculation rules

| Topic | Decision | Notes |
|---|---|---|
| e1RM formula | Epley: `load × (1 + reps / 30)`; with 1 rep the load itself | A plain class (`EpleyFormula`). No alternative formulas and no interface: not planned |
| Which sets count | All except `RampUp` | Drop and ToFailure sets count |
| PR | The best value up to the end of the period. A new PR needs a value **strictly greater** than every earlier day's best | Rounded to 2 decimals before comparing, so kg/lb noise cannot create a PR |
| First result of an exercise | Not a PR; it sets the baseline | Reconsider if the Athlete Brief should show "first recorded" results |
| Same-day sessions | Compared with each other first (best wins, ties go to the lowest session id), then against earlier days | Makes the result independent of input order |
| Units | `Mass` stores whole grams (SharedKernel) | kg and lb inputs of the same weight compare equal |
| Period | Inclusive calendar days; only the day of `performedOn` matters | The analyzer has no clock. The caller passes the period |
| Measurement per exercise | `Measurement`: `LoadAndReps` (e1RM), `Reps` (most reps), `Duration` (longest hold), `LoadAndDuration` (longest hold per load) | Fixed per exercise, it comes from the exercise and not from the sets. Pull-ups are always `Reps`; weighted pull-ups are a different exercise (`LoadAndReps`), and so is a weighted dead hang (`LoadAndDuration`) |
| `Reps` exercises | PR is the most reps in one counted set; load is ignored | Bodyweight exercises |
| Duration exercises | PR is the longest hold in one counted set. A set counts only with `durationSeconds > 0`; reps and load are ignored for `Duration`, reps and duration for `LoadAndDuration` | Plank, dead hang |
| Weighted holds | `LoadAndDuration` is per load: every load is its own series (`ExerciseProgress.load`) with its own best and PRs. A set without a load is ignored | 45 s at 20 kg is compared only with other 20 kg holds. Loads are compared in whole grams |
| Volume | `volumeKg` for `LoadAndReps` (sum of reps × load; 0 for every other measurement, so `Reps` exercises have no volume); `totalSeconds` for `Duration` and `LoadAndDuration` | Load × seconds is not used. Total reps for `Reps` exercises can be added if the Athlete Brief needs it |
| Series | One series per exercise, or per exercise and load for `LoadAndDuration`. An exercise id seen with two different measurements is an error (`DomainException`) | The measurement is a fact of the exercise, so a mismatch means corrupt input |

## Open

- e1RM reliability: above which rep count does the estimate stop being reliable (commonly ~10)? Cap it?
- Progression suggestion rule (`ProgressionRule`: Linear, DoubleProgression). Not implemented yet.
- Time window for trends, and what "recent" means.
- Exercise comparability (back squat vs front squat vs box squat).
- Effect of RPE/RIR on e1RM and on PRs.
- Where the `Measurement` of an exercise comes from in the persisted flow (TrainingLibrary exercise definition, copied into the session event).
- Whether a first-ever result should count as a PR.
