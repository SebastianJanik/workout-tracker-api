# Workout Tracker — domain model (DDD)

## 1. Subdomains and bounded contexts

| Context | Subdomain type | Responsibility | Key aggregates |
|---|---|---|---|
| **Identity** | generic | accounts, login, roles, JWT | `User` |
| **Coaching** | supporting | coaches, athletes, coach–athlete relationships, invitations, athlete handover, permissions | `Coach`, `Athlete`, `CoachingRelationship`, `Handover` |
| **TrainingLibrary** | supporting | library of reusable training elements independent of session type: **warm-ups** (later cool-downs, mobility) | `WarmUpRoutine` |
| **Strength** | **core** | exercise catalogue, strength sessions, sets, templates | `Exercise`, `StrengthSession`, `SessionTemplate` |
| **Padel** | **core** | drill catalogue, padel training sessions, matches | `Drill`, `PadelTrainingSession`, `PadelMatch` |
| **Progress** | **core** | history, recent results, records, trends, coach assessments, Athlete Brief, **pure analytics engine** | `AthleteAssessment` + projections (read models) |
| **SharedKernel** | — | `AthleteId`, `CoachId`, `UserId` (UUIDv7), `Duration`, `Mass`, `DateRange`, PSR ports | — |

Strength and Padel are separate contexts because they speak **different languages**: set, rep and load mean something else than drill, success rate and game. A new training type (e.g. `Running`) is a new context publishing events in the same format (Published Language). Progress handles it through a new strategy, with no changes to existing code.

## 2. Context map

```
Identity ──(UserRegistered, Customer/Supplier)──▶ Coaching
Coaching ──(Open Host Service: AthleteAccessPolicy)──▶ Strength, Padel, Progress, TrainingLibrary
TrainingLibrary ──(Open Host Service: WarmUpCatalog → snapshot)──▶ Strength, Padel
Strength ─┐
Padel ────┼──(Published Language: integration events on Kafka)──▶ Progress [ACL]
(Running)─┘
Progress ──(ProgressRecalculated, PersonalRecordSet)──▶ (notifications, mobile)
```

- **Coaching → the rest:** in the MVP, permissions are checked through the in-process facade `AthleteAccessPolicy` (Open Host Service). Later, as an exercise: a local permission replica built from `coaching.events` (Event-Carried State Transfer).
- **TrainingLibrary → sessions:** a session doesn't hold a reference to a "live" warm-up. When attaching one, it fetches a **snapshot** through the `WarmUpCatalog` facade and stores a copy in its own model. Later edits to the warm-up don't change history.
- **Progress** doesn't import Strength or Padel classes. It translates events into its own model in an **Anti-Corruption Layer** (`Progress/Infrastructure/Acl/*Translator`).

## 3. Model per context

### Identity
- `User` (Aggregate Root): `UserId`, `Email` (VO), `HashedPassword` (VO), `roles`.
- Symfony Security gets a `SecurityUser` adapter in Infrastructure. The domain `User` does not implement `UserInterface`.
- Events: `UserRegistered`.

### Coaching
- `Coach`: coach profile linked to a `UserId`, specialisations (`SportDiscipline`: Strength, Padel, …).
- `Athlete`: athlete profile. May **have no account** (managed by the coach); `userId` is optional. Sports data includes dominant hand, padel side (drive/revés) and declared level.
- `CoachingRelationship`: `coachId`, `athleteId`, `CoachRole` (Primary | Assistant | Viewer), status (Invited → Active → Ended).
- `Handover` (Process Manager): coach A hands an athlete over to coach B. B accepts, B becomes Primary, A becomes Viewer or Ended. The process state is handled by **Symfony Workflow** in the application layer.
- Invariants: exactly one Primary per athlete; a Viewer cannot record sessions.
- Events: `AthleteRegistered`, `CoachAssigned`, `AccessGranted`, `AccessRevoked`, `AthleteHandedOver`.

### TrainingLibrary
- `WarmUpRoutine` (AR): `WarmUpRoutineId`, name, description/purpose (e.g. "shoulder activation before padel"), `ownerCoachId` (or System for built-in ones), `applicableTo: SportDiscipline[]` (Strength, Padel, …), **`version`** (incremented on every change), ordered list of `WarmUpItem`.
- `WarmUpItem` (VO): movement name, optional reference to a catalogue exercise (`exerciseRef`: id + name snapshot), `Dosage` (reps | duration | distance, number of rounds, "per side"), note.
- The estimated duration of the whole warm-up is computed from its items (`Duration`).
- Invariants: at least one item; positive dosages; only the owner can edit their own warm-up.
- Facade `WarmUpCatalog::snapshot(routineId): WarmUpSnapshotDto` (Open Host Service) returns the id, version and items.
- Events: `WarmUpRoutineCreated`, `WarmUpRoutineRevised`, `WarmUpRoutineArchived` (archiving instead of deleting, because session history refers to it).
- In the future the same context may hold cool-downs (`CoolDownRoutine`) and mobility blocks, using the same snapshot mechanism.

