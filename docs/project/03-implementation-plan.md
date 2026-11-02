# Workout Tracker — implementation plan

> Tasks 0–7 map onto the survey (checklist in §4). **The agentic tasks (1–7) take priority**; product features (M1–M7) are the material the agent works on.
>
> This plan covers the **`workout-tracker-api`** repo. Mobile items (screens, TS hooks, mobile `CLAUDE.md`) are done in the separate `workout-tracker-mobile` repo and are only mentioned here.

## 1. Stages and schedule

Assumption: about 5 h per week. With a different time budget only the "Product" column shifts; the order of agentic tasks stays.

| Week | Agentic tasks | Product (milestone) | Symfony to learn |
|---|---|---|---|
| 1 | **0**, **0b** (stub + contracts), **1** | M1: accounts, coach, athlete | Flex, Messenger (buses), Doctrine XML, Uid, Clock, Security + JWT |
| 2 | **2**, **7** (start: spec, CI) | M2: exercise catalogue + strength session + warm-up library | Validator, MapRequestPayload, Serializer, Nelmio, tagged services |
| 3 | **3** | M3: padel (training + match); outbox → Kafka → Progress flow | outbox, Kafka transport, inbox, Voters |
| 4 | **4**, **5** | M4: history, recent results, PRs | failure transport, retry, middleware, Console |
| 5 | **6** | M5: Athlete Brief + handover; start of the mobile repo | Workflow, Scheduler, Mailer, Deptrac |
| later | **7** (min. 2 weeks, 5 tasks), real analytics | M6: mobile end-to-end (separate repo); **M7: new training type (running)** as an extensibility test | Infection, Event Sourcing experiments |

**Product milestones:**
- **M1:** sign-up and login, coach profile, adding an athlete (without an account), athlete list.
- **M2:** exercise catalogue (seed + custom), planning and recording a strength session: blocks, sets, rests, goals, notes. The **TrainingLibrary** context with warm-ups (CRUD + archiving) and attaching a warm-up to the start of a strength session. TrainingLibrary is a good first real use of the `bounded-context-scaffold` skill (task 2).
- **M3:** padel training (drills, focus areas, success rate, warm-up from the library) and match (score, observations).
- **M4:** athlete timeline, "recent results" while planning, PRs, "dry-run" endpoint.
- **M5:** `AthleteAssessment`, `AthleteBrief`, handing an athlete over to another coach.
- **M6 (mobile repo):** Expo app: login, athlete list, Athlete Brief, logging a strength session on the phone. Client generated from this repo's `docs/api/openapi.json`.
- **M7:** a `Running` context added with new files only (skill `session-type-scaffold`) — proof of Open/Closed.

---

## 2. Project part (scaffolding for the agent)

### Task 0: playground and domain

1. ✅ Repo `workout-tracker-api` (structure in `00-project-context.md` §6). Symfony 8.1 from `symfony/skeleton`, created once with the `composer:2` image. Flex has its Docker recipes disabled (`extra.symfony.docker=false`).
2. Docker Compose (OrbStack), file `compose.yaml`:
   - ✅ `php`: FrankenPHP (`dunglas/frankenphp:1-php8.4`), extensions `pdo_pgsql intl zip opcache`, Composer in the image, port 8000,
   - ✅ `postgres` (17-alpine),
   - ⏳ add `rdkafka` to the image (`install-php-extensions rdkafka`),
   - ⏳ `kafka` (image `apache/kafka`, KRaft). Two listeners: internal `kafka:9092` for containers and external `localhost:9094` for the host,
   - ⏳ `kafka-ui` (kafbat, port 8080), `mailpit` (ports 8025/1025).
3. Messenger:
   - buses `command.bus` (middleware: validation, doctrine_transaction), `query.bus`, `event.bus`,
   - transports: `outbox` (Doctrine), `kafka` (`koco/messenger-kafka`, a topic per context, key = athleteId), `failed` (Doctrine), `retry` (Doctrine, exponential backoff),
   - **Note — Kafka is a log, not a queue:** there's no requeue, there are offsets and consumer groups. Retry and DLQ are handled by Messenger's failure transport. We need to verify when the chosen transport commits the offset on failure, and document it in ADR-002.
