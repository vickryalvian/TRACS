# TRACS PHP API Architecture Plan

## Purpose

PHP will evolve gradually from page-level rendering and mixed endpoint logic
into a clearer backend/API provider. Phase 5 adds an isolated internal
foundation but does not change routes, normalize existing responses, or alter
business logic.

## Compatibility Boundary

Existing routes under `public/api/` remain valid while clients depend on them.
New structure should be introduced behind those public entry points:

```text
Browser
  -> public/api/<route>.php
     -> bootstrap and middleware
     -> request validation
     -> controller
     -> service transaction
     -> repository/query layer
     -> MySQL
```

Public API files stay thin. They must not become a second business-logic layer.

## Structure

```text
api/
  _bootstrap.php
  _response.php
  _request.php
  _auth.php
  _csrf.php
  _permissions.php
  _logging.php
  Controllers/       # future, when a real module needs it
  Requests/          # future
  Resources/         # future
includes/
services/
repositories/
middleware/
config/
migrations/
logs/
public/
  api/
```

Existing `core/`, `modules/`, and `public/api/` are migrated gradually. Do not
perform a repository-wide namespace or directory rewrite.

The Phase 5 files use the `TRACS\Api` namespace to avoid collisions with
existing global helpers such as `verify_csrf()` and
`tracs_require_permission()`. They accept the current `mysqli` connection
instead of opening another connection or introducing a container/framework.

Responsibilities:

| Layer | Responsibility |
| --- | --- |
| Public route | Method declaration, bootstrap, request handoff |
| Middleware | Authentication, session freshness, CSRF, permission, rate limit |
| Request/validator | Input normalization and field validation |
| Controller | Coordinate request, service, and response |
| Service | Business rules, transactions, linked effects, audit decisions |
| Repository | Reusable prepared queries and persistence |
| Resource/response | Stable output shape and safe serialization |

## Security Order

Every protected endpoint applies:

1. Direct-access and web-root protections.
2. Hardened session start.
3. Full authentication and pending-2FA rejection.
4. Idle-session expiry.
5. Active-account verification.
6. CSRF for state-changing methods.
7. Route permission.
8. Object ownership, division, role hierarchy, or exact-role restriction.
9. Request validation.
10. Transactional business operation.
11. Audit, notification, ticker, or linked-module effects.
12. Sanitized response and private error logging.

React-provided permission metadata affects only presentation. All checks above
remain server-side.

## Response Standard

