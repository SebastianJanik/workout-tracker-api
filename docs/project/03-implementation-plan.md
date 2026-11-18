# Workout Tracker — implementation plan

> Tasks 0–7 map onto the survey (checklist in §4). **The agentic tasks (1–7) take priority**; product features (M1–M7) are the material the agent works on.
>
> **This file is the single place for task requirements, definition of done (DoD) and status.** Every task has a `Status` line and a DoD checklist. When a DoD item is met, tick it (`- [x]`); when all are ticked, set the status to ✅ done. Status legend: ✅ done · 🔎 built, awaiting real-life confirmation (only the "used on real work" DoD items are open) · ⏳ in progress (build work remains) · ☐ not started.
>
> This plan covers the **`workout-tracker-api`** repo. Mobile items (screens, TS hooks, mobile rule files) are done in the separate `workout-tracker-mobile` repo and are only mentioned here.

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
- **M4:** athlete timeline, "recent results" while planning, PRs, analysis from persisted data.
- **M5:** `AthleteAssessment`, `AthleteBrief`, handing an athlete over to another coach.
- **M6 (mobile repo):** Expo app: login, athlete list, Athlete Brief, logging a strength session on the phone. Client generated from this repo's `docs/api/openapi.json`.
- **M7:** a `Running` context added with new files only — proof of Open/Closed.

---

## 2. Project part (scaffolding for the agent)

### Task 0: playground and domain

**Status:** ⏳ in progress (only the `UserRegistered` flow is open; it needs M1).

1. Repo `workout-tracker-api` (structure in `00-project-context.md` §5). Symfony 8.1 from `symfony/skeleton`, created once with the `composer:2` image. Flex has its Docker recipes disabled (`extra.symfony.docker=false`).
2. Docker Compose (OrbStack), file `docker-compose.yaml`: `php` (FrankenPHP `dunglas/frankenphp:1-php8.4`, extensions `pdo_pgsql intl zip opcache rdkafka`, Composer in the image, port 8000), `postgres` (17-alpine), `kafka` (`apache/kafka`, KRaft; internal listener `kafka:9092` for containers, external `localhost:9094` for the host), `kafka-ui` (kafbat, port 8080), `mailpit` (ports 8025/1025).
3. Messenger:
   - buses `command.bus` (middleware: validation, doctrine_transaction), `query.bus`, `event.bus`,
   - transports: `outbox` (Doctrine), `kafka` (`koco/messenger-kafka`, a topic per context, key = athleteId), `failed` (Doctrine), `retry` (Doctrine, exponential backoff),
   - **Note: Kafka is a log, not a queue.** There's no requeue, there are offsets and consumer groups. Retry and DLQ are handled by Messenger's failure transport. We need to verify when the chosen transport commits the offset on failure, and document it in ADR-002.
4. Working commands live in `AGENTS.md` (Environment).
5. On the host only: `git`, OrbStack, VS Code + Claude Code, `jq` (for hooks).

**DoD:**
- [x] repo in Git, green tests, no Deptrac violations
- [x] `GET /api/health` returns 200
- [ ] `UserRegistered` travels outbox → Kafka → consumer in Coaching (visible in Kafka UI); needs M1

### Task 0b: pure Progress Analytics core

**Status:** ✅ done.

`StrengthAnalyzer::analyze(list<StrengthSessionRecord>, AnalysisPeriod): StrengthReport` (details in `01-domain-model.md` §3). Strength sessions only. e1RM via Epley, PR as the best value of a series, volume as the sum of `reps × load` (or held seconds) over counted sets. Its only job is to pass the contract tests and let us build the rest.

**Contract tests (golden, `tests/Progress/Contract`, suite `progress-contract`):**
- empty history → empty report, no exception,
- determinism: the same input gives an identical report,
- ramp-up sets (`RampUp`) and the session warm-up don't affect PRs, e1RM or volume,
- unit normalisation: the same sets in kg and in lb give the same result,
- a PR only when the value is **strictly** greater than the previous best,
- bodyweight exercises: PR computed from reps,
- timed exercises: PR is the longest hold, one series per load, sets without a duration are ignored,
- the order of sessions on the same day doesn't change the result,
- a set with 0 reps or no load doesn't break the calculations (this comes back in the MCP scenario).

**Open analytics decisions** (for `docs/progress/DECISIONS.md`): the rep limit beyond which e1RM stops being reliable; the progression suggestion rule; the time window for trends; exercise comparability (squat variants); the effect of RPE/RIR.

**DoD:**
- [x] `StrengthAnalyzer` + the full set of contract tests green

---

## 3. Agentic part (the main goal)

### Task 1: rule files (AGENTS.md + CLAUDE.md)
*Custom rule files tailored to a specific repository*