4. Working commands (recorded in CLAUDE.md), all through `docker compose exec php`:
   `vendor/bin/phpunit`, `vendor/bin/phpunit --testsuite progress-contract`, `vendor/bin/php-cs-fixer fix`, `vendor/bin/phpstan analyse`, `vendor/bin/deptrac analyse`, `bin/console lint:container`, `bin/console doctrine:schema:validate`, `bin/console messenger:consume outbox kafka_progress -vv`, `bin/console nelmio:apidoc:dump --format=json > docs/api/openapi.json`.
5. On the host only: `git`, OrbStack, VS Code + Claude Code, `jq` (for hooks).
6. `docs/agentic-workflow/LOG.md` from the template (§5).

**DoD:** repo in Git, green tests, `UserRegistered` travels outbox → Kafka → consumer in Coaching (visible in Kafka UI), no Deptrac violations, `GET /api/health` returns 200, LOG.md exists.

### Task 0b: pure Progress Analytics core

Contract `ProgressAnalyzer::analyze(AthleteHistory, AnalysisPeriod): ProgressReport` (details in `01-domain-model.md` §3). The first implementation is `BaselineProgressAnalyzer`: e1RM via Epley, PR as the maximum, volume as the sum of `reps × load` over working sets. Its only job is to pass the contract tests and let us build the rest.

**Contract tests (golden, `tests/Progress/Contract`, suite `progress-contract`):**
- empty history → empty report, `confidence = 0`, no exception,
- determinism: the same input gives an identical report,
- ramp-up sets (`RampUp`) and the session warm-up don't affect PRs, e1RM or volume,
- unit normalisation: the same sets in kg and in lb give the same result,
- a PR only when the value is **strictly** greater than the previous best,
- bodyweight exercises: PR computed from reps (or added load),
- the order of sessions on the same day doesn't change the result,
- padel success rate weighted by the number of attempts, not an average of percentages,
- a session correction (same session, new version) replaces the old one rather than duplicating it,
- a set with 0 reps or no load doesn't break the calculations (this comes back in the MCP scenario).

**Open analytics decisions** (for `docs/progress/DECISIONS.md`): e1RM formula and the rep limit beyond which it stops being reliable; the progression suggestion rule; how to compute confidence; the time window for trends; exercise comparability (squat variants); the effect of RPE/RIR; which padel metrics go into the Athlete Brief.

**DoD:** interface + baseline + the full set of contract tests green, endpoint `POST /api/progress/dry-run`.

---

## 3. Agentic part (the main goal)

### Task 1: CLAUDE.md
*Custom rule files tailored to a specific repository*

1. `claude` → `/init`, then rewrite by hand. `/init` will read the repo, including `docs/project/`. At the top of CLAUDE.md we import the context: `@docs/project/00-project-context.md`. Sections:
   - **Stack and commands:** versions, containers, exact commands (`docker compose exec php …`), addresses (API :8000, Kafka UI :8080, mailpit :8025).
   - **Architecture:** 6 contexts + SharedKernel, layers, XML mapping outside the domain, repositories as interfaces, cross-context communication only via events or a facade, Deptrac as the guard.
   - **Core rules:** Progress Analytics without the framework or I/O, time and randomness injected; every analytics change requires updating the contract tests and an entry in `docs/progress/DECISIONS.md`.
   - **Messenger/Kafka:** messages are immutable DTOs with primitives, integration events only through the outbox, schema in `docs/contracts/`, key = athleteId, idempotent consumer (inbox), every new message type has routing, retry and a test on `in-memory://`.
   - **Conventions:** `declare(strict_types=1)`, `final`, `readonly` DTOs, named constructors, domain exceptions instead of null, controllers only delegate.
   - **Patterns:** reference to the catalogue; every new feature names the patterns it uses in the PR description.
   - **Workflow:** TDD; after a change, run the affected context's tests; after an API change, export `docs/api/openapi.json`.
   - **Prohibitions:** don't run PHP/Composer on the host, don't edit released migrations, don't touch `.env.local`, don't add packages without asking, don't delete topics or Docker volumes, don't reset consumer group offsets, don't change contract tests to make them "pass"; when in doubt, ask.
2. Nested file: `src/Progress/Domain/Analytics/CLAUDE.md` (purity, determinism, `ProgressReport` format). The mobile `CLAUDE.md` is created in the mobile repo.
3. About 150 lines in the main file. Every recurring agent mistake becomes a rule.

