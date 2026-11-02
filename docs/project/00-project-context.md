# Workout Tracker — project context

> Starting document. Read it before any work in this repository. Details live in the same folder (`docs/project/`): `01-domain-model.md`, `02-design-patterns.md`, `03-implementation-plan.md`.
>
> This repository (`workout-tracker-api`) contains **the backend only**. The mobile app lives in a separate repository, `workout-tracker-mobile` (ADR-012).

## 1. What the project is

An app for **coaches** to manage their **athletes** and record their progress. A coach plans and logs training sessions (gym and padel to start with, later e.g. running), session goals, notes and results.

**The core product value:** an athlete's history must be recorded so that **another coach** opening it immediately knows what this athlete can do, what to focus on and what to avoid. The "Athlete Brief" serves this purpose.

## 2. Project goals (in order of priority)

1. **Learning to work with an AI agent (Claude Code).** Seven agentic tasks: rule files, slash commands and skills, hooks, a custom MCP server, subagents, orchestration and a personal workflow. Each one **must** be completed with evidence in `docs/agentic-workflow/LOG.md`. This is priority #1 and wins any conflict.
2. **Learning Symfony.** Equally important: modern Symfony with DDD, Messenger, Security, Doctrine (XML mappings), Workflow, Scheduler and DI (tagged services, decorators).
3. **The product.** It should be genuinely useful, but it remains a training ground for goals 1 and 2.

## 3. Product requirements (MVP)