**Status:** 🔎 built, awaiting real-life confirmation (show a rule changing agent behaviour in 3 cases).

Layout, so that other tools and developers are supported too:
- `AGENTS.md` holds all tool-neutral rules (the cross-tool convention). Each topic lives in exactly one file, and its header table says which.
- `CLAUDE.md` is `@AGENTS.md` plus Claude-only extras (agentic-task bookkeeping, pointers to `.claude/`). It points to `docs/project/00-project-context.md` through `AGENTS.md` instead of importing it.
- Nested rules go in `<dir>/AGENTS.md`, with a one-line `<dir>/CLAUDE.md` (`@AGENTS.md`) beside it, because Claude Code doesn't read nested `AGENTS.md` itself.
- Every recurring agent mistake becomes a rule.

Required content of `AGENTS.md`:
- [x] Stack and commands: containers, exact `docker compose exec php …` commands, addresses (API :8000, Kafka UI :8080, mailpit :8025)
- [x] Architecture: contexts, layers, XML mapping outside the domain, cross-context communication only via events or a facade, Deptrac as the guard
- [x] Pure core: Progress Analytics without framework or I/O, injected time
- [x] Core rules: contract tests are never changed to pass; a deliberate analytics change is recorded in `docs/progress/DECISIONS.md`
- [x] Messenger/Kafka: messages are immutable DTOs with primitives, integration events only through the outbox, every new message type has routing, retry and a test on `in-memory://` (bus separation, partition key and inbox are already rules)
- [x] Conventions: `declare(strict_types=1)`, `final`, named constructors, domain exceptions instead of null, controllers only delegate (`readonly` DTOs and thin controllers are already rules)
- [x] Patterns: every new feature names the patterns it uses in the PR description (naming them in answers is already a rule)
- [x] Workflow: TDD; after a change run the affected context's tests
- [x] Prohibitions: don't edit released migrations, don't touch `.env.local`, don't delete topics or Docker volumes, don't reset consumer group offsets; when in doubt, ask (no packages without asking, no host PHP and no changing contract tests are already rules)

**DoD:**
- [x] all required content above is in `AGENTS.md`
- [x] nested `src/Progress/Domain/Strength/AGENTS.md` (purity, determinism, `StrengthReport` format) plus its one-line `CLAUDE.md` committed
- [x] `AGENTS.md` stays around 150 lines
- [ ] a rule is shown to change the agent's behaviour in at least 3 cases (say so in the commit message)

The mobile rule files are created in the mobile repo.

### Task 2: a skill
*Custom slash commands or reusable prompts/skills*

**Status:** 🔎 built, awaiting real-life confirmation (use the skill on 3 real changes).

Skills are shared across tools: they live in `.agents/skills/` (the cross-tool location) and `.claude/skills` is a symlink to it, because Claude Code reads only `.claude/skills/`. Claude-only frontmatter such as `allowed-tools` is ignored by other tools. One skill is enough for the survey item ("commands / skills"): **`review`** in `.agents/skills/review/SKILL.md` (frontmatter `name`, `description`). It reviews the current `git diff` against the `code-reviewer` checklist in task 5 (single source: the skill and the subagent share it, no second copy), plus a class-in-the-right-`Domain/`-folder check.

More skills or commands are added only when a task repeats often enough to need one.

**DoD:**
- [x] `review` skill (`SKILL.md` + generic `checklist.md`) is in `.agents/skills/`, symlinked from `.claude/skills`
- [ ] used on real changes at least 3 times (M1-M4)

### Task 3: hooks and automation
*Hooks or automation that run on agent actions*

**Status:** 🔎 built, awaiting real-life confirmation (check `/hooks`, then wait for a real pre-commit fix).

Hooks run **on the host**, so they call PHP tools through `docker compose exec -T php …`.

One Claude hook (more can be added later when a real mistake needs one). Quality checks (php-cs-fixer, PHPStan, Deptrac) are deliberately *not* a PostToolUse hook: they run only at commit time in the git pre-commit hook (`.githooks/pre-commit`), so the agent can leave debug code and half-finished edits while working.

**PreToolUse** (`Bash`) → `block-dangerous.sh`: blocks destructive commands (`doctrine:schema:drop`, `doctrine:database:drop`, `docker compose down -v`, `docker volume rm`, `rm -rf`, `kafka-topics … --delete`, `kafka-consumer-groups … --reset-offsets|--delete`), reading `.env.local`, and running `php`/`composer` directly on the host.

Registered in `.claude/settings.json` (script `chmod +x`, verify via `/hooks`). The mobile equivalent of the pre-commit quality checks (eslint + tsc) will be created in the mobile repo.

