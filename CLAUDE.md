# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`AGENTS.md` holds the general Symfony conventions (attributes, autowiring, Flex, `MapRequestPayload`, `symfony/lock`, etc.). Follow it. This file covers what is specific to this repo.

## Read first

`docs/project/00-project-context.md` is the starting document; `01-domain-model.md`, `02-design-patterns.md` and `03-implementation-plan.md` sit next to it. The plan describes the target state (DDD contexts, Kafka, JWT, Deptrac, ...). **The code is currently just a Symfony 8.1 skeleton** (`src/Kernel.php`, an empty `src/Controller/`), with only `framework-bundle`, `console`, `dotenv`, `yaml` and `flex` installed. No Doctrine, Messenger, Security, PHPUnit or other tooling exists yet. Check `composer.json` and the plan's status (§10 of the context doc, task list in the plan) before assuming something is there.

This repo is the **backend only**. The mobile app lives in a separate repo, `workout-tracker-mobile`.

## Environment: everything runs in Docker

There is no PHP, Composer or Node on the host. Run every PHP/Composer/console command inside the container, and never on the host:

```
docker compose up -d                          # app at http://localhost:8000 (FrankenPHP)
docker compose exec php bin/console ...
docker compose exec php composer require ...
docker compose exec -T php ...                # use -T in scripts/hooks (no TTY)
```

Services: `php` (FrankenPHP, PHP 8.4, `pdo_pgsql intl zip opcache`) and `postgres` 17 (db/user/password `workout`/`app`/`app`, port 5432). The docs refer to `compose.yaml` and `docker/php/Dockerfile`, but the files currently in the repo root are `docker-compose.yaml` and `Dockerfile`.

Tests, PHPStan, PHP-CS-Fixer and Deptrac are planned but **not installed**. Don't assume the commands in the plan exist; install tools only after asking.

## Architecture (planned, per docs)

- DDD bounded contexts as sub-namespaces of `App\` (`src/<Context>/{Domain,Application,Infrastructure,UI}`): Identity, Coaching, TrainingLibrary, Strength, Padel, Progress, plus SharedKernel. Strength and Padel sessions are separate models, not one table with a flag. New training types must be addable without modifying existing ones.
- The domain layer does not depend on Symfony or Doctrine (PSR interfaces such as `ClockInterface` are fine). Doctrine mappings are XML, outside the domain.
- Contexts talk to each other only via integration events (outbox, then Kafka, partition key `athleteId`, idempotent inbox consumers) or a context's public facade/query, never through another context's entities or repositories.
- Messenger has separate `command.bus`, `query.bus` and `event.bus`.
- `Progress` analytics is a pure core: plain PHP, no I/O, injected time, deterministic.
- API contract: `docs/api/openapi.json` (exported from Nelmio) must be updated in the same commit as any API change.

## Working rules from the project docs

- Don't add Composer packages without asking, even though `AGENTS.md` says to install via Flex.
- Don't change ADR decisions without discussion; a new decision means a new ADR.
- Language: code, commits, docs and conversation are all in English.
- The author knows Laravel well and is learning Symfony. Keep answers short, lead with the main point, and explain Symfony concepts with Laravel analogies. Briefly explain a component the first time it is used.
- Name any design pattern you apply (catalogue in `02-design-patterns.md`) and say where it lives.
- Priority #1 of the project is practising agentic workflows. For each feature, say which agentic task (1-7) it covers and what belongs in `docs/agentic-workflow/LOG.md`.
