# Strength analytics core: local rules

Extends the root `AGENTS.md` (pure core, injected time, contract tests). Only what is specific to this directory:

- No static state or logging in the core. Sort explicitly (by day, then id) before aggregating; never rely on input order.
- `StrengthReport`: `exercises` is a `list<ExerciseProgress>` sorted by exercise id, then load; `sessionsConsidered` is a sorted `list<string>` of session ids inside the period. Changing this shape is a contract change.
- New formulas or metrics are separate classes under `Service/` (Strategy, like `EpleyFormula`), not branches inside `StrengthAnalyzer`.