### Strength
- `Exercise` (catalogue): `ExerciseId`, name, `ExerciseOrigin` (System | Custom(ownerCoachId)), `MeasurementProfile` (VO defining what is measured: reps, load, duration, distance, bodyweight), tags (movement pattern, muscle groups, equipment). System exercises are loaded by a seed; custom ones belong to a coach.
- `StrengthSession` (Aggregate Root):
  - `athleteId`, `authorCoachId`, planned date, `SessionStatus` (Planned → InProgress → Completed | Cancelled), State pattern,
  - `SessionGoal[]` (text + `GoalOutcome` after completion: Achieved / Partial / Missed), `Note[]`,
  - optional **`SessionWarmUp`** at the start of the session (see below),
  - ordered **blocks** (Composite): `SingleExerciseBlock` | `SupersetBlock` | `CircuitBlock`, each with `restAfterBlock` (rest between exercises),
  - `ExerciseEntry`: `exerciseId` + name snapshot, `Prescription` (plan: sets × reps @ load, tempo, target RIR/RPE), `SetEntry[]` (execution),
  - `SetEntry`: number, `SetType` (RampUp | Working | Drop | ToFailure); `RampUp` is a set ramping up to the working load, **not** a library warm-up, `reps?`, `Load?` (Mass + unit, or bodyweight + added load), `duration?`, `restAfterSet`, `rpe?`, note.
- Invariants (Specification): set values match the exercise's `MeasurementProfile`; no sets recorded on a Cancelled session; completion requires at least one set; values are positive.
- `SessionWarmUp` (VO in the session, shared shape for Strength and Padel): `sourceRoutineId` + `sourceVersion` (where it came from), copied items (the coach can adjust them for this particular session without touching the library), `WarmUpOutcome` (Done | Partial | Skipped), note. Commands: `AttachWarmUp`, `AdjustWarmUp`, `RecordWarmUpOutcome`, `DetachWarmUp`.
- `SessionTemplate`: a template or "repeat last session", copied into a new session (Prototype). A template can point to a default warm-up, which is snapshotted when the session is created.
- Correcting a completed session is allowed and emits `StrengthSessionCorrected`.
- Events: `StrengthSessionPlanned`, `StrengthSessionCompleted`, `StrengthSessionCorrected`, `ExerciseCreated`.

### Padel
- `Drill` (catalogue: System | Custom), `PadelSkill[]` (Serve, Return, ForehandGround, BackhandGround, Volley, Bandeja, Víbora, Smash, Lob, Chiquita, WallExit, Positioning, Transition, Communication), category (Technical / Tactical / Physical / Mental).
- `PadelTrainingSession` (AR): optional `SessionWarmUp` at the start (same mechanism as in Strength), goals, `focusAreas: PadelSkill[]`, `DrillBlock[]` (drillId, duration, `Attempts` → `SuccessRate` VO, quality rating 1–5, observation), notes, status (State).
- `PadelMatch` (AR): date, `MatchFormat` (best of 3 sets, golden point, super tie-break), playing side, partner (`AthleteId` or external name), opponents, `Score` (VO: sets with games and tie-break; validated by the `ValidPadelSetScore` Specification: 6–0…6–4, 7–5, 7–6), result, `SkillObservation[]` (strength/weakness + comment), `focusForNext`.
- Events: `PadelTrainingCompleted`, `PadelMatchRecorded`, `PadelMatchCorrected`.

### Progress
Write side:
- `AthleteAssessment` (AR, versioned): the coach's assessment — skills 1–5 per area, **priorities** ("what to focus on"), **limitations** ("what to avoid", as a coach's note), recommendations. Each new assessment creates a new version, and the assessment history is preserved.

Read side (projections built from Kafka):
- `AthleteTimeline`: chronological entries of any type (envelope + a `TimelineEntryRenderer` per type, Strategy from a registry).
- `ExercisePerformanceHistory`: **recent results** per exercise and athlete (for planning).
- `PersonalRecords`: PR per exercise (load, e1RM, reps for bodyweight).
- `SkillTrend`: success-rate trend per padel skill.
- `AthleteBrief` (**the Athlete Brief**): current priorities, limitations, most-used warm-ups, open goals, working loads, PRs, recent matches with observations, engine recommendations.

