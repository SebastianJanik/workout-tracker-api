# Review checklist

Single source for the `review` skill and the `code-reviewer` subagent. It holds general rules only. Repo-specific rules (folders, commands, prohibitions, decisions) live in `CLAUDE.md`, nested `CLAUDE.md` files and `docs/`; read those for the changed paths and treat a violation as a finding. Apply only the sections the diff touches. Review the code, not product decisions recorded in the docs.

## 1. Project rules

- The change follows `CLAUDE.md`, any nested `CLAUDE.md` for the touched paths, and the architecture docs it points to.
- Decisions recorded in ADRs / decision logs are not silently reversed; a changed decision comes with a new record.
- Files that must be updated together with a change (API contract, schemas, decision logs, docs) are updated in the same change.
- Existing guards (tests, static analysis, architecture checks, code style) pass and were not weakened to make the change pass. Tests and contracts are not edited just to turn them green.

## 2. DDD and architecture

- Dependencies point inward: domain depends on nothing outside itself (no framework, ORM, HTTP, I/O); application orchestrates; infrastructure and presentation adapt.
- Bounded contexts stay separate: no reaching into another context's entities or repositories; communication through events or a published interface.
- Aggregates: small, protect their own invariants, referenced by identity, one aggregate changed per transaction.
- Behaviour lives in the domain (entities, value objects, domain services), not in controllers, handlers or setters. Watch for an anaemic model.
- Value objects are immutable, validated on construction and compared by value; primitives that carry domain meaning are wrapped.
- Ports (interfaces) belong to the inner layer, implementations to the outer one.
- Domain language: names match the ubiquitous language in the docs; no technical names for domain concepts.
- Time, randomness and IDs are injected, never created inside the domain.
- Controllers and handlers stay thin: map input, delegate, map output.
- Code sits in the right layer, context and folder according to the repo's structure rules.

## 3. Clean code

- **Bugs:** off-by-one, wrong comparison or operator, unhandled empty/zero/null input, shared-state mutation, rounding errors, non-determinism, behaviour that contradicts the name or docblock, swallowed exceptions, wrong error type.
- **Repetition:** duplicated logic or near-identical methods and test setups with the same reason to change. Code that only looks alike is not duplication.
- **Dead code:** unused classes, methods, parameters, properties, constants, imports, unreachable branches, commented-out code, unused enum cases.
- **Abstraction:** one responsibility per class and method, one level of abstraction per method, small public surface, no leaking of internals, no flag arguments that split a method into two behaviours.
- **Overengineering:** interfaces with a single implementation and no planned second, indirection that adds nothing, unused configurability, generic code for one case, speculative features. Say what to delete.
- **Naming and readability:** intention-revealing names, no abbreviations or misleading names, short methods, guard clauses over deep nesting, no magic numbers or strings, comments explain why not what.
- **Error handling:** domain exceptions with meaningful messages instead of `null` or error codes for absence; fail fast on invalid state.
- **Type safety:** strict types, precise types over `mixed`/arrays-of-anything, immutability by default, `final` unless designed for extension.

## 4. Design patterns

- Where a known pattern clearly fits (e.g. Strategy, Factory / named constructor, Specification, State, Decorator, Repository, Adapter, Null Object), suggest it and say where it would live. Check the repo's pattern catalogue first.
- Do not suggest a pattern for its own sake; if the plain code is clear, say nothing.
- A pattern that is used must earn its place; flag one that adds ceremony without a second use case.
- Flag common anti-patterns: god class/aggregate, anaemic model, service locator, static/global state, primitive obsession, feature envy, shotgun surgery, leaky abstraction.

## 5. Security and risk

Flag anything on the diff that touches these; severity is BLOCKER when exploitable.

- **Injection:** SQL/DQL built by string concatenation (use bound parameters), shell/command execution with user input, unsafe deserialisation, template/XSS output without escaping, path traversal in file access, header or log injection.
- **Authentication and sessions:** weak or home-made crypto, passwords or tokens compared non-constant-time or stored in plain text, missing expiry or revocation, tokens in URLs or logs.
- **Authorisation:** every endpoint and handler checks that *this* caller may act on *this* resource (object-level access, role vs ownership), not only that they are logged in. Look for IDOR (user supplies an ID, code does not verify ownership), missing checks on read/list/export as well as write, privilege escalation via mass assignment.
- **Input validation:** all external input (request bodies, query, headers, files, messages from queues) is validated for type, range, size and format at the boundary; allow-lists over deny-lists; file uploads checked.
- **Secrets and config:** no credentials, keys or tokens in code, tests, fixtures, logs or committed env files; no secrets in error messages.
- **Data exposure:** responses and logs do not leak internal fields, stack traces, personal data or other users' data; error messages do not reveal internals; personal data is minimised.
- **Transport and web:** no disabled TLS verification, CORS not wildcard for credentialed requests, CSRF protection where cookies are used, safe cookie flags, security headers where relevant.
- **Denial of service and abuse:** unbounded queries, loops, pagination, payload sizes or retries; missing rate limiting on auth and expensive endpoints; regex with catastrophic backtracking.
- **Dependencies:** new or upgraded packages are needed, maintained and from a trusted source; no known-vulnerable versions.
- **Concurrency and integrity:** race conditions and check-then-act, missing transactions or locks, non-idempotent handlers for at-least-once messaging, partial failure leaving inconsistent state.
- **Reliability:** swallowed errors, missing timeouts and retries on outbound calls, resources not released.

## 6. Persistence and performance

- No N+1 queries, no unbounded result sets, queries use indexes, migrations match the mapping and are reversible or safe.
- Released migrations are not edited.
- Transactions are scoped to one unit of work; no I/O inside loops that could be batched.

## 7. Messaging and integration

- Messages and events are immutable, serialisable and contain primitives, not entities.
- Consumers are idempotent; events are published reliably with the state change (e.g. outbox); ordering/keys are chosen deliberately.
- Retries, failure handling and schema backward compatibility are considered for each new message type.

## 8. Tests

- Tests cover behaviour and edge cases (empty, zero, boundary, invalid input, failure paths), not implementation details.
- New behaviour has a test that would fail without it; a bug fix has a regression test.
- Tests are deterministic, independent and readable; test data comes from builders or mothers where the repo has them.
- Security-relevant rules (authorisation, validation) have tests for the denied case, not only the allowed one.

## 9. Repo hygiene

- No debug output, stray files, secrets or local config in the diff.
- No new dependencies without prior approval when the repo requires it.
- Language and commit-message conventions of the repo are followed.
