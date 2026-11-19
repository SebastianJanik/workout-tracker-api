# Agentic workflow (task 7)

The workflow every feature goes through. Requirements and DoD for task 7 live in `docs/project/03-implementation-plan.md`; this file is the how-to. Tool-neutral: any coding agent can follow it.

```
Spec → Plan ✅ → Implement → Verify → Agent review → Manual review ✅ → Retro
```

✅ = human checkpoint. The agent stops and waits there.

## 1. Spec
Copy `docs/specs/TEMPLATE.md` to `docs/specs/NNN-name.md` (next free number) and fill every section; write "None" instead of deleting one. Set `Status: approved` once you've read it. No plan or code before that.

## 2. Plan (checkpoint 1)
The agent drafts a plan in Plan Mode from the spec: files to add or change, test list, migrations, config. **You approve or correct the plan before any code is written.** Check it against context boundaries and the spec's out-of-scope list.

## 3. Implement
TDD as in `AGENTS.md` (Testing): failing test first, then code; run the affected context's tests after each change. Before declaring done, self-review with the `review` skill (`.agents/skills/review`) and fix its BLOCKER/MAJOR findings. Commit in small steps; the git pre-commit hook runs php-cs-fixer, PHPStan and Deptrac on staged files.

## 4. Automated verification
- Locally: the full set of quality tools from `AGENTS.md` (Environment).
- CI: `.github/workflows/ci.yml` runs the same checks on every push and pull request (style, PHPStan, Deptrac, `lint:container`, migrations + `schema:validate`, all tests including the `progress-contract` suite, OpenAPI export diff). It must be green before merge.

## 5. Agent review
Once the subagents exist (task 5): `domain-modeler` (ubiquitous language, aggregate and context boundaries) then `code-reviewer` (shared checklist `.agents/skills/review/checklist.md`). Until then the `review` skill covers both. Fix BLOCKER and MAJOR findings.

## 6. Manual diff review (checkpoint 2)
You read the diff yourself, looking at what automated tools miss:
- domain logic and invariants
- context boundaries (no cross-context entities, repositories or ORM relations)
- core purity (Progress)
- event contract backward compatibility
- migrations (new file, never an edited released one)
- retry / failure-transport decisions
- whether tests check behaviour rather than "didn't throw"

## 7. Retro
After each feature, ask: did the agent make a mistake or need correcting? Each one becomes exactly one of:
- a rule in `AGENTS.md` (or `CLAUDE.md` if Claude-only),
- a fix to a command, skill or hook,
- nothing, if it was a one-off (say why).

Log it in the table below. The task 7 DoD needs at least 2 rule-file changes from retros.

## Task log

| # | Spec | Branch / PR | Checkpoints passed | Retro outcome |
|---|---|---|---|---|
| 1 | 001 identity register/login | main (commits e08c238, 4cf9177) | spec ✅, plan ✅; manual diff review pending | Rule fixed: AGENTS.md folder list and "nothing built yet" line were stale after new Domain folders (updated). Plan assumption that `HandleTrait` unwraps exceptions was wrong (verify framework behaviour before planning around it). Tooling: block-dangerous hook false-positives on commands that merely mention the secrets file (task 3 follow-up). |
