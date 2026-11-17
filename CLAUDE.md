@AGENTS.md

# Claude Code extras

Everything shared lives in `AGENTS.md`; add here only what applies to Claude Code.

- Priority #1 of the project is practising agentic workflows (tasks 1-7). For each feature, say which agentic task it covers. When a task's DoD is met, tick it and update its status in `docs/project/03-implementation-plan.md`.
- Shared skills live in `.agents/skills/`; `.claude/skills` is a symlink to it (Claude Code doesn't read `.agents/skills/`). Add new skills there, never as copies under `.claude/`.
- Claude-only automation lives in `.claude/` (hooks, subagents, settings). The rules those enforce are written in `AGENTS.md`, not here.
- Nested rule files: put the rules in `AGENTS.md` next to the code and add a one-line `CLAUDE.md` containing `@AGENTS.md` beside it, because Claude Code doesn't read nested `AGENTS.md` on its own.
