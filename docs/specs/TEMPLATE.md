# NNN: <feature name>

**Status:** draft | approved | implemented
**Milestone / agentic task:** e.g. M1 / task 7 (which agentic task this feature practises)

## Goal
One or two sentences: what the user can do afterwards that they can't now.

## Acceptance criteria
Each one is testable and names the test that proves it (HTTP request for controllers, service call for services).
- [ ] ...

## Out of scope
- ...

## Affected contexts
Which bounded contexts change (see `docs/project/01-domain-model.md`) and how they talk to each other.

## Patterns used
Names from `docs/project/02-design-patterns.md`, where each lives, and why. Copied into the PR description.

## Event contract (Published Language)
New or changed integration events: name, version, payload, partition key, schema file in `docs/contracts/`, consumers, retry/failure decision. "None" if no events change.

## Mobile API (`docs/api/openapi.json`)
New or changed endpoints and whether the change is backward compatible for the mobile app. "None" if no API change.

## Athlete Brief impact
Does it add or change what appears in the Athlete Brief? "None" if not.

## Persistence
Tables, migration (`migrations/<Context>/`), cross-context foreign keys (and why they are safe).

## Risks and open questions
- ...
