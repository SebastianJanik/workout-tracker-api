# Workout Tracker — design pattern catalogue

Goal: use as many patterns as possible **where they genuinely make sense**, and know what each one teaches. The "Symfony" column shows which framework mechanism we learn along the way.

## 1. Strategic DDD

| Pattern | Where | Symfony / learning |
|---|---|---|
| Bounded Context | Identity, Coaching, TrainingLibrary, Strength, Padel, Progress | folder per context, separate Doctrine mappings, Deptrac |
| Context Map | `docs/domain/context-map.md` | — |
| Shared Kernel | `SharedKernel`: IDs, `Duration`, `Mass` | custom Doctrine types / embeddables |
| Published Language | integration events + JSON schemas | Serializer, versioning |
| Open Host Service | `AthleteAccessPolicy` from Coaching, `WarmUpCatalog` from TrainingLibrary | public services, DI aliases |
| Anti-Corruption Layer | `Progress/Infrastructure/Acl/*Translator` | — |
| Customer/Supplier | Identity → Coaching | — |
| Conformist (deliberately avoided) | Progress is **not** a conformist towards Strength and Padel | — |

## 2. Tactical DDD

| Pattern | Where |
|---|---|
| Aggregate / Aggregate Root | `StrengthSession`, `PadelMatch`, `CoachingRelationship`, `AthleteAssessment` |
| Entity | `ExerciseEntry`, `SetEntry`, `DrillBlock` |
| Value Object | `Load`, `Mass`, `Duration`, `Score`, `SuccessRate`, `Prescription`, `Email`, `MeasurementProfile` |
| Domain Event | `StrengthSessionCompleted` etc. (recorded in the aggregate, published after persisting) |
| Repository (port in the domain) | `StrengthSessionRepository` (interface) + `DoctrineStrengthSessionRepository` |
| Factory | `StrengthSessionFactory::fromTemplate()`, `SessionFactoryRegistry` |
| Domain Service | `PersonalRecordDetector`, `HandoverEligibility` |
| Specification | `ValidPadelSetScore`, `SetMatchesMeasurementProfile`, `ReadyForLoadIncrease` |
| Policy | `AthleteAccessPolicy`, `ProgressionPolicy` |
| Module | sub-folders `Domain/Catalog`, `Domain/Session` |

## 3. Architecture and integration

| Pattern | Where | Symfony / learning |
|---|---|---|
| Hexagonal (Ports & Adapters) | Domain and Application define ports, Infrastructure holds adapters | DI, interfaces → implementations |
| Layered Architecture | Domain / Application / Infrastructure / UI | Deptrac |
| CQRS | separate command, query and event buses | Messenger: multiple buses, `#[AsMessageHandler(bus: …)]` |
| Event-Driven Architecture | Kafka between contexts | Messenger + `koco/messenger-kafka` |
| **Transactional Outbox** | events go to the Doctrine `outbox` transport within the transaction; a relay moves them to Kafka | `doctrine_transaction` middleware, `DispatchAfterCurrentBusStamp` |
| **Idempotent Consumer (Inbox)** | `processed_events` checked before handling | Messenger middleware, Lock |
| Process Manager / Saga | `Handover` (invitation → acceptance → role change) | **Workflow component** (state machine) |
| Read Model / Projection | `AthleteTimeline`, `AthleteBrief`, `ExercisePerformanceHistory` | DBAL, projectors as handlers |
| Snapshot / Copy-on-Attach | `SessionWarmUp` = copy of `WarmUpRoutine` + `sourceVersion`; history immune to later library changes | — |
| Event-Carried State Transfer | permission replica in Progress (later stage) | — |
| Dead Letter Queue | `failed` transport + optionally a `*.dlq` topic | failure transport, retry strategy |
| Retry with backoff | retry via the Doctrine transport (Kafka has no native requeue) | `retry_strategy`, `multiplier` |
| Backend for Frontend (optional) | "screen" endpoints for mobile (`/mobile/athletes/{id}/brief`) | — |
| API-first / Contract | OpenAPI → generated TS client (in the mobile repo) | NelmioApiDoc |

## 4. GoF: creational

| Pattern | Where |
|---|---|
| Factory Method | `Exercise::createCustom()`, `Exercise::createSystem()` (named constructors) |
| Abstract Factory | `SessionTypeModule` creates a full set: session factory, ACL translator and timeline renderer for a given training type |
| Builder | `StrengthSessionBuilder` (tests and import), `ScoreBuilder` |
| Prototype | `SessionTemplate::instantiateFor(athleteId, date)`, "repeat last session", the warm-up copy in a session (`SessionWarmUp`) |
| Object Mother | `tests/…/Mother/StrengthSessionMother` |

## 5. GoF: structural

