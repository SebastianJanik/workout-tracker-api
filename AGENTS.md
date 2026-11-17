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

This repo is the **backend only**. The mobile app lives in a separate repo, `workout-tracker-mobile` (ADR-012). Currently built: Symfony 8.1 infrastructure (Doctrine, Messenger, Kafka, `GET /api/health`), quality tooling and the pure Progress core for strength. Check `composer.json` and the task statuses in the plan before assuming something exists (no Security, Nelmio or other contexts yet).

## Environment: everything runs in Docker

There is no PHP, Composer or Node on the host. Run every PHP/Composer/console command inside the container, never on the host (ADR-013):

```
docker compose up -d                          # app at http://localhost:8000 (FrankenPHP)
docker compose exec php bin/console ...
docker compose exec php composer require ...
docker compose exec -T php ...                # use -T in scripts/hooks (no TTY)
```

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

A pre-commit hook in `.githooks/` (`core.hooksPath`) checks for debug dumps and code style. `.env` is committed and holds defaults only; real secrets go in `.env.local` (git-ignored) or the secrets vault, read via `%env(...)%`. When something fails, read `var/log/dev.log` and the web profiler (`/_profiler`) before changing code.

## Architecture

- DDD bounded contexts as sub-namespaces of `App\` (`src/<Context>/{Domain,Application,Infrastructure,Presentation}`): Identity, Coaching, TrainingLibrary, Strength, Padel, Progress, plus SharedKernel (IDs, `Duration`, `Mass`, shared ports, buses, outbox, inbox, Kafka). Strength and Padel sessions are separate models, not one table with a flag. New training types must be addable without modifying existing ones (Open/Closed).
- The domain layer does not depend on Symfony or Doctrine (PSR interfaces such as `ClockInterface` are fine); Deptrac enforces this. Doctrine mappings are XML, outside the domain.
- Inside `Domain/` group classes by DDD building block, never flat (under the module folder if there is one). Currently used: `ValueObject/` (incl. enums) and `Service/` (see `src/Progress/Domain/Strength`). Don't create or suggest other folders up front. If a class doesn't fit (first aggregate, repository port, domain event...), propose a new folder named after its DDD building block and ask before adding it.
- Contexts talk to each other only via integration events (outbox, then Kafka, versioned schemas in `docs/contracts/`, partition key `athleteId`, idempotent inbox consumers keyed by `eventId`) or a context's public facade/query, never through another context's entities or repositories.
- Messenger has separate `command.bus`, `query.bus` and `event.bus`.
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

## Testing

Functional/HTTP tests extend `WebTestCase`; service-level tests extend `KernelTestCase`. A feature isn't done until a test exercises it the way a caller would (an HTTP request for a controller, a service call for a service), not just "it didn't throw." Contract tests in `progress-contract` are never changed to make them pass; a deliberate change is recorded in `docs/progress/DECISIONS.md`.

## Discover, don't guess

Look things up in the project instead of relying on memory: `bin/console about`, `debug:router`, `debug:container`, `debug:autowiring <name>`, `debug:config <bundle>`, `config:dump-reference <bundle>`, `lint:container`, and the installed source under `vendor/`. Docs: https://symfony.com/doc/current/ (the version in `composer.json`).

## Working rules

- Language: code, names, commits, docs and conversation are all in English.
- Don't add Composer packages without asking.
- Don't change ADR decisions without discussion; a new decision means a new ADR (`docs/project/03-implementation-plan.md` §5).
- When unsure about context boundaries, check `docs/project/01-domain-model.md`.
- If the task doesn't say which persistence, interface or auth to use, the ADRs already decide it (Doctrine ORM, controllers + DTOs, JWT); otherwise ask.
- The author knows Laravel well and is learning Symfony. Keep answers short and lead with the main point. Explain Symfony concepts with Laravel analogies (cheat sheet in `00-project-context.md` §6) and say where the analogy breaks. Explain a component briefly the first time it is used, with a link to symfony.com docs.
- Name any design pattern you apply (catalogue in `02-design-patterns.md`), where it lives in the code, and what it teaches.
