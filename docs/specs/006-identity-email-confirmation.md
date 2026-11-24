# 006: Identity — email confirmation and no user enumeration on register

**Status:** draft, reviewed (to be implemented after spec 005)
**Milestone / agentic task:** M1 hardening / task 7 (fourth run through `WORKFLOW.md`); another run of task 5 (implementer → domain-modeler → code-reviewer).

## Goal

`POST /api/auth/register` no longer reveals whether an email already has an account. A new account starts **pending** and becomes usable only after the owner confirms the email. If someone registers an address that already has an account, the owner gets a "someone tried to register with your address" email and nothing else happens. The caller sees the same response in every case.

## Acceptance criteria

Each is proven by a test written first. HTTP tests use `WebTestCase`; handlers use `KernelTestCase` with the real repository (ADR-015); the domain is covered by plain unit tests. Mail is checked with Symfony's `MailerAssertionsTrait`.

- [ ] `POST /api/auth/register` with `{email, password}` returns **202** with an empty body for a new email, for an already confirmed email, and for a pending email. A test sends all three and asserts identical status, headers that matter (`Content-Type`) and body.
- [ ] New email: a `User` is stored with status `pending` (password hashed, same validation as today: valid email, 12+ characters, **422** otherwise) and a confirmation email with a single-use link is queued for that address.
- [ ] Email of a **confirmed** user: nothing is written; an "already registered" email is queued for that address. It contains no token and no account data.
- [ ] Email of a **pending** user: the stored password hash is replaced by the new one, the old token is invalidated and a fresh confirmation email is queued (latest registration wins; this is also how a user asks for a new link, so there is no separate resend endpoint).
- [ ] `GET /confirm-email?token=...` is a **web page** (not an API route, not in OpenAPI). A valid token activates the pending user and returns **200** with an HTML page saying the account was created and the person can now log in in the app. An unknown, expired (24 h) or already used token returns **400** with a page saying the link is invalid or expired ("if you already confirmed, just log in"), the same page for all three cases. The token is consumed together with the activation (one transaction). Page text comes from translation keys (`identity.confirmation.*`, `en` and `pl`).
- [ ] **Per-IP limit:** more than 10 `POST /api/auth/register` calls per hour from one IP returns **429** with the translated error shape and a `Retry-After` header. Applies to every request, whatever the email, so it reveals nothing about accounts.
- [ ] **Per-email limit:** at most 3 registration attempts per hour per email (normalised) take effect. Further attempts do nothing at all (no write, no mail) and still return the same **202**, in every branch (new, pending, confirmed), so the limit can't be used to probe accounts and a victim's inbox can't be flooded. Tested with 4 calls for each of the three branches.
- [ ] Tokens are random (32 bytes, `random_bytes`) and only their SHA-256 hash is stored, so a database leak does not yield working links.
- [ ] A pending user cannot log in: `POST /api/auth/login` returns the same **401** body as a wrong password. No token or refresh token is issued, so nothing else needs to check the status.
- [ ] Two concurrent registrations of the same email cannot both create a user: the unique index violation is caught and treated as the "email exists" path (handler test on the race, not a mock).
- [ ] The register handler never throws `EmailAlreadyRegistered`. The exception and its translation keys (`email.already_registered`, `en` and `pl`) are removed, and the translation parity test stays green.
- [ ] Emails are text only, built from translation keys (`identity.mail.*`, `en` and `pl`), never literals in PHP. The locale is carried in the message as a primitive.
- [ ] Existing rows in `users` become `active` through the migration, so current accounts keep working.
- [ ] Domain stays free of Symfony/Doctrine (Deptrac green). `docs/api/openapi.json` is updated in the same commit.

## Out of scope

- Rate limiting of login, refresh and every other route (only register is throttled here).
- Mobile deep links / universal links for the confirmation link (the link opens a web page).
- Password reset, change of email, resend endpoint, purging of pending users that never confirm (a scheduled cleanup is a follow-up).
- `UserRegistered` event, outbox, Kafka (spec 002). It will now be emitted on **confirmation**, not on registration; spec 001's M1 split table should say so when 002 is written.
- HTML emails, Twig, email branding.
- Closing the remaining timing difference on login (unknown user vs wrong password). Accepted in spec 001.

## Affected contexts

- **Identity** only. Nothing else reads its tables.
- **SharedKernel**: a Messenger routing line for the mail message, and a new marker `TooManyRequests` (429) with one line in `DomainExceptionListener`, as AGENTS.md prescribes for a new status.

## Patterns used

| Pattern | Where | Teaches |
|---|---|---|
| Aggregate Root + state | `User` gets `UserStatus` (Pending, Active); `User::register()` creates a pending user, `activate()` is the only transition | invariants inside the aggregate; no setters |
| Value Object | `UserStatus` (enum), `EmailConfirmationToken` (hash + expiry), `ConfirmationTokenValue` (plain token, only in memory and in the email) | validation and hashing inside the type |
| Repository (port + adapter) | `EmailConfirmationTokenRepository` (port in `Domain/Repository`, Doctrine adapter) | single-use token lookup by hash |
| Hexagonal / Ports & Adapters | `AccountMailer` port (Application) with a `SymfonyAccountMailer` adapter (Infrastructure) | the handler asks for "send confirmation", never sees `MailerInterface` |
| CQRS | `RegisterUser` (now returns nothing) and new `ConfirmEmail` on `command.bus` | a command that always succeeds from the caller's view |
| Domain exception | `InvalidConfirmationToken` (translated; the web controller turns it into the failure page) | one outcome for unknown/expired/used, so tokens can't be probed |
| Throttling (Symfony RateLimiter) | `RegistrationThrottle` port (Application) with an adapter over two `RateLimiterFactory` limiters (per IP, per email); `RegistrationThrottled` exception with the `TooManyRequests` marker | protecting an endpoint without touching domain code; a limit that behaves the same for every input |
| Asynchronous messaging | Mailer's `SendEmailMessage` routed to a Doctrine transport | sending mail off the request path also removes the SMTP latency that would tell the two branches apart |