**Pure core:** `Progress/Domain/Strength` (strength sessions only; padel is a plain session list in the Padel context)
```php
final class StrengthAnalyzer
{
    /** @param list<StrengthSessionRecord> $sessions */
    public function analyze(array $sessions, AnalysisPeriod $period): StrengthReport;
}
```
- `StrengthReport`: per exercise series (e1RM, most reps or longest hold; PR; volume or total seconds) and the list of sessions taken into account (explainability). A loaded hold is a series per load.
- `Measurement`, fixed per exercise: `LoadAndReps`, `Reps`, `Duration`, `LoadAndDuration`. A weighted variant (weighted pull-up, weighted dead hang) is a different exercise.
- Strategies: `ProgressionRule` (Linear | DoubleProgression), e.g. "all working sets hit the target reps for 2 sessions → +2.5 kg".
- Analysis always runs on **persisted** data (Kafka event → recalculation → `ProgressRecalculated`). There is no ad hoc mode. See `docs/progress/DECISIONS.md` (2026-11-09).

## 4. Integration events (Published Language)

Envelope (every event): `eventId` (UUIDv7), `eventType`, `schemaVersion`, `occurredAt`, `athleteId`, `aggregateId`, `payload`. JSON schemas in `docs/contracts/<topic>/<event>.v<N>.json`.

| Topic | Key | Events |
|---|---|---|
| `identity.events` | userId | UserRegistered |
| `library.events` | routineId | WarmUpRoutineCreated, WarmUpRoutineRevised, WarmUpRoutineArchived |
| `coaching.events` | athleteId | AthleteRegistered, CoachAssigned, AccessGranted, AccessRevoked, AthleteHandedOver |
| `strength.events` | athleteId | StrengthSessionCompleted, StrengthSessionCorrected |
| `padel.events` | athleteId | PadelTrainingCompleted, PadelMatchRecorded, PadelMatchCorrected |
| `progress.events` | athleteId | ProgressRecalculated, PersonalRecordSet |

Rules:
- Messages are immutable DTOs with primitives, never entities.
- A breaking contract change means a new schema version.
- Consumers are idempotent: `processed_events(event_id, consumer)` with a unique index.

## 5. Key flows

1. **Recording a strength session:** `CompleteStrengthSession` (command) → aggregate → domain event → mapping to an integration event → **outbox** (same transaction) → relay → `strength.events` → Progress consumer (inbox) → ACL → projection update → `StrengthAnalyzer` → `ProgressRecalculated`.
2. **Planning a session:** query `GetLastPerformance(athleteId, exerciseIds[])` reads from `ExercisePerformanceHistory` (CQRS read).
3. **Athlete handover:** `RequestHandover` → Workflow (requested → accepted | rejected | expired) → `AthleteHandedOver` → the new coach sees the `AthleteBrief`.
4. **Warm-up at the start of a session:** the coach picks a warm-up from the library → `AttachWarmUp(sessionId, routineId)` → a handler in Strength/Padel fetches a snapshot from `WarmUpCatalog` → the session aggregate stores `SessionWarmUp` (copy + source version). The session-completed event carries a warm-up summary (name, outcome), but Progress does **not** count it towards PRs, e1RM or volume.
5. **Session correction:** `*Corrected` → Progress recalculates from scratch using the history. That's why the analysis must be deterministic and the consumer idempotent.

## 6. Ubiquitous language (glossary)

| Polish (domain term) | English (code) |
|---|---|
| Trener | Coach |
| Podopieczny | Athlete |
| Relacja trenerska | CoachingRelationship |
| Przekazanie podopiecznego | Handover |
| Jednostka treningowa / sesja | Session (`StrengthSession`, `PadelTrainingSession`) |
| Mecz | Match (`PadelMatch`) |
| Ćwiczenie (siłownia) | Exercise |
| Drill (padel) | Drill |
| Seria / powtórzenie / ciężar | Set / Rep / Load |
| Przerwa po serii / po ćwiczeniu | restAfterSet / restAfterBlock |
| Plan serii | Prescription |
| Superseria / obwód | Superset / Circuit |
| Rozgrzewka (z biblioteki) | WarmUpRoutine |
| Rozgrzewka w sesji (kopia) | SessionWarmUp |
| Seria dochodząca | RampUp set |
| Cel sesji / realizacja celu | SessionGoal / GoalOutcome |
| Obszar pracy | FocusArea (PadelSkill) |
| Skuteczność | SuccessRate |
| Ocena trenera | AthleteAssessment |
| Karta podopiecznego | AthleteBrief |
| Rekord | PersonalRecord |
| Szacowany 1RM | e1RM |