**DoD:**
- [ ] the hook is registered in `.claude/settings.json` and visible in `/hooks`
- [ ] the agent fixed a pre-commit failure by itself (PHPStan or Deptrac)
- [x] a hook blocked a dangerous command (2026-11-18: a Docker volume removal, plus an accidental block of a commit message mentioning the secrets file)

### Task 4: custom MCP server over Kafka and Messenger
*A custom MCP server connecting the agent to an internal system*

**Status:** ☐ not started.

Progress recalculations go through Kafka, so an agent without visibility into topics and failed messages is blind when debugging. The server lives in `tools/mcp-kafka/` (Python, FastMCP + `confluent-kafka` + `psycopg`) and has its own `Dockerfile`. Claude Code starts it via `.mcp.json` with `docker compose run --rm -i mcp-kafka` (stdio transport). Inside the Compose network the broker is `kafka:9092`.

Two read-only tools:
- `peek_messages(topic, count=5, partition=0)`: preview of the latest messages via `assign()` with `enable.auto.commit=False` and a temporary `group.id`, **without affecting real consumer groups**; decodes the event envelope,
- `list_failed_messages(limit)`: reads the `messenger_messages` table (`queue_name = 'failed'`) with a read-only DB user; the agent fetches details of a single message itself via `docker compose exec php bin/console messenger:failed:show <id>`.

Sketch:
```python
import os, json, uuid
import psycopg
from confluent_kafka import Consumer, TopicPartition
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
Steps: test in MCP Inspector (in a container or via `docker compose run`), registration through `.mcp.json` (secrets in environment variables), README.

**End-to-end scenario:** break the `RecalculateAthleteProgress` handler, e.g. a division by zero when computing the average load for an exercise that only has bodyweight sets, or a set with 0 reps. Record a few sessions and stop the consumer. Then prompt: *"Check the failed messages, find the event that breaks the handler, write a test that reproduces it, fix the handler and make sure that redelivering the same `eventId` doesn't change the result."*

**DoD:**
- [ ] server with a README and the 2 tools
- [ ] error handling: broker unavailable, non-existent topic, empty partition
- [ ] the end-to-end scenario above runs successfully

### Task 5: subagents
*Multiple subagents split by task type*

**Status:** ☐ not started.

In `.claude/agents/`:
- `implementer`: code compliant with AGENTS.md and TDD, full tools.
- `code-reviewer`: read-only (Read, Grep, Glob, Bash for tests). Checklist below.
- `test-writer`: tests, edge cases, handlers on `in-memory://`, Object Mothers.
- `domain-modeler`: read-only; checks consistency with `docs/project/01-domain-model.md` and `docs/domain/` (ubiquitous language, aggregate boundaries, whether an event leaks between contexts, whether an anaemic model is emerging).
- `mobile-implementer` will be created in the mobile repo.