**DoD:** CLAUDE.md (main + Analytics) committed, 3 entries in LOG.md where a rule actually changed the agent's behaviour.

### Task 2: slash commands and a skill
*Custom slash commands or reusable prompts/skills*

Commands in `.claude/commands/` (minimum 3, target 5):
- `/analytics-experiment <variant>`: a new strategy implementation (e.g. `BrzyckiE1rm`, `DoubleProgression`) in `Progress/Domain/Analytics`, the whole contract suite (no test changes), recalculation of `tests/Progress/fixtures/` and a comparison table (athlete, exercise, current result, new result, difference), an explanation of the biggest divergences, an entry in `DECISIONS.md`. **Does not swap the default implementation without approval.**
- `/new-message <Context> <Event>`: JSON schema in `docs/contracts/`, integration event class, mapping from the domain event, outbox → Kafka routing (topic, key), consumer handler with inbox, test on `in-memory://`.
- `/new-endpoint`: DTO + validation, command or query + handler, controller, Voter, OpenAPI annotations, functional test, export of `docs/api/openapi.json`.
- `/review`: review of `git diff` against the code-reviewer checklist.
- a fifth command to be decided in practice (from recurring tasks). `/new-screen` will be created in the mobile repo.

Skills in `.claude/skills/<name>/SKILL.md` (frontmatter `name`, `description`):
- **`bounded-context-scaffold`** (required): context skeleton with layers, a repository interface in the domain, a Doctrine implementation with XML, a Deptrac rule, an in-memory repo and tests.
- `session-type-scaffold` (optional, ideal for M7): a new training type end-to-end; new files registered via tags, without editing existing classes.

**DoD:** min. 3 commands + 1 skill in the repo, each used at least 3 times on real tasks (M2–M4 provide plenty), entries in LOG.md.

### Task 3: hooks and automation
*Hooks or automation that run on agent actions*

Hooks run **on the host**, so they call PHP tools through `docker compose exec -T php …`.

1. **PostToolUse** (`Edit|Write`) → `php-quality.sh`: for `*.php` runs php-cs-fixer on the file, PHPStan on the file, Deptrac; for paths under `src/Progress/Domain/Analytics/` also the `progress-contract` suite. An error ends with `exit 2` and a description on stderr, which goes back to the agent.
2. **PreToolUse** (`Bash`) → `block-dangerous.sh`: blocks `doctrine:schema:drop`, `doctrine:database:drop`, `messenger:failed:remove`, `kafka-topics … --delete`, `kafka-consumer-groups … --reset-offsets|--delete`, `docker compose down -v`, `docker volume rm`, `rm -rf`, reading `.env.local`, and running `php`/`composer` directly on the host.
3. **PreToolUse** (`Edit|Write`) → `protect-contract-tests.sh`: blocks edits to `tests/Progress/Contract/**` and `docs/contracts/**` (versioned schemas are changed deliberately).
4. **Stop** → `run-checks.sh`: full PHPUnit, `lint:container`, `doctrine:schema:validate --skip-sync`, Deptrac; on error `exit 2`. Check `stop_hook_active` to avoid a loop.
5. `.claude/settings.json` in the repo, `chmod +x`, verification via `/hooks`. A pre-commit hook independent of the agent: **lefthook** (commands via `docker compose exec -T php`).
6. The equivalent of `ts-quality.sh` (eslint + tsc) will be created in the mobile repo.

**DoD:** 3 documented situations in LOG.md: the agent fixed a hook error by itself (PHPStan or Deptrac), a hook blocked a contract edit or a dangerous command, the Stop hook prevented finishing with red tests.

### Task 4: custom MCP server over Kafka and Messenger
*A custom MCP server connecting the agent to an internal system*

Progress recalculations go through Kafka, so an agent without visibility into topics, lag and failed messages is blind when debugging. The server lives in `tools/mcp-kafka/` (Python, FastMCP + `confluent-kafka` + `psycopg`) and has its own `Dockerfile`. Claude Code starts it via `.mcp.json` with `docker compose run --rm -i mcp-kafka` (stdio transport). Inside the Compose network the broker is `kafka:9092`.

