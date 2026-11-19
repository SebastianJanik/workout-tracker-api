# 004: Multilingual API messages

**Status:** approved
**Milestone / agentic task:** cross-cutting (before M1 closes) / task 7 (second run through `WORKFLOW.md`). Numbered 004 because 002 and 003 are reserved by spec 001's M1 split.

## Goal

Every message the API shows to a person comes from the backend, in that person's language (English by default, Polish on request). No user-facing text is written in PHP code: code carries translation keys, translation files carry the text. The whole app follows this rule, in every bounded context. Every error the API returns, whatever produces it, has the same structure. Builds on the `DomainExceptionListener` from spec 001.

## Rules (also go into `AGENTS.md`)

- User-facing text (error messages, validation messages, anything the API returns for a person to read) is never a literal in PHP. Code holds keys, files hold text.
- Log messages and developer-facing text stay in English and are never translated.
- Each bounded context keeps its translation files in `src/<Context>/translations/`, one file per locale, same set of keys in every enabled locale.
- Custom validator constraints use keys for their messages too.

## Error contract

One structure for every error (RFC 9457 "problem details", which is what Symfony already emits):

```json
{ "type": "about:blank", "title": "Conflict", "status": 409, "detail": "…translated…" }
```

- `title` is the translated status text and `detail` the translated message; both follow the request locale.
- Validation errors (422) add `violations: [{ "field": "email", "message": "…translated…" }]`.
- No error code is sent. An uncaught error (500) returns a generic translated `detail` and never the exception text; the debug trace appears only when `kernel.debug` is on.
- A single `ApiErrorFactory` (SharedKernel, Presentation) builds this body and does the translation. No other code assembles an error body.
- Producers that all go through it: Symfony's error pipeline (via a Serializer normalizer), the JWT bundle's failure events (`on_jwt_not_found`, `on_jwt_invalid`, `on_jwt_expired`, `on_authentication_failure`) and the refresh bundle's failure handler.

## Acceptance criteria

Each is proven by a test written first (TDD). HTTP tests use `WebTestCase`; the rest are plain unit tests.

- [ ] A domain exception that maps to HTTP implements `TranslatableException` (new, `SharedKernel/Domain/Exception`): `messageKey()` and `messageParameters()`. Its constructor takes a key and parameters, never free text; `getMessage()` returns the key (readable in logs). All existing domain exceptions are migrated. Unit tests on the base class and on the migrated exceptions.
- [ ] `DomainExceptionListener` translates the key with `TranslatorInterface` (translation domain = the context), and a Serializer normalizer renders every exception through `ApiErrorFactory` into the error contract above. HTTP test: registering a duplicate email returns 409 with translated `title` and `detail`.
- [ ] `ErrorShapeTest` (parametrized) triggers every error source and asserts the same keys (`type`, `title`, `status`, `detail`, plus `violations` only on 422): unknown route (404), wrong method (405), no token (401), bad login (401), expired JWT (401), invalid refresh token (401), forbidden (403, via a test-only route), duplicate email (409), invalid input (422) and a forced uncaught error (500, no exception text in the body).
- [ ] The locale comes from `Accept-Language`, matched against the enabled locales `en` and `pl`; a missing header or an unsupported language falls back to `en`. HTTP tests: `Accept-Language: pl` gives Polish, `fr` and no header give English.
- [ ] Validation violations (422 on register) and security errors (login 401, missing or expired JWT 401) come back in the requested locale. HTTP tests with `pl`.
- [ ] Parity test: for every context, the `en` and `pl` files define the same set of keys, and every key used by a translatable exception exists in both.
- [ ] A test fails when an exception implementing a marker (`Conflict`, `NotFound`, `InvalidInput`) is not translatable, so a hard-coded message cannot slip in.
- [ ] `docs/api/openapi.json` documents the `Accept-Language` header and has one shared `Error` schema (and `ValidationError`) that every error response references; CI's export diff stays green.
- [ ] `AGENTS.md` contains the rules above.

## Out of scope

- A per-user stored locale preference and a profile endpoint to set it (a later spec; the header is enough for the mobile app).
- Locales other than `en` and `pl`.
- Email content (no emails exist yet).

## Deferred, but required in the specs that create the entities

Translatable data (exercise names, drills, warm-ups, and similar catalogue content) is a must-have. It needs its own design (translations stored with the data, or a translation table per entity) and belongs in the specs that introduce those entities (Strength, Padel, TrainingLibrary). Each of those specs must say how its data is translated.

## Affected contexts

- **SharedKernel:** `TranslatableException` interface, the extended `DomainExceptionListener`, an `Accept-Language` locale listener, `ApiErrorFactory` and the error-body normalizer, the listeners for the JWT bundle's failure events, and the translations of the generic error titles.
- **Every bounded context** follows the rules above and supplies its own files in `src/<Context>/translations/`.
- No cross-context communication.

## Patterns used

| Pattern | Where | Teaches |
|---|---|---|
| Factory | `ApiErrorFactory` is the single place that builds an error body | one contract, no producer assembles its own |
| Hexagonal (Ports & Adapters) | the domain holds a key, the Presentation edge adapts it to text with the Translator | the domain never sees Symfony or a language |
| Factory Method (named constructors) | exceptions such as `EmailAlreadyRegistered::withEmail()` keep building the key and parameters | no free text can be passed in |
| Observer (kernel events) | `DomainExceptionListener` and the locale listener on `kernel.request` and `kernel.exception` | `#[AsEventListener]`, priorities |
| Shared Kernel | `TranslatableException` and the markers shared by all contexts | one translation convention for the whole app |

## Event contract (Published Language)

None.

## Mobile API (`docs/api/openapi.json`)

- New optional request header `Accept-Language` (`en`, `pl`).
- All error responses now share one structure (see Error contract). This **changes** the JWT bundle's current 401 body (`{code, message}`) to `{type, title, status, detail}`; nothing consumes it yet (the mobile app does not exist), so it is safe now and would not be later.
- `title` and `detail` are in the requested language. The app shows the server text; it does not parse it. No stable error code is sent.

## Athlete Brief impact

None.

## Persistence

None.

## Risks and open questions

- **Refresh-bundle hook:** the failure handler of the `refresh_jwt` authenticator is a firewall option; confirm in the plan that it can return our body for every failure path (unknown, expired and already-used token).
- **Custom validator constraints** added later will need keys for their messages; the AGENTS.md rule covers it, a missing one would be caught by the parity test only if it is a domain exception.
- **Messages already translated by Symfony** (Validator, Security) are expected to come back in Polish without files of ours once the translation component is installed; the Polish HTTP tests will prove it.