`code-reviewer` reads the shared `.agents/skills/review/checklist.md` (frontmatter `tools: Read, Grep, Glob, Bash`). Areas it must cover:
- Analytics core: no framework or I/O, determinism, edge cases (empty history, 0 reps, mixed units, sets without the measured value),
- layers: domain without Doctrine and Symfony, controllers only delegate, Deptrac green,
- Messenger/Kafka: immutable and serialisable message, idempotency (inbox), outbox in the same transaction, partition key, retry and failure transport, schema backward compatibility,
- Doctrine: N+1, indexes, migration consistent with the mapping,
- security: input validation, **Voter** (can this coach read or write this athlete's data, does a Viewer not write),
- API: is `docs/api/openapi.json` updated and backward compatible for mobile,
- patterns: is the pattern used justified, are any catalogue anti-patterns present,
- tests cover behaviour,
- output as `[BLOCKER] / [MAJOR] / [MINOR]` with file and line. The reviewer doesn't modify code.

Rehearsed sequence: implementer → domain-modeler → code-reviewer → BLOCKER/MAJOR fixes.

**DoD:**
- [ ] at least 3 subagents in `.claude/agents/`
- [ ] the reviewer (or domain-modeler) found a real problem in at least 2 cases

### Task 6: orchestration
*Orchestrated multiple agents in parallel or in sequence*

**Status:** ☐ not started.

**Variant A, in parallel (git worktrees):** three independent parts, e.g.:
- `feature/padel-match` (Padel context: match + score + Specification),
- `feature/handover` (Coaching: athlete handover + Workflow),
- `feature/personal-records` (Progress: PR projection + endpoint).

Commands: `git worktree add ../wt-padel -b feature/padel-match` etc., a separate Claude session in each folder.
**Docker caveat:** each worktree is a separate folder with the same `docker-compose.yaml`. Set a different `COMPOSE_PROJECT_NAME` and different ports (e.g. via `compose.override.yaml` or per-worktree `.env` variables), otherwise the stacks will clash. Alternatively the tests can share one database and one Kafka if each worktree uses its own test database name.
When merging, watch for conflicts in `messenger.yaml`, migrations and `docs/api/openapi.json`. Run the full test suite at the end.

**Variant B, sequential pipeline** `scripts/agent-pipeline.sh`: spec → plan (`claude -p`, Read/Grep/Glob/Write only) → implementation (TDD) → hard gates (via `docker compose exec -T php`: php-cs-fixer `--dry-run`, PHPStan, Deptrac, `lint:container`, `schema:validate`, PHPUnit) → subagent review into `review.md`. Headless-mode flags need to be verified against current documentation.

Optionally: the same pipeline in GitHub Actions with Kafka as a service container.

**DoD:**
- [ ] variant A run once on a real feature
- [ ] variant B run on at least 2 tasks, with script problems fixed along the way

### Task 7: a consistent workflow
*A defined personal workflow applied consistently*

**Status:** 🔎 built, awaiting real-life confirmation (take 5 real tasks through it; CI has not run on GitHub yet).

`docs/agentic-workflow/WORKFLOW.md`:
1. **Spec**: `docs/specs/NNN-name.md`. Contains the goal, acceptance criteria, out of scope, risks, affected contexts, **patterns used**, impact on the event contract (Published Language), impact on the mobile API (`openapi.json`) and on the Athlete Brief.
2. **Plan**: the agent in Plan Mode. ✅ *Checkpoint 1: plan approval.*
3. **Implementation**: implementer + the `review` skill from task 2 for self-review.
4. **Automated verification**: hooks + CI (`.github/workflows/ci.yml`). Backend job: CS-Fixer, PHPStan, Deptrac, `lint:container`, `schema:validate`, PHPUnit with a separate `progress-contract` suite, Kafka as a service or `in-memory://`, a check that `openapi.json` is up to date. The mobile job lives in the mobile repo.
5. **Agent review**: domain-modeler + code-reviewer.
6. ✅ *Checkpoint 2: manual diff review*: domain logic, context boundaries, core purity, contract backward compatibility, migrations, retry/DLQ, whether the tests check behaviour.
7. **Retro**: an agent mistake becomes a rule in `AGENTS.md` (or `CLAUDE.md` if Claude-only) or a fix to a command or hook.

**DoD:**
- [x] WORKFLOW.md, a spec template and CI in the repo
- [ ] at least 5 tasks taken through the full workflow
- [ ] at least 2 rule-file changes (`AGENTS.md` / `CLAUDE.md`) resulting from retros

---

## 4. Final checklist (mapping onto the survey)

| Survey item | Task | Evidence |
|---|---|---|
| Custom rule files / project instructions | 1 | `AGENTS.md` + `CLAUDE.md` (shared rules + Claude extras; nested Progress core; mobile in the mobile repo) |
| Custom slash commands / skills | 2 | `review` skill, 3 real uses |
| Hooks / automation | 3 | `settings.json`, 2 scripts (via Docker), 2 demonstrations |
| Custom MCP server | 4 | server with 2 tools (peek messages, failed messages) + E2E scenario |
| Multiple subagents | 5 | `.claude/agents/` (≥3), 2 problems found |
| Orchestrated multiple agents | 6 | worktrees + `agent-pipeline.sh`, ≥2 runs |
| Defined personal workflow | 7 | WORKFLOW.md, CI, ≥5 tasks, ≥2 retro fixes |

## 5. Decisions (ADRs in `docs/adr/`)

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
| 012 | Monorepo or separate repositories | **accepted:** separate (`workout-tracker-api`, `workout-tracker-mobile`). The contract is `docs/api/openapi.json`; consequence: hooks, rule files and CI per repo, TS client generated in the mobile repo |
| 013 | Runtime environment | **accepted:** everything in Docker (OrbStack), no local PHP/Node; FrankenPHP as the server; `symfony/skeleton` instead of `--webapp`; Flex without Docker recipes |
| 014 | Working language | **accepted:** English for code, docs, commits and conversation with the agent |

## 6. Roadmap: far future (after everything else)

**AI video analysis of padel sessions (premium).** Not in the current scope; just a note so the idea isn't lost.
- Premium feature: the coach records or uploads a clip of a training session or match, and AI assesses technique (e.g. bandeja, volley, positioning) and adds observations to the session and the Athlete Brief.
- **Bring your own key (BYOK):** the user connects their own key for a model provider; the app doesn't pay for the analysis.
- Initial thoughts for later (to be verified when the time comes): a separate `VideoAnalysis` context; keys stored encrypted per user; video in object storage; analysis asynchronously via Kafka; the result reaches Padel/Progress as an event (e.g. `VideoAnalysisCompleted`); the premium flag as a permission (Voter).