Target JSON:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": [],
  "meta": {}
}
```

Recommended status use:

- `200` successful read/update/action.
- `201` successful creation where useful.
- `204` only when the client expects no body.
- `400` malformed request.
- `401` missing, expired, or incomplete authentication.
- `403` authenticated but forbidden.
- `404` missing resource or intentional concealment.
- `409` state/version/conflict failure.
- `422` field validation failure.
- `429` rate limited.
- `500` sanitized unexpected server failure.

Existing endpoint differences must be characterized before normalization.
Change response contracts only in separately reviewed compatibility batches.

Phase 5 response functions:

- `response_payload()` builds the five-key contract for tests and resources.
- `json_success()` emits a success envelope and exits.
- `json_error()` sanitizes the public message, emits an error envelope, and
  exits.
- Both response paths send JSON, `no-store`, and `nosniff` headers.

## Request And Query Conventions

- JSON request bodies for new React mutations unless file upload requires multipart.
- ISO `YYYY-MM-DD` dates and explicit date-time/timezone semantics.
- Snake_case API fields.
- Query reads use `page`, `per_page`, `sort`, `direction`, `q`, `start`, `end`,
  and named filters.
- Pagination, totals, allowed sorts, and active filters return under `meta`.
- Reject unknown sort columns instead of interpolating them into SQL.
- Validate uploads by content, size, decoding, and allowlist; serve protected
  evidence through permission-checked endpoints.

## Transactions And Side Effects

Services own transactions that span multiple writes. Audit, linked reminder,
task, notification, ticker, or source-module effects must not be silently lost
when the primary operation fails.

Where a non-critical side effect is intentionally best-effort, document and log
that choice. Do not duplicate source-module business rules in React or a
calendar/aggregation service.

## Database And Migration Direction

- MySQL remains the system of record.
- Centralize connection configuration without introducing a second connection
  pattern during migration.
- Use prepared statements and allowlisted identifiers.
- Add repositories only where query complexity or reuse warrants them.
- New database changes use paired `up.sql` and `down.sql`, a migration ledger,
  backup instructions, and verification queries.
- Prefer additive changes; do not remove legacy tables/columns until all PHP and
  React consumers are proven migrated.

## API Versioning

Do not version every existing endpoint merely for appearance. Introduce
`/api/v1/` only when a new stable contract must coexist with an incompatible
legacy contract. Compatible normalization can remain behind current routes.

## Observability

- Generate or accept a safe request ID for error correlation.
- Log private exception details server-side.
- Return only sanitized messages to clients.
- Never log passwords, session IDs, CSRF tokens, TOTP secrets, upload contents,
  or database credentials.
- Keep Super Admin log viewing bounded, sanitized, fixed-path, and exact-role
  restricted.

Phase 5 generates a safe request ID and includes it only in private logs by
default. It does not create a new log-viewing or server-monitoring endpoint.
`write_error_log()` redacts password/token/secret-shaped context keys through
the existing scrubber. Public errors continue through
`tracs_public_error_message()`.

## Phase 5 Foundation Batch

Implemented:

1. Internal helpers under `api/`, outside the public web root.
2. Standard future response envelope without changing existing endpoint output.
3. Hardened auth/session, permission, and CSRF wrappers around current rules.
4. JSON-object parsing, required fields, and strict ISO date validation.
5. Existing audit logger delegation and private correlated error logging.
6. Dependency-free CLI checks in `tests/php-api-foundation.php`.
7. No public endpoint, module migration, database migration, or route rewiring.

Validation:

```bash
find api tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/php-api-foundation.php
```

The CLI check covers envelope keys, malformed JSON, required fields, ISO date
validation, and unauthenticated JSON rejection. Authenticated-session,
permission, CSRF, audit persistence, and object-scope integration tests require
a disposable test database and fixture users; do not run those against
production data.

## Adoption Rule

The next backend batch should adopt the foundation for one new or explicitly
versioned pilot contract, not silently replace `public/api/_bootstrap.php`.
That batch must characterize the existing endpoint first and test:

1. Unauthenticated and pending-2FA `401`.
2. Idle session expiry.
3. Inactive-account rejection.
4. Valid and invalid CSRF for mutations.
5. Allowed and denied permissions.
6. Object ownership/division scope.
7. Validation `422` shape.
8. Audit persistence and sanitized unexpected errors.
9. Existing PHP client compatibility or an explicit version boundary.

## Phase 5.5 Pilot Adoption

The first adopter is:

```text
GET /api/v1/context.php
```

Implementation:

```text
public/api/v1/context.php  -> public route and authenticated bootstrap
api/v1/context.php         -> allowlisted response resource formatter
```

The route:

- Uses `TRACS\Api\bootstrap()` with GET as the only allowed method.
- Requires a fully authenticated, non-expired session and active account.
- Does not require a module permission because all authenticated frontend
  shells need their own server-authoritative permission map.
- Does not require CSRF for GET because it is read-only.
- Returns the current CSRF token and `X-CSRF-Token` header name for future
  mutations; this never removes server-side CSRF verification.
- Returns only user ID, display name, role slug/name, effective permission keys,
  CSRF handoff, and request ID.
- Uses `Cache-Control: no-store` and removes `X-Powered-By`.
- Does not expose email, password/status fields, division internals, 2FA data,
  server/runtime details, paths, logs, environment values, or database data.

The pilot does not replace or modify `public/api/_bootstrap.php`, Calendar APIs,
or Shift Assignment APIs.

## Phase 6 Shift Assignment Context

The first module-specific adopter is:

```text
GET /api/v1/shift-assignment/context.php
```

The route requires an authenticated active session and `shifts.view`. It is
GET-only, uses the existing `ShiftingAssignmentService` for permission and
role/division scope, and returns only allowlisted bootstrap/filter metadata.
It does not return assignments or replace the legacy Shift Assignment API.

Its permission flags are presentation hints. Future reads and writes must
repeat route permission and object-scope enforcement in PHP. Mutations must
also require CSRF and retain existing validation, transaction, audit,
notification, conflict, and workload rules.

See `docs/shift-assignment-api-contract.md` for the current data-flow,
permission, filter, business-rule, and future endpoint characterization.

## Phase 7 Shift Assignment Read Resource

Phase 7 adds:

```text
GET /api/v1/shift-assignment/assignments.php
```

The thin public route validates authentication, `shifts.view`, GET, and query
input, then delegates all reads and calculations to the existing
`ShiftingAssignmentService`. The internal v1 resource allowlists output fields
and supplies ISO raw dates plus `dd-mm-yyyy` display dates.

This batch intentionally does not introduce a parallel repository or service.
The current module service already owns prepared queries, role/division scope,
workload calculations, holidays, and warning rules. Extracting those layers
before behavioral parity would increase risk.

Role filtering is a response filter over the already scoped agent set; it
cannot expand access. Future write resources remain prohibited until separate
CSRF, permission, object-scope, transaction, validation, audit, notification,
and rollback contracts are approved.

## Phase 13 Shift Assignment Write Planning

`docs/shift-assignment-write-api-contract.md` defines the future mutation
boundary without adding routes. New JSON writes will use the Phase 5 bootstrap,
CSRF header validation, five-key envelope, request IDs, service transactions,
server-side scope, and sanitized error handling.

The current database has broad Shift Assignment permissions. Proposed granular
keys are documentation-only until a separately reviewed paired `up.sql` and
`down.sql` migration is approved. Implemented endpoints must not require
unseeded permissions or weaken the existing compatibility gate.

Create is the first recommended write slice. Update follows with concurrency
protection. Delete remains blocked because the current module has no assignment
delete behavior. Template and copy operations require preview/confirmation,
idempotency, audit, and an explicit partial-success or transaction contract.

Phase 27 documents the future template/copy v1 shape. Phase 28 adds only the
first non-mutating preview route. Bulk scheduling must remain split into
non-mutating preview and confirmed commit:

- `POST /api/v1/shift-assignment/templates/preview.php`
- `POST /api/v1/shift-assignment/templates/commit.php`
- `POST /api/v1/shift-assignment/templates/copy-preview.php`
- `POST /api/v1/shift-assignment/templates/copy-commit.php`

The implemented preview endpoint must not wrap legacy behavior that changes
draft template state. It returns in-memory preview items, conflicts, warnings,
and blocked rows only. Commit endpoints must require authenticated session,
CSRF, exact permission/scope checks, confirmation, conflict re-checks, audit
evidence, and rollback data before writing.

## Phase 14 Controlled Create Resource

The existing assignments route branches by method:

```text
GET  -> shifts.view -> existing scoped read pipeline
POST -> CSRF -> exact super_admin -> explicit shifts.manage -> saveAssignment()
```

The internal v1 resource owns strict JSON-to-service normalization and safe
response serialization. It does not duplicate persistence queries. The
existing service continues to own prepared writes, transactions, overlap and
availability checks, holiday linkage, notifications, warnings, and assignment
audit behavior.

Because `shifts.create` is not seeded, Phase 14 uses the documented temporary
exact-role gate. Broader access requires a later paired permission migration
and role tests. The React client does not call POST in this phase.

Phase 15 validates the route against disposable MySQL using a schema-only
clone, dedicated fixtures, real session/CSRF state, and guaranteed teardown.
The request harness is test-only and adds no production authentication bypass.

Automated checks:

```bash
php tests/php-api-foundation.php
php tests/php-api-contract.php
find api tests public/api/v1 -name '*.php' -exec php -l {} \;
```

Live unauthenticated and method checks:

```bash
curl -i http://127.0.0.1:8080/api/v1/context.php
curl -i -X POST http://127.0.0.1:8080/api/v1/context.php
```

Expected results are `401` for unauthenticated GET and `405` plus `Allow: GET`
for POST. Both responses use the five-key JSON envelope and include
`meta.request_id`.
