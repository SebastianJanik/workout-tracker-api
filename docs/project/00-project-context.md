# Workout Tracker — project context

> Starting document: what the product is and why. Working rules (environment, commands, architecture, conventions) are in `AGENTS.md`. Details live in the same folder (`docs/project/`): `01-domain-model.md`, `02-design-patterns.md`, `03-implementation-plan.md` (agentic tasks, DoD, status, ADRs).
>
> This repository (`workout-tracker-api`) contains **the backend only**. The mobile app lives in a separate repository, `workout-tracker-mobile` (ADR-012).

## 1. What the project is

An app for **coaches** to manage their **athletes** and record their progress. A coach plans and logs training sessions (gym and padel to start with, later e.g. running), session goals, notes and results.

**The core product value:** an athlete's history must be recorded so that **another coach** opening it immediately knows what this athlete can do, what to focus on and what to avoid. The "Athlete Brief" serves this purpose.

## 2. Project goals (in order of priority)

1. **Learning to work with an AI agent (Claude Code).** Seven agentic tasks: rule files, slash commands and skills, hooks, a custom MCP server, subagents, orchestration and a personal workflow. Each one has a definition of done in `03-implementation-plan.md`. This is priority #1 and wins any conflict.
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

**Far future (not now, only after everything else is done):** AI-based video analysis of padel sessions as a premium feature, requiring the user's own API key. Details in `03-implementation-plan.md` §6. We don't design for it ahead of time.

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
| Infra | Docker Compose (OrbStack): php, postgres, kafka, kafka-ui, mailpit. No PHP, Composer or Node on the host (ADR-013); how to run things: `AGENTS.md` |
| Mobile | **separate repo** `workout-tracker-mobile`: Expo + React Native + TypeScript, client generated from this repo's `openapi.json` |

## 5. Repository structure

```
workout-tracker-api/
├── AGENTS.md  CLAUDE.md          # working rules (shared); CLAUDE.md = @AGENTS.md + Claude extras
├── docker-compose.yaml  Dockerfile
├── bin/ config/ public/ migrations/<Context>/
├── src/
│   ├── SharedKernel/             # IDs, Duration, Mass, shared ports; Infrastructure: buses, outbox, inbox, Kafka
│   ├── Identity/   {Domain,Application,Infrastructure,Presentation}
│   ├── Coaching/   {…}
│   ├── TrainingLibrary/ {…}      # warm-ups (later cool-downs)
│   ├── Strength/   {…}
│   ├── Padel/      {…}
│   └── Progress/   {…, Domain/Strength/{AGENTS.md,CLAUDE.md}}   # pure core
├── tests/
├── tools/mcp-kafka/              # MCP server (Python, in Docker)
├── scripts/agent-pipeline.sh
├── docs/
│   ├── project/                  # THESE documents (00–03)
│   ├── domain/  adr/  specs/  progress/
│   ├── contracts/                # event schemas
│   ├── api/openapi.json          # contract for the mobile repo
│   └── agentic-workflow/WORKFLOW.md
├── .agents/skills/               # shared skills (any tool)
└── .claude/ {skills -> ../.agents/skills, agents, hooks, settings.json}   # Claude Code only
```

`App\` in `composer.json` points to `src/`. Contexts are sub-namespaces, e.g. `App\Strength\Domain\StrengthSession`. Layer, boundary and folder rules are in `AGENTS.md` (Architecture).

## 6. Laravel → Symfony cheat sheet

The author knows Laravel well and is learning Symfony. Starting point for analogies (how to use it: `AGENTS.md`, Working rules):

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
| Sail | our `docker-compose.yaml` + FrankenPHP | our own setup, not a ready-made package |
| Task Scheduling | Scheduler component | the schedule is Messenger messages |
| Mail / Notifications | Mailer / Notifier | similar |
| `config/*.php` | `config/packages/*.yaml` | config per bundle, per environment |
| Telescope / Debugbar | Web Profiler | — |
