# AGENTS.md

Working rules for this repo, for any coding agent or developer. Claude Code reads it through `CLAUDE.md` (`@AGENTS.md`), which adds only Claude-specific extras. Each topic lives in one file:

| Topic | File |
|---|---|
| Working rules: environment, commands, architecture, conventions, how to answer | this file |
| What the product is, goals, requirements, stack, repo layout, Laravel→Symfony cheat sheet | `docs/project/00-project-context.md` (read first) |
| Domain model, contexts, events | `docs/project/01-domain-model.md` |
| Design pattern catalogue | `docs/project/02-design-patterns.md` |
| Agentic tasks 0-7: requirements, DoD, status; ADR list | `docs/project/03-implementation-plan.md` |
| Analytics decisions | `docs/progress/DECISIONS.md` |

This repo is the **backend only**. The mobile app lives in a separate repo, `workout-tracker-mobile` (ADR-012). Currently built: Symfony 8.1 infrastructure (Doctrine, Messenger, Kafka, `GET /api/health`), quality tooling and the pure Progress core for strength, and the Identity context (register, login, refresh, `GET /api/me`; JWT via Security, OpenAPI via Nelmio). Check `composer.json` and the task statuses in the plan before assuming something exists (no Coaching or other contexts yet).

## Nested rule files

- **Reading:** before editing code in a directory, check whether it (or a parent) has an `AGENTS.md` and follow it. Nested rules add to this file and never override it. Claude Code loads a nested file only through its `CLAUDE.md`.
- **Creating:** add one only when a directory has rules that apply nowhere else and a mistake there would be costly (e.g. `src/Progress/Domain/Strength/`). Not one per folder.
- **Writing:** each topic lives in exactly one file. Never restate a root rule; link to it in one line at most. Keep it short (about 10 lines) and concrete.
- **Wiring:** the rules go in `<dir>/AGENTS.md`, and `<dir>/CLAUDE.md` contains only `@AGENTS.md`.

## Environment: everything runs in Docker

There is no PHP, Composer or Node on the host. Run every PHP/Composer/console command inside the container, never on the host (ADR-013):

```
docker compose up -d                          # app at http://localhost:8000 (FrankenPHP)
docker compose exec php bin/console ...
docker compose exec php composer require ...
docker compose exec -T php ...                # use -T in scripts/hooks (no TTY)
```

The one host requirement besides Docker and git is `python3` (preinstalled on macOS with the Xcode Command Line Tools): the Claude Code hook in `.claude/hooks/` runs on the host and uses it to read the tool call JSON.

Compose file is `docker-compose.yaml`, image is `Dockerfile` (repo root). `composer.json` has `extra.symfony.docker = false`, so Flex must not generate Docker files.

Services: `php` (PHP 8.4, `pdo_pgsql intl zip opcache rdkafka`), `postgres` 17 (db/user/password `workout`/`app`/`app`, port 5432), `kafka` (KRaft), `kafka-ui` (:8080), `mailpit` (:8025).

Quality tools (run in the container):

```
docker compose exec -T php vendor/bin/phpunit                          # default suite
docker compose exec -T php vendor/bin/phpunit --testsuite progress-contract
docker compose exec -T php vendor/bin/phpstan analyse
docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec -T php vendor/bin/deptrac analyse
docker compose exec -T php bin/console lint:container
docker compose exec -T php bin/console doctrine:schema:validate
docker compose exec -T php bin/console messenger:consume outbox kafka_progress -vv
docker compose exec -T php bin/console nelmio:apidoc:dump --format=json > docs/api/openapi.json   # once Nelmio is installed
```

A pre-commit hook in `.githooks/` (`core.hooksPath`) blocks secrets (key files, local env files, private keys), checks staged files for debug dumps, then runs php-cs-fixer, PHPStan and Deptrac on staged PHP files. Quality checks run only at commit time, not after every agent edit. `.env` is committed and holds defaults only; real secrets go in `.env.local` (git-ignored) or the secrets vault, read via `%env(...)%`. When something fails, read `var/log/dev.log` and the web profiler (`/_profiler`) before changing code.

## Architecture