| Pattern | Where | Symfony / learning |
|---|---|---|
| Composite | session blocks: `SingleExerciseBlock`, `SupersetBlock`, `CircuitBlock` | — |
| Adapter | `SecurityUser` (domain `User` → `UserInterface`), Kafka transport | Security |
| Decorator | `CachedExerciseCatalog`, `LoggingCommandBus` | `#[AsDecorator]` |
| Facade | `CoachingFacade`, a context's public API | — |
| Proxy | Doctrine lazy loading, lazy services | `lazy: true` |
| Bridge (optional) | history export: report abstraction × format (PDF/CSV) | — |

## 6. GoF: behavioural

| Pattern | Where | Symfony / learning |
|---|---|---|
| Strategy | `E1rmFormula`, `ProgressionRule`, `TimelineEntryRenderer`, `MeasurementProfile` | **tagged services** + `#[AutowireIterator]` / `#[AutowireLocator]` |
| State | `SessionStatus`: Planned, InProgress, Completed, Cancelled (state classes in the domain) | comparison with Workflow (ADR-009) |
| Command | CQRS commands (`LogSet`, `CompleteStrengthSession`) | Messenger |
| Mediator | command bus | Messenger |
| Chain of Responsibility | Messenger middleware (validation, transaction, inbox, logging) | custom `MiddlewareInterface` |
| Observer | domain event subscribers | EventDispatcher, `#[AsEventListener]` |
| Template Method | `AbstractProjector::project()` (shared idempotency skeleton, `apply()` hook) | — |
| Visitor | `SessionBlockVisitor`: computing session volume and duration over the block tree (Composite) | — |
| Null Object | `NoPreviousPerformance` instead of `null` for "recent results" | — |
| Iterator | `AthleteHistory` as an iterable collection | `IteratorAggregate` |
| Memento (optional) | session draft with undo in mobile | — |

## 7. Enterprise patterns (PoEAA)

| Pattern | Where |
|---|---|
| Data Mapper | Doctrine + XML mapping (the domain doesn't know the ORM) |
| Unit of Work | `EntityManager` within `doctrine_transaction` |
| Identity Map | Doctrine |
| Embedded Value | `Load`, `Score` as embeddables |
| Registry / Plugin | `SessionTypeRegistry` (new training type = new tagged module) |
| Quantity | `Mass` (value + unit; kg canonically) |
| Special Case | `Bodyweight` as a special `Load` |

## 8. Testing patterns

| Pattern | Where |
|---|---|
| Contract Tests (golden scenarios) | `tests/Progress/Contract` (protected by a hook) |
| Object Mother / Test Data Builder | `tests/**/Mother`, `*Builder` |
| In-memory adapters | `InMemoryStrengthSessionRepository`, `in-memory://` transport |
| Approval / Snapshot | comparing the analysis report to a reference file |
| Mutation testing | Infection on `Progress/Domain/Analytics` |
| Architecture tests | Deptrac (+ possibly PHPat) |

## 9. Anti-patterns we watch for (for the code reviewer)

- Anaemic model: setters instead of domain methods (`$session->complete($goalsOutcome, $clock)`, not `setStatus()`).
- God aggregate: the exercise catalogue is **not** part of the session; the session holds an `exerciseId` and a name snapshot.
- Transactions spanning multiple aggregates: aggregates communicate through events.
- Entities in messages, Doctrine attributes in the domain, `new \DateTimeImmutable()` in the domain.
- Controller with logic: a controller only maps the DTO and delegates to the bus.
- `null` meaning "absent": use a domain exception or a Null Object instead.

## 10. Symfony learning map (component → where)

| Component | Usage |
|---|---|
| Messenger | 3 buses, middleware, outbox, Kafka, retry, failure transport, inbox |
| Security | JWT, `SecurityUser` adapter, **Voters** (`AthleteVoter`: can this coach read/write) |
| Doctrine ORM / Migrations | XML mapping, embeddables, custom types (`AthleteIdType`), migrations |
| Validator | input DTO validation, custom constraints |
| Serializer | DTO ↔ JSON, integration events |
| HttpKernel | `#[MapRequestPayload]`, `#[MapQueryString]`, ExceptionListener → problem+json |
| DependencyInjection | autowiring, tagged iterators/locators, `#[AsDecorator]`, compiler pass for `SessionTypeRegistry` |
| Workflow | `Handover`, invitations |
| Clock | `ClockInterface` in the domain, `MockClock` in tests |
| Uid | UUIDv7 for IDs and `eventId` |
| Scheduler | weekly progress summary, invitation expiry |
| Mailer / Notifier | coach or athlete invitation (mailpit locally) |
| Lock / RateLimiter | recalculation idempotency, login rate limit |
| Console | exercise seed import commands, `app:progress:recalculate` |
| NelmioApiDoc | OpenAPI for the mobile client (`openapi.json` published for the mobile repo) |
