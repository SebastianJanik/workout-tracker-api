# 005: Coaching — coach profile, add athlete, athlete list

**Status:** approved
**Milestone / agentic task:** M1 (closes it) / task 7 (third run through `WORKFLOW.md`); first real run of task 5 (implementer → domain-modeler → code-reviewer), so it also counts towards that task's "real problem found in 2 cases" DoD item.

## Goal

A logged-in user can create their coach profile, add athletes who have no account, and list their own athletes. Nobody can see another coach's athletes. This is the first slice of the Coaching context.

## Acceptance criteria

Each is proven by a test written first. HTTP tests use `WebTestCase`; the domain is covered by plain unit tests.

- [ ] `PUT /api/coach` with `{displayName, specialisations[]}` creates the caller's coach profile (**201**) or updates it (**200**), returning `{id, displayName, specialisations}`. One coach per user (unique `user_id`). `specialisations` values come from `SportDiscipline` (Strength, Padel); unknown value, empty name or name over 100 chars returns **422** with per-field violations.
- [ ] `GET /api/coach` returns the caller's profile, or **404** if none exists yet.
- [ ] `POST /api/athletes` with `{fullName}` returns **201** and `{id, fullName}`. The athlete has no account (`userId` null). The caller must have a coach profile, otherwise **404** (`coaching.coach.not_found`). The same call creates an active `CoachingRelationship` with role Primary.
- [ ] `GET /api/athletes?page=1&limit=20` returns only the caller's athletes (ordered by name, then id for a stable order), as `{items: [...], page, limit, total}`. Defaults: `page=1`, `limit=20`; `limit` max 100. `page < 1`, `limit < 1` or `limit > 100` returns **422** with per-field violations. A page past the end returns empty `items` with the real `total`. A second coach's athletes never appear, and `total` counts only the caller's (test with two coaches and more athletes than one page).
- [ ] `GET /api/athletes/{id}` returns **200** for the caller's athlete and **404** (not 403) for an athlete of another coach or an unknown id, so ids can't be probed.
- [ ] All new routes require a JWT (**401** without). Validation and domain errors use the translated error shape from spec 004 (keys in `coaching.en.yaml` and `coaching.pl.yaml`).
- [ ] The Coaching domain has no Symfony or Doctrine dependency (Deptrac green). Coaching reads Identity only through the `UserId` value object (no import of Identity classes).

## Out of scope

- `UserRegistered` event, outbox, Kafka, auto-creating the coach profile (specs 002/003 in the M1 split; here the coach creates the profile explicitly).
- Roles other than Primary, invitations, Active/Ended transitions, handover, `AccessGranted`/`AccessRevoked`.
- Updating or archiving an athlete (add later with a real need).
- `AthleteAccessPolicy` as a shared facade for other contexts. Here access is a query filtered by the caller's coach id; the facade is extracted when Strength needs it.
- Integration events (`AthleteRegistered` etc.).

## Affected contexts

- **Coaching** (new): `Coach`, `Athlete`, `CoachingRelationship`.
- **SharedKernel**: new `CoachId` and `AthleteId` (UUIDv7) with their DBAL types. `UserId` already exists.
- **Identity**: untouched.

## Patterns used

| Pattern | Where | Teaches |
|---|---|---|
| Aggregate Root | `Coach`, `Athlete` (`Coaching/Domain/Aggregate`) | separate aggregates referencing each other by id only |
| Value Object | `DisplayName`, `FullName`, `SportDiscipline` (enum), `CoachId`, `AthleteId` | validation in the type |
| Repository (port + adapter) | `CoachRepository`, `AthleteRepository` | Doctrine XML mapping outside the domain |
| CQRS | commands `SaveCoachProfile`, `AddAthlete` on `command.bus`; queries `GetCoachProfile`, `ListAthletes` (page, limit), `GetAthlete` on `query.bus` | read side filtered by owner, offset pagination |
| Domain exception | `CoachNotFound`, `AthleteNotFound` (NotFound marker) | translated, no `null` for failure |

`CoachingRelationship` is a small entity created together with the athlete in one handler transaction. Whether it is its own aggregate or part of `Athlete` is a question for `domain-modeler` (the domain model lists it as a separate concept).

New `Coaching/Domain` folders follow the AGENTS.md rule: `Aggregate/`, `Repository/`, `Exception/`, `ValueObject/` are already in use. Anything else is proposed in the plan first.

## Event contract (Published Language)

None.

## Mobile API (`docs/api/openapi.json`)

New: `PUT /api/coach`, `GET /api/coach`, `POST /api/athletes`, `GET /api/athletes`, `GET /api/athletes/{id}`. Additive only, so backward compatible. Exported in the same commit.

## Athlete Brief impact

None.

## Persistence

- `coaches`: `id`, `user_id` (unique), `display_name`, `specialisations` (json), `created_at`.
- `athletes`: `id`, `full_name`, `user_id` (nullable), `created_at`. Sport-specific fields (hand, padel side, level) are deliberately not modelled yet.
- `coaching_relationships`: `id`, `coach_id`, `athlete_id`, `role`, `status`, unique `(coach_id, athlete_id)`.
- Migration in `migrations/Coaching/`. Foreign keys inside Coaching are fine. `coaches.user_id` gets **no** FK to Identity's `users` (cross-context; the row is guaranteed by the JWT today, but a future event-driven flow would break an FK).

## Risks and open questions

- **Explicit `PUT /api/coach` vs event-driven creation:** chosen to keep Kafka out of this slice. When 002/003 land, the consumer can call the same `SaveCoachProfile` command.
- **Athlete sports fields:** dropped. We only track athletes for now; add the fields when a feature needs them (the domain model in `01-domain-model.md` still lists them, so no change there).
- **`GET /api/athletes` pagination:** offset-based (`page`/`limit`) with `total`, bound via `#[MapQueryString]`. Chosen over cursors because a coach's list is small and the mobile app wants page numbers; revisit if lists grow large.
- **Spec numbering:** 002 and 003 stay reserved for the event work named in spec 001; this one takes 005.
