# 001: Identity — register, login, current user

**Status:** approved (open questions settled: 12-char password, email as login name, single-use refresh tokens, `json_login`)
**Milestone / agentic task:** M1 (first slice) / task 7 (first run through `WORKFLOW.md`); also gives real uses for tasks 1, 2 and 3.

## M1 split

M1 is too big for one pass, so it is cut into three specs, each taken through the full workflow:

| Spec | Content | Closes |
|---|---|---|
| **001** (this one) | Identity: register, login, refresh, `GET /api/me`, JWT, first OpenAPI export | — |
| 002 | Outbox + inbox + Kafka wiring; `UserRegistered` event; Coaching consumer creates the `Coach` profile | Task 0 DoD (`UserRegistered` in Kafka UI) |
| 003 | Coaching API: coach profile, add athlete (no account), athlete list, `AthleteAccessPolicy` voter | M1 |

001 emits no event: `register` only writes the user. 002 adds the event to the same handler.

## Goal

A person can create an account with email and password, log in to get a JWT plus a refresh token, and call an authenticated endpoint. Nothing else exists to protect yet; this is the foundation for 002 and 003.

## Acceptance criteria

Each is proven by a test written first (TDD). HTTP tests use `WebTestCase`; the domain is covered by plain unit tests.

- [ ] `POST /api/auth/register` with `{email, password}` returns **201** and `{id, email}`; the password is stored hashed, never returned.
- [ ] Email is normalised (trimmed, lower-cased) and must be valid; password must be at least 12 characters. Invalid input returns **422** with violations per field (Validator, not hand-written checks).
- [ ] Registering an email that already exists (case-insensitively) returns **409**. The unique index is the final guard, so two concurrent requests cannot both succeed.
- [ ] `POST /api/auth/login` with correct credentials returns **200** with `{token, refresh_token}`; wrong password or unknown email returns **401** with the same body (no user enumeration).
- [ ] `POST /api/auth/refresh` with a valid refresh token returns a new JWT; an invalid or used token returns **401**.
- [ ] `GET /api/me` with a valid JWT returns `{id, email}`; without a token or with an expired one returns **401**.
- [ ] `GET /api/health` and the three `/api/auth/*` routes stay public; everything else under `/api` requires a JWT.
- [ ] The domain `User` has no Symfony or Doctrine dependency (Deptrac green); `SecurityUser` is an adapter in Infrastructure.

## Out of scope

- The `UserRegistered` event, outbox and Kafka (spec 002).
- Coach and athlete profiles (spec 003).
- Email verification, password reset, logout-everywhere, rate limiting, roles beyond `ROLE_USER`. Each is a later spec if needed.
- Athlete accounts (ADR-005: profile managed by the coach).

## Affected contexts

- **Identity** only (new). No other context is touched, and nothing reads Identity's tables.

## Patterns used

| Pattern | Where | Teaches |
|---|---|---|
| Aggregate Root | `Identity/Domain/Entity/User` | one consistency boundary, built through the named constructor `User::register()` |
| Value Object | `Email`, `HashedPassword` (+ `UserId` from SharedKernel) | validation inside the type, `readonly`, named constructors |
| Repository (port + adapter) | `UserRepository` interface in Domain, `DoctrineUserRepository` in Infrastructure | Doctrine XML mapping outside the domain |
| Hexagonal / Ports & Adapters | `PasswordHasher` port (Application) with a Symfony `PasswordHasherInterface` adapter (Infrastructure) | the domain never sees Symfony |
| Adapter | `SecurityUser` implements `UserInterface`, wraps the domain `User` | Security component without leaking into the domain |
| CQRS (command bus) | `RegisterUser` command + handler on `command.bus` | Messenger buses, `#[AsMessageHandler]` |
| Domain exception | `EmailAlreadyRegistered` mapped to 409 | no `null` for failure |

Folders under `Identity/Domain/` follow the AGENTS.md rule: only `ValueObject/` and `Service/` are used so far. `User` is the first aggregate and the `UserRepository` port is the first repository port, so I will **propose** the folder names (`Entity/`, `Repository/`) in the plan and ask before creating them.

## Event contract (Published Language)

None. `UserRegistered` arrives with spec 002.

## Mobile API (`docs/api/openapi.json`)

New endpoints: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/refresh`, `GET /api/me`, plus the bearer security scheme. This is the first export, so `docs/api/openapi.json` is created in the same commit and the CI diff step starts checking it. No backward-compatibility concern yet (nothing consumed).

## Athlete Brief impact

None.

## Persistence

- Table `identity_user`: `id` (uuid), `email` (unique, stored lower-case), `password_hash`, `roles` (json), `created_at`.
- Table `refresh_tokens` from the gesdinet bundle (owned by Identity).
- Migrations in `migrations/Identity/` (namespace `DoctrineMigrations\Identity`, one shared timeline). No cross-context foreign keys.

## Packages that need your approval

AGENTS.md says not to add packages without asking. Proposed:

| Package | Why |
|---|---|
| `symfony/security-bundle` | firewalls, password hasher, `#[IsGranted]` (Laravel: guards + `Hash`) |
| `lexik/jwt-authentication-bundle` | JWT issue and verify (ADR-004) |
| `gesdinet/jwt-refresh-token-bundle` | refresh tokens (ADR-004) |
| `symfony/uid` | UUIDv7 ids (domain model uses UUIDv7) |
| `symfony/serializer` | request/response mapping for `#[MapRequestPayload]` |
| `nelmio/api-doc-bundle` | OpenAPI export (ADR-003) |

`symfony/clock` is not needed yet: `created_at` is passed in from the injected PSR `ClockInterface`, which `framework-bundle` already provides.

## Risks and open questions

- **JWT keys:** lexik needs a key pair. Generate it with `lexik:jwt:generate-keypair`; keys and passphrase go in `.env.local` / the secrets vault, never committed (and I won't touch `.env.local`, so you create it).
- **`json_login` vs a custom login controller:** the plan uses the firewall's `json_login` with lexik success/failure handlers, which gives no controller to test directly; the HTTP tests cover it end to end.
- **Refresh token rotation:** gesdinet can single-use tokens. Proposed: yes (`single_use: true`).
- **Password minimum:** 12 characters is a proposal; change it if you prefer.
- **Email as the login name** (no separate username): proposed.