- DDD bounded contexts as sub-namespaces of `App\` (`src/<Context>/{Domain,Application,Infrastructure,Presentation}`): Identity, Coaching, TrainingLibrary, Strength, Padel, Progress, plus SharedKernel (IDs, `Duration`, `Mass`, shared ports, buses, outbox, inbox, Kafka). Strength and Padel sessions are separate models, not one table with a flag. New training types must be addable without modifying existing ones (Open/Closed).
- The domain layer does not depend on Symfony or Doctrine (PSR interfaces such as `ClockInterface` are fine); Deptrac enforces this. Doctrine mappings are XML, outside the domain. Each context registers its own Doctrine types and XML mapping directory in its `src/<Context>/<Context>Bundle.php` (`prependExtension()`), never in the shared `config/packages/doctrine.yaml`. Map value objects as XML embeddables, which need no registration; only identifiers need a custom DBAL type (an embeddable cannot be an id).
- Inside `Domain/` group classes by DDD building block, never flat (under the module folder if there is one). Currently used: `ValueObject/` (incl. enums), `Service/` (see `src/Progress/Domain/Strength`), `Aggregate/`, `Repository/` (ports) and `Exception/` (see `src/Identity/Domain`; `src/SharedKernel/Domain/Exception` holds the HTTP marker interfaces below). Don't create or suggest other folders up front. If a class doesn't fit (first aggregate, repository port, domain event...), propose a new folder named after its DDD building block and ask before adding it.
- Contexts talk to each other only via integration events (outbox, then Kafka, versioned schemas in `docs/contracts/`, partition key `athleteId`, idempotent inbox consumers keyed by `eventId`) or a context's public facade/query, never through another context's entities or repositories.
- Messenger has separate `command.bus`, `query.bus` and `event.bus`.
- Messages (commands, queries, events) are immutable `final readonly` DTOs holding primitives only (no entities, no objects with behaviour), so they serialize safely.
- Integration events are published only through the outbox, never dispatched straight to Kafka. Every new message type gets routing in `config/packages/messenger.yaml`, a retry/failure decision, and a test on `in-memory://`.
- One PostgreSQL database, migrations in `migrations/<Context>/` (namespace `DoctrineMigrations\<Context>`, one shared timeline). Each context owns its tables. Cross-context foreign keys are allowed in migrations (DB-level integrity, e.g. `strength_session.athlete_id` to Coaching's athlete table). The code boundary stays strict: no ORM relations across contexts, only plain ID value objects (`AthleteId`), and no JOINs into another context's tables. An FK couples schemas (migration order, test fixtures) and can reject a valid write when the referenced row arrives asynchronously from a Kafka event, so use it only where the referenced row is guaranteed to exist first.
- `Progress` analytics is a pure core: plain PHP, no I/O, injected time, deterministic.
- API contract: `docs/api/openapi.json` (exported from Nelmio) must be updated in the same commit as any API change; the mobile repo generates its client from it.

## Symfony conventions

Follow https://symfony.com/doc/current/best_practices.html:

- Use PHP attributes for framework metadata, not only on controllers: `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]`, `#[Assert\...]`, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or XML routing.
- Rely on autowiring and autoconfiguration. Where a type-hint can't express it, stay in the class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one of several implementations). A YAML service definition is the last resort.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Validator, Serializer, Messenger, Security (voters, authenticators), Twig `path()`/`url()`. Before hand-writing infrastructure (locks, queues, caches, HTTP clients, mailers, schedulers) check whether a Symfony component covers it, and propose it (see Working rules on packages).
- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]`, not by hand with `json_decode()` or `SerializerInterface`.
- Constructor property promotion; `readonly` for DTOs and value objects.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion, never a hand-built flag or lock file.
- When a package is approved, install it with `composer require` and let the Flex recipe register it. Don't hand-edit `config/bundles.php` or hand-write a bundle's base config. Check `git status` afterwards for recipe leftovers (Docker files, `src/Entity`).
- Code style is the `@Symfony` php-cs-fixer ruleset (config in `.php-cs-fixer.dist.php`).
- Every PHP file starts with `declare(strict_types=1);`. Classes are `final` unless designed for extension.
- Value objects and aggregates are built through named constructors (`Mass::fromKilograms()`), not public constructors with loose primitives.
- Domain code throws domain exceptions for invalid states and missing things; it doesn't return `null` to signal failure.
- A domain exception gets its HTTP meaning by implementing a marker from `App\SharedKernel\Domain\Exception`: `Conflict` (409), `NotFound` (404), `InvalidInput` (422). `DomainExceptionListener` maps them for the whole app. Never list exceptions one by one in `framework.yaml`, and never make a domain exception extend an `HttpException`. A new status means a new marker plus one line in the listener. An exception with no marker stays a 500.
- Controllers only map the request to a message/DTO, dispatch, and map the result back. No business logic.

## Testing

Functional/HTTP tests extend `WebTestCase`; service-level tests extend `KernelTestCase`. A feature isn't done until a test exercises it the way a caller would (an HTTP request for a controller, a service call for a service), not just "it didn't throw." Contract tests in `progress-contract` are never changed to make them pass; a deliberate change is recorded in `docs/progress/DECISIONS.md`.

No in-memory repositories or fake implementations of our own ports (ADR-015): handler and service tests extend `KernelTestCase` and use the real repository, hasher and test database (DAMA rolls each test back). Pure domain tests need no kernel. Doubles are only for the system boundary (Kafka, mail, external HTTP, time via `MockClock`).

Workflow: write the failing test first (TDD), then the code. After every change run the tests of the affected context (e.g. `vendor/bin/phpunit --filter Progress`), and the full set of quality tools before finishing.

## Discover, don't guess

Look things up in the project instead of relying on memory: `bin/console about`, `debug:router`, `debug:container`, `debug:autowiring <name>`, `debug:config <bundle>`, `config:dump-reference <bundle>`, `lint:container`, and the installed source under `vendor/`. Docs: https://symfony.com/doc/current/ (the version in `composer.json`).

## Working rules

- Language: code, names, commits, docs and conversation are all in English.
- Don't add Composer packages without asking.
- Never edit a migration that has been released (committed to `main`); add a new one.
- Never read or modify `.env.local`.
- Never delete Kafka topics or Docker volumes, and never reset consumer group offsets. Destructive operations on shared state need explicit approval.
- When in doubt about any of the above, ask.
- Every new feature names the design patterns it uses (see `02-design-patterns.md`) in the PR description.
- Don't change ADR decisions without discussion; a new decision means a new ADR (`docs/project/03-implementation-plan.md` §5).
- When unsure about context boundaries, check `docs/project/01-domain-model.md`.
- If the task doesn't say which persistence, interface or auth to use, the ADRs already decide it (Doctrine ORM, controllers + DTOs, JWT); otherwise ask.
- The author knows Laravel well and is learning Symfony. Keep answers short and lead with the main point. Explain Symfony concepts with Laravel analogies (cheat sheet in `00-project-context.md` §6) and say where the analogy breaks. Explain a component briefly the first time it is used, with a link to symfony.com docs.
- Name any design pattern you apply (catalogue in `02-design-patterns.md`), where it lives in the code, and what it teaches.