- User accounts; a coach adds and manages their athletes.
- An athlete can have several coaches (primary, assistant, viewer). An athlete can be **handed over** to another coach.
- Different training types: to start, **gym + padel (training and match)**. The model must allow adding another type (running, another sport) **without modifying existing ones** (Open/Closed).
- **A strength session and a padel session are separate models**, not one table with a flag.
- Every session has: session goals (with an outcome rating), notes, and a view of **recent results** while planning.
- **Warm-ups** are defined independently (the coach's library + built-in ones) and attached to a training session (strength or padel) as its opening part. The session stores a copy, so later changes to the warm-up don't alter history.
- **Gym:** a catalogue of predefined exercises + the coach's custom exercises. A set has: reps, load, duration, rest after the set, note. An exercise has a rest after the exercise. Supersets and circuits.
- **Padel, training:** drills, focus areas (bandeja, víbora, volley, wall exit…), success rate (e.g. 14/20), quality rating, observations.
- **Padel, match:** format, partner, opponents, score in sets and games, observations of strengths and weaknesses, "what to work on next".
- Athlete history, personal records (PRs), trends and the Athlete Brief.
- UI: React Native (Expo) on iOS, Android and web, **in a separate repository**. The backend exposes an API described with OpenAPI.

**Far future (not now, only after everything else is done):** AI-based video analysis of padel sessions as a premium feature, requiring the user's own API key. Details in `03-implementation-plan.md` §7. We don't design for it ahead of time.

## 4. Stack

| Area | Choice |
|---|---|
| Backend | PHP 8.4, **Symfony 8.1** (ADR-001; bump to 8.2 once released), started from `symfony/skeleton` (no `--webapp`), Doctrine ORM with XML mappings outside the domain, PostgreSQL 17 |
| HTTP server | **FrankenPHP** (image `dunglas/frankenphp:1-php8.4`), one container instead of nginx + php-fpm |
| Messaging | Symfony Messenger: command, query and event buses; outbox (Doctrine) → Kafka |
| Kafka | Apache Kafka in KRaft mode (no ZooKeeper), Kafka UI (kafbat), transport `koco/messenger-kafka` (requires `ext-rdkafka`, supports Symfony ^7.4 and ^8.1) |
| API | Controllers + DTOs (`#[MapRequestPayload]`), OpenAPI via NelmioApiDocBundle, JWT (lexik) + refresh tokens |
| Quality | PHPUnit, PHPStan (max), PHP-CS-Fixer, **Deptrac** (layer and context boundaries), Infection (mutation testing of the core), lefthook |
| Agent | Claude Code (VS Code extension + CLI); custom MCP server in Python (FastMCP + confluent-kafka), run in Docker |
| Infra | Docker Compose (OrbStack): php, postgres, kafka, kafka-ui, mailpit |
| Mobile | **separate repo** `workout-tracker-mobile`: Expo + React Native + TypeScript, client generated from this repo's `openapi.json` |

## 5. Environment: everything through Docker (ADR-013)

**There is no PHP, Composer or Node on the host.** Locally: `git`, OrbStack, VS Code + Claude Code, `jq` (the only exception: agent hooks run on the host).

| Instead of (Laravel / local) | We use |
|---|---|
| `php artisan …` | `docker compose exec php bin/console …` |
| `composer require …` | `docker compose exec php composer require …` |
| `vendor/bin/phpunit` | `docker compose exec php vendor/bin/phpunit` |
| `php artisan serve` | `docker compose up -d` → http://localhost:8000 |

- Files: `compose.yaml` (repo root), `docker/php/Dockerfile`.
- `composer.json` has `extra.symfony.docker = false`. Flex does **not** generate its own Docker files, because we have ours.
- In scripts and hooks, call commands with `-T` (`docker compose exec -T php …`), because there's no TTY there.

## 6. Repository structure

```
workout-tracker-api/
├── CLAUDE.md                     # main rules (task 1)
├── compose.yaml
├── docker/php/Dockerfile
├── bin/ config/ public/ migrations/
├── src/
│   ├── SharedKernel/             # IDs, Duration, Mass, shared ports
│   ├── Shared/Infrastructure/    # buses, outbox, inbox, Kafka
│   ├── Identity/   {Domain,Application,Infrastructure,UI}
│   ├── Coaching/   {…}
│   ├── TrainingLibrary/ {…}      # warm-ups (later cool-downs)
│   ├── Strength/   {…}
│   ├── Padel/      {…}
│   └── Progress/   {…, Domain/Analytics/CLAUDE.md}   # pure core
├── tests/
├── tools/mcp-kafka/              # MCP server (Python, in Docker)
├── scripts/agent-pipeline.sh
├── docs/
│   ├── project/                  # THESE documents (00–03)
│   ├── domain/  adr/  specs/  progress/
│   ├── contracts/                # event schemas
│   ├── api/openapi.json          # contract for the mobile repo
│   └── agentic-workflow/ {LOG.md, WORKFLOW.md}
└── .claude/ {commands, skills, agents, hooks, settings.json}
```

`App\` in `composer.json` points to `src/`. Contexts are sub-namespaces, e.g. `App\Strength\Domain\StrengthSession`.

## 7. Architectural rules (summary)

- **DDD:** contexts Identity, Coaching, TrainingLibrary, Strength, Padel, Progress + SharedKernel. Layers Domain / Application / Infrastructure / UI.
- The **domain** does not depend on Symfony or Doctrine. It may use PSR interfaces, e.g. `Psr\Clock\ClockInterface`. Deptrac enforces this.
- **Between contexts**, communication goes only through integration events (Kafka, versioned schemas in `docs/contracts/`) or a context's public API (facade or query). Never through another context's entities or repositories.
- **Transactional Outbox** → Kafka; idempotent consumers (inbox keyed by `eventId`). The partition key is `athleteId`, which guarantees per-athlete event ordering.
- **Pure core** Progress Analytics: plain PHP, no I/O, injected time, deterministic. Contract tests are protected by a hook.
- **Contract with mobile:** `docs/api/openapi.json` (exported from Nelmio). An API change = updating this file in the same commit; the mobile repo generates its client from it.
- **Language:** code, names, commits, documentation and conversation are all in English.

## 8. About the author (knowledge level)

- **Knows Laravel inside out. Is still learning Symfony.**
- Answer questions **simply and briefly**: the core point first, details afterwards if needed.
- Explain Symfony through **Laravel analogies** ("it's like X in Laravel, but…") and say where the analogy breaks down.

Laravel → Symfony cheat sheet (starting point for analogies):

| Laravel | Symfony | Key difference |
|---|---|---|
| Eloquent (Active Record) | Doctrine ORM (Data Mapper) | the entity doesn't know the database; saving goes through `EntityManager` (`persist` + `flush`) |
| hand-written migrations | Doctrine Migrations | generated from the mapping ↔ database diff (`make:migration`) |
| Service Container + Service Providers | DependencyInjection + `services.yaml` | autowiring by default; attributes and tags instead of providers; compiled container |
| Facades, `app()` | constructor injection | no global helpers, everything explicit in the constructor |
| Queues / Jobs, Horizon | Messenger (bus + transports), `messenger:consume` | a "job" is a message (DTO) + a separate handler; the same bus works sync and async |
| Events / Listeners | EventDispatcher, `#[AsEventListener]` | similar; we route domain events through Messenger though |
| Form Request | DTO + Validator + `#[MapRequestPayload]` | validation is attributes on the DTO |
| Policies / Gates | Security **Voters** | `isGranted('EDIT', $athlete)` ≈ `can('update', $athlete)` |
| HTTP middleware | kernel event listeners (`kernel.request`…) | no middleware stack for HTTP; Messenger has middleware though |
| `routes/*.php` | `#[Route]` on the controller | routes sit next to the action |
| API Resources | Serializer / response DTOs | normalizers instead of Resource classes |
| Sanctum / Passport | Security + lexik JWT | firewalls and authenticators in `security.yaml` |
| Artisan, `make:*` | `bin/console`, MakerBundle (`make:*`) | similar |
| Package auto-discovery + `vendor:publish` | Symfony Flex (recipes) | a recipe creates config files itself on `composer require` |
| Sail | our `compose.yaml` + FrankenPHP | our own setup, not a ready-made package |
| Task Scheduling | Scheduler component | the schedule is Messenger messages |
| Mail / Notifications | Mailer / Notifier | similar |
| `config/*.php` | `config/packages/*.yaml` | config per bundle, per environment |
| Telescope / Debugbar | Web Profiler | — |

## 9. How Claude should work in this repository

- Answer simply, with Laravel analogies (see §8).
- Run every PHP/Composer command inside the container (`docker compose exec php …`), never on the host.
- For every feature, state **which agentic task (1–7)** it covers and what should go into `LOG.md`.
- Propose **design patterns** with a justification (catalogue in `02-design-patterns.md`). Name the pattern, show where it lives in the code and say what it teaches.
- **Teach Symfony:** the first time a component is used, briefly explain what it does (Laravel equivalent), why we use it this way, and link to the symfony.com docs.
- Don't change ADR decisions without an explicit discussion. A new decision means a new ADR.
- When in doubt about context boundaries, refer to `01-domain-model.md`.
- Don't add packages (composer) without asking.

## 10. Status

- 2026-10-27: project start, no code.
- 2026-11-02: repo `workout-tracker-api` created. Symfony 8.1 (`symfony/skeleton`) runs in Docker (FrankenPHP + PostgreSQL 17), welcome page at http://localhost:8000. Decisions: two separate repositories (ADR-012), everything in Docker (ADR-013), Symfony 8.1 (ADR-001), English as the working language (ADR-014). Project documents moved to `docs/project/`.
- **Next step:** start task 1 with `/init` in Claude Code, then finish task 0 (Kafka, kafka-ui, mailpit, Messenger, `/api/health`) and task 0b.
