---
name: review
description: Review the current git diff (staged, unstaged and untracked) against the project checklist and report findings as BLOCKER/MAJOR/MINOR with file and line. Use before committing, after finishing a feature, or when asked to "review" local changes in this repo. Read-only: never edits code.
allowed-tools: Read, Grep, Glob, Bash(git status:*), Bash(git diff:*), Bash(git log:*), Bash(docker compose exec -T php vendor/bin/*), Bash(docker compose exec -T php bin/console lint:container)
---

# review

Review the local changes against [checklist.md](checklist.md). The checklist is the single source of review rules; the future `code-reviewer` subagent (task 5) must read the same file instead of keeping a copy. The checklist is deliberately generic; repo specifics come from `AGENTS.md` and `docs/`, so it needs no edits when the code changes.

## Steps

1. **Collect the change.** Run `git status --short`, then `git diff HEAD` (staged + unstaged). Read every untracked file listed by `git status` too. If there is nothing, say so and stop.
2. **Load context only as needed.** Read the `AGENTS.md` rules that touch the changed paths, any nested `AGENTS.md` in those directories, and the docs they point to (architecture, ADRs, decision logs) for the areas the diff touches.
3. **Run the guards** listed in `AGENTS.md` (tests, static analysis, architecture check, code style, container lint), in the container with `-T`. Report a failing guard as a finding; do not fix it. If Docker is not running, say so instead of skipping silently.
4. **Trace the blast radius.** A line that looks safe in the diff can break code outside it. For every changed or removed symbol (class, method, signature, parameter, return type, constant, enum case, config key, route, message/event shape, DB column, mapping, env var, translation key), search the whole repo with Grep/Glob for its usages, including `src/`, `tests/`, `config/`, `migrations/`, `docs/` and the API contract. Check that each caller, implementer, subclass, test, mapping and consumer still works with the new behaviour. Pay special attention to:
   - changed defaults, nullability, return values, thrown exceptions, ordering or rounding that callers silently relied on;
   - widened or narrowed types, renamed or removed members, and changed interface contracts with other implementations;
   - shared helpers, value objects and base classes: list every dependant, not just the ones in the diff;
   - serialised or persisted shapes (messages, JSON, DB, cache) that older data or other services still produce or read;
   - config, DI wiring, routes and tests that reference the old name or behaviour.
   Report each broken or doubtful dependant as a finding at the dependant's `file:line`, naming the changed line that causes it. If the search finds nothing affected, say so in one line ("Blast radius: N usages of X checked, none affected"). Do not read the whole repo; follow the symbols.
5. **Apply the checklist** to the diff. Only sections whose area the diff touches apply; skip the rest without comment.
6. **Report.** Use the format below. Findings come from the diff and from code it affects; do not review unrelated untouched code.

## Output format

```
## Review: <one-line summary of the change>

[BLOCKER] path/to/File.php:42 - what is wrong, why it matters, suggested fix
[MAJOR]   path/to/File.php:10 - ...
[MINOR]   path/to/File.php:7  - ...

Guards: <name> ✓/✗ for each guard that was run
Blast radius: <symbols traced and what was found>
Verdict: <ready to commit | fix BLOCKER/MAJOR first>
```

- BLOCKER: breaks a rule in AGENTS.md, a layer boundary, correctness, or is an exploitable security issue. Do not commit.
- MAJOR: likely bug, missing test for behaviour, or a pattern/anti-pattern problem.
- MINOR: naming, style, small simplification.
- No findings in a severity: omit it. No findings at all: say "No findings" and still print the guards line.
- Do not pad. Every finding needs a file:line and a concrete reason. Uncertain? Mark it "(unsure)" instead of asserting.

## Rules

- Never edit files, commit, or change the contract tests; only report.
- Explain Symfony concepts you mention with a Laravel analogy, per AGENTS.md.