New `Identity/Domain` folders: none beyond the ones already used (`Aggregate/`, `ValueObject/`, `Repository/`, `Exception/`). Whether the token is its own aggregate or part of `User` is a question for `domain-modeler`; the proposal is a separate entity because it has its own lifecycle and table.

## Event contract (Published Language)

None. (`UserRegistered` is still spec 002.)

## Mobile API (`docs/api/openapi.json`)

- `POST /api/auth/register`: **201 `{id, email}` becomes 202 with no body**, 409 disappears, 429 is added. Breaking in principle, but nothing consumes the API yet (spec 001), so no migration action is needed; the commit message just says so.
- `POST /api/auth/login`: unchanged shape; unconfirmed accounts get the existing 401.
- The confirmation page `GET /confirm-email` is served by the backend as HTML and is deliberately not part of the OpenAPI document.

## Athlete Brief impact

None.

## Persistence

- `users`: new column `status` (varchar, `pending`|`active`), migration sets existing rows to `active`, default for new rows is set by the domain.
- New table `email_confirmation_tokens`: `id`, `user_id` (FK to `users`, same context), `token_hash` (unique), `expires_at`, `used_at` (nullable). One live token per user: a new one replaces the old.
- New table for the mail transport (`messenger_messages` already exists for the Doctrine transport; reuse it with a dedicated queue name instead of a new table).
- Migration in `migrations/Identity/`. No cross-context foreign keys.

## Configuration and packages

| Item | Detail |
|---|---|
| `symfony/mailer` (**approved**) | install with `composer require`; check `git status` afterwards for recipe leftovers (Docker files) |
| `MAILER_DSN` | `.env` default `smtp://mailpit:1025` (Mailpit is already in compose, UI on :8025); real DSN goes in `.env.local` / vault, which I won't touch |
| `config/packages/messenger.yaml` | new transport `mail` (Doctrine, own queue), routing for `SendEmailMessage`, retry (3 attempts, exponential) then the failure transport; test env uses `in-memory://` and a test consumes it |
| `symfony/rate-limiter` (**approved**) | two limiters in `config/packages/rate_limiter.yaml`-style config: `register_ip` (sliding window, 10/hour) and `register_email` (sliding window, 3/hour). State lives in a cache pool; the test env uses an array pool so tests don't leak into each other (DAMA rolls back the database, not the cache) |
| Confirmation URL | built with the router (`url()` equivalent, absolute) from the `GET /confirm-email` route; needs `framework.router.default_uri` from an env var, because the mail may be built where there is no request |
| Page rendering | no new package: the controller returns a small HTML `Response` with escaped, translated text. Twig would be the natural tool, but it is a new package, so it is only proposed if the pages grow |
| Sender address | parameter `app.mail_from` |

## Risks and open questions

- **Equal behaviour, not only equal responses.** Three branches differ in work: new (hash + 2 inserts), pending (hash + update + token), confirmed (no hash). Proposal: always hash the password first, then branch, and queue the mail in every branch, so the request time is dominated by the same hash. A timing test is flaky, so it is not an acceptance test; the equal-work rule is checked in code review.
- **Pending-account squatting.** Someone can register your email with their password; if you then register, "latest wins" replaces their password and invalidates their link, so only the real owner (who sees the mail) can confirm. The reverse case (attacker registers after you but before you confirm) invalidates your link; you register again. Acceptable for now; rate limiting makes it harder to abuse.
- **Throttle side effect:** an attacker can burn the 3 attempts per hour for a victim's email, so the victim's own registration does nothing for up to an hour (they see the same 202 and get no mail, and can retry later). Accepted: the alternative is letting anyone flood the victim.
- **Limiter storage:** a cache pool is fine on one node; with several app servers it needs shared storage (Redis). Not an issue locally.
- **IP behind a proxy:** the per-IP limit uses `Request::getClientIp()`; trusted proxies must be configured in production or every user shares one IP.
- **GET confirms the account:** link scanners that prefetch URLs in the inbox can consume the token, so the person then sees the "invalid" page for an account that is in fact active. The failure page says "if you already confirmed, just log in". A confirm button (POST) would avoid this but means an extra tap; revisit if it happens.
- **Token lifetime:** 24 h proposed.
- **Email locale:** proposal is the same resolution as the translated errors in spec 004 (default `en`); the locale string goes into the message.
- **Token inside the queue:** the plain token is in the queued mail message in the database. Same data that goes out by email, and the queue row is deleted after sending; acceptable.
- **Existing tests change:** the register tests from spec 001 (201, 409) are rewritten, not deleted without replacement.