Tools (stage 1, **read-only**):
- `list_topics()`: name, partition count, total messages (high minus low watermark),
- `topic_details(topic)`: partitions and offsets,
- `consumer_lag(group)`: lag per partition (Kafka's equivalent of "how many are waiting in the queue"),
- `peek_messages(topic, count=5, partition=None, from_="latest")`: preview via `assign()` with `enable.auto.commit=False` and a temporary `group.id`, **without affecting real consumer groups**; decodes the event envelope,
- `find_event(event_id)`: looks for an event by `eventId` in the last N messages,
- `list_failed_messages(limit)`: reads the `messenger_messages` table (`queue_name = 'failed'`) with a read-only DB user; the agent fetches details of a single message itself via `docker compose exec php bin/console messenger:failed:show <id>`,
- optionally `outbox_status()`: number of unsent messages in the outbox.

Sketch:
```python
import os, json, uuid
import psycopg
from confluent_kafka import Consumer, TopicPartition
from confluent_kafka.admin import AdminClient
from mcp.server.fastmcp import FastMCP

BOOTSTRAP = os.environ.get("KAFKA_BOOTSTRAP", "kafka:9092")
DB_DSN = os.environ["MCP_DB_DSN"]  # read-only user
mcp = FastMCP("kafka-messenger")

def _consumer() -> Consumer:
    return Consumer({"bootstrap.servers": BOOTSTRAP,
                     "group.id": f"mcp-peek-{uuid.uuid4()}",
                     "enable.auto.commit": False,
                     "auto.offset.reset": "earliest"})

@mcp.tool()
def list_topics() -> list[dict]:
    """Topics with partition count and message count (internal topics skipped)."""
    md = AdminClient({"bootstrap.servers": BOOTSTRAP}).list_topics(timeout=10)
    c, out = _consumer(), []
    try:
        for name, t in md.topics.items():
            if name.startswith("__"):
                continue
            total = sum(hi - lo for lo, hi in
                        (c.get_watermark_offsets(TopicPartition(name, p), timeout=5) for p in t.partitions))
            out.append({"topic": name, "partitions": len(t.partitions), "messages": total})
    finally:
        c.close()
    return out

@mcp.tool()
def peek_messages(topic: str, count: int = 5, partition: int = 0) -> list[dict]:
    """Latest messages from a partition, without committing offsets."""
    c = _consumer()
    try:
        lo, hi = c.get_watermark_offsets(TopicPartition(topic, partition), timeout=5)
        start = max(lo, hi - count)
        c.assign([TopicPartition(topic, partition, start)])
        msgs = []
        while len(msgs) < hi - start:
            m = c.poll(2.0)
            if m is None: break
            if m.error(): return [{"error": str(m.error())}]
            raw = (m.value() or b"").decode(errors="replace")
            try:
                value = json.loads(raw)
            except json.JSONDecodeError:
                value = raw
            msgs.append({"offset": m.offset(), "key": (m.key() or b"").decode(),
                         "headers": dict((k, v.decode(errors="replace")) for k, v in (m.headers() or [])),
                         "value": value})
        return msgs
    finally:
        c.close()

@mcp.tool()
def list_failed_messages(limit: int = 20) -> list[dict]:
    """Messages in Messenger's `failed` transport (Doctrine table)."""
    with psycopg.connect(DB_DSN) as conn:
        rows = conn.execute(
            "SELECT id, created_at, headers FROM messenger_messages "
            "WHERE queue_name = 'failed' ORDER BY id DESC LIMIT %s", (limit,)).fetchall()
    return [{"id": r[0], "created_at": r[1].isoformat(), "headers": r[2]} for r in rows]

if __name__ == "__main__":
    mcp.run()
```
Steps: test in MCP Inspector (in a container or via `docker compose run`), registration through `.mcp.json` (secrets in environment variables), README. Stage 2 (optional, local only, with confirmation): `retry_failed_message(id)`.

**End-to-end scenario:** break the `RecalculateAthleteProgress` handler, e.g. a division by zero when computing the average load for an exercise that only has bodyweight sets, or a set with 0 reps. Record a few sessions and stop the consumer. Then prompt: *"Check the lag and failed messages, find the event that breaks the handler, write a test that reproduces it, fix the handler and make sure that redelivering the same `eventId` doesn't change the result."*

**DoD:** server with a README and at least 4 tools, error handling (broker unavailable, non-existent topic, empty partition), E2E scenario in LOG.md.

### Task 5: subagents
*Multiple subagents split by task type*

In `.claude/agents/`:
- `implementer`: code compliant with CLAUDE.md and TDD, full tools.
- `code-reviewer`: read-only (Read, Grep, Glob, Bash for tests). Checklist below.
- `test-writer`: tests, edge cases, handlers on `in-memory://`, Object Mothers.
- `domain-modeler`: read-only; checks consistency with `docs/project/01-domain-model.md` and `docs/domain/` (ubiquitous language, aggregate boundaries, whether an event leaks between contexts, whether an anaemic model is emerging).
- optionally `analytics-analyst` (compares analytics variants on fixtures, doesn't touch production code). `mobile-implementer` will be created in the mobile repo.

`code-reviewer` checklist (frontmatter `tools: Read, Grep, Glob, Bash`):
- Analytics core: no framework or I/O, determinism, edge cases (empty history, 0 reps, mixed units, corrections, duplicates),
- layers: domain without Doctrine and Symfony, controllers only delegate, Deptrac green,
- Messenger/Kafka: immutable and serialisable message, idempotency (inbox), outbox in the same transaction, partition key, retry and failure transport, schema backward compatibility,
- Doctrine: N+1, indexes, migration consistent with the mapping,
- security: input validation, **Voter** (can this coach read or write this athlete's data, does a Viewer not write),
- API: is `docs/api/openapi.json` updated and backward compatible for mobile,
- patterns: is the pattern used justified, are any catalogue anti-patterns present,
- tests cover behaviour,
- output as `[BLOCKER] / [MAJOR] / [MINOR]` with file and line. The reviewer doesn't modify code.

Rehearsed sequence: implementer → domain-modeler → code-reviewer → BLOCKER/MAJOR fixes.

**DoD:** at least 3 subagents, 2 cases in LOG.md where the reviewer (or domain-modeler) found a real problem.

### Task 6: orchestration
*Orchestrated multiple agents in parallel or in sequence*

**Variant A, in parallel (git worktrees):** three independent parts, e.g.:
- `feature/padel-match` (Padel context: match + score + Specification),
- `feature/handover` (Coaching: athlete handover + Workflow),
- `feature/personal-records` (Progress: PR projection + endpoint).

Commands: `git worktree add ../wt-padel -b feature/padel-match` etc., a separate Claude session in each folder.
**Docker caveat:** each worktree is a separate folder with the same `compose.yaml`. Set a different `COMPOSE_PROJECT_NAME` and different ports (e.g. via `compose.override.yaml` or per-worktree `.env` variables), otherwise the stacks will clash. Alternatively the tests can share one database and one Kafka if each worktree uses its own test database name.
When merging, watch for conflicts in `messenger.yaml`, migrations and `docs/api/openapi.json`. Run the full test suite at the end.

**Variant B, sequential pipeline** `scripts/agent-pipeline.sh`: spec → plan (`claude -p`, Read/Grep/Glob/Write only) → implementation (TDD) → hard gates (via `docker compose exec -T php`: php-cs-fixer `--dry-run`, PHPStan, Deptrac, `lint:container`, `schema:validate`, PHPUnit) → subagent review into `review.md`. Headless-mode flags need to be verified against current documentation.

**Analytics variant:** three parallel headless sessions, each with a different `E1rmFormula`/`ProgressionRule` variant, recalculating the fixtures; the results go into a single comparison table.

Optionally: the same pipeline in GitHub Actions with Kafka as a service container.

**DoD:** variant A once on a real feature, variant B on at least 2 tasks. In LOG.md: time, result, what went wrong, what was fixed in the script.

### Task 7: a consistent workflow
*A defined personal workflow applied consistently*

`docs/agentic-workflow/WORKFLOW.md`:
1. **Spec**: `docs/specs/NNN-name.md`. Contains the goal, acceptance criteria, out of scope, risks, affected contexts, **patterns used**, impact on the event contract (Published Language), impact on the mobile API (`openapi.json`) and on the Athlete Brief.
2. **Plan**: the agent in Plan Mode. ✅ *Checkpoint 1: plan approval.*
3. **Implementation**: implementer + commands from task 2.
4. **Automated verification**: hooks + CI (`.github/workflows/ci.yml`). Backend job: CS-Fixer, PHPStan, Deptrac, `lint:container`, `schema:validate`, PHPUnit with a separate `progress-contract` suite, Kafka as a service or `in-memory://`, a check that `openapi.json` is up to date. The mobile job lives in the mobile repo.
5. **Agent review**: domain-modeler + code-reviewer.
6. ✅ *Checkpoint 2: manual diff review*: domain logic, context boundaries, core purity, contract backward compatibility, migrations, retry/DLQ, whether the tests check behaviour.
7. **Retro**: an agent mistake becomes a rule in CLAUDE.md or a fix to a command or hook.

**DoD:** WORKFLOW.md, a spec template and CI in the repo; at least 5 tasks taken through the full workflow with LOG.md entries; at least 2 CLAUDE.md changes resulting from retros.

---

## 4. Final checklist (mapping onto the survey)

| Survey item | Task | Evidence |
|---|---|---|
| Custom rule files / project instructions | 1 | CLAUDE.md (main + Analytics; mobile in the mobile repo) + 3 LOG entries |
| Custom slash commands / skills | 2 | min. 3 commands + `bounded-context-scaffold`, usages in LOG |
| Hooks / automation | 3 | settings.json, PHP scripts (via Docker), lefthook, 3 demonstrations |
| Custom MCP server | 4 | server over Kafka and the failed transport + E2E scenario |
| Multiple subagents | 5 | `.claude/agents/` (≥3), 2 problems found |
| Orchestrated multiple agents | 6 | worktrees + `agent-pipeline.sh`, ≥2 runs |
| Defined personal workflow | 7 | WORKFLOW.md, CI, ≥5 tasks in LOG, ≥2 retro fixes |

## 5. Log template: `docs/agentic-workflow/LOG.md`

| Date | Task / feature | Elements used (1–7) | What worked | What went wrong | Change after retro |
|---|---|---|---|---|---|
| YYYY-MM-DD | | | | | |

## 6. Decisions (ADRs in `docs/adr/`)

| ADR | Question | Decision / recommendation |
|---|---|---|
| 001 | Symfony 8.x or 7.4 LTS | **accepted:** Symfony 8.1 (latest stable, supported by `koco/messenger-kafka`); bump to 8.2 once released |
| 002 | Kafka transport | `koco/messenger-kafka` (actively maintained, Symfony ^7.4/^8.1). A minimal custom transport only as an optional exercise. Document offset behaviour on failure |
| 003 | API Platform or controllers + DTOs | controllers + DTOs + Nelmio (more Symfony learning, cleaner DDD) |
| 004 | Authentication | JWT (lexik) + refresh tokens (gesdinet) |
| 005 | Does an athlete have an account | MVP: profile managed by the coach; account optional later |
| 006 | Plan and execution in one session aggregate | yes: `Prescription` + `SetEntry[]` in `ExerciseEntry` |
| 007 | Group padel training | MVP: one athlete per session; groups later |
| 008 | Event Sourcing | not in the MVP; events + projections. ES of a single aggregate as an experiment |
| 009 | Session lifecycle: State or Workflow | State in the domain (sessions), Workflow in the application layer (Handover, invitations) |
| 010 | Units | kg canonically in storage, display unit per coach/athlete |
| 011 | Projections: Doctrine ORM or DBAL | DBAL (readable SQL for read models) |
| 012 | Monorepo or separate repositories | **accepted:** separate (`workout-tracker-api`, `workout-tracker-mobile`). The contract is `docs/api/openapi.json`; consequence: hooks, CLAUDE.md and CI per repo, TS client generated in the mobile repo |
| 013 | Runtime environment | **accepted:** everything in Docker (OrbStack), no local PHP/Node; FrankenPHP as the server; `symfony/skeleton` instead of `--webapp`; Flex without Docker recipes |
| 014 | Working language | **accepted:** English for code, docs, commits and conversation with the agent |

## 7. Roadmap: far future (after everything else)

**AI video analysis of padel sessions (premium).** Not in the current scope; just a note so the idea isn't lost.
- Premium feature: the coach records or uploads a clip of a training session or match, and AI assesses technique (e.g. bandeja, volley, positioning) and adds observations to the session and the Athlete Brief.
- **Bring your own key (BYOK):** the user connects their own key for a model provider; the app doesn't pay for the analysis.
- Initial thoughts for later (to be verified when the time comes): a separate `VideoAnalysis` context; keys stored encrypted per user; video in object storage; analysis asynchronously via Kafka; the result reaches Padel/Progress as an event (e.g. `VideoAnalysisCompleted`); the premium flag as a permission (Voter).
