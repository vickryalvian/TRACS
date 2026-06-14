# TRACS PHP API Architecture Plan

## Purpose

PHP will evolve gradually from page-level rendering and mixed endpoint logic
into a clearer backend/API provider. This phase documents the direction only;
it does not move files, change routes, normalize responses, or alter business
logic.

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

## Future Structure

```text
api/
  Controllers/
  Requests/
  Resources/
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

## First Backend Preparation Batch

After architecture approval and test-baseline implementation:

1. Add response-contract characterization tests.
2. Introduce shared response helpers that preserve existing output by default.
3. Introduce a reusable request/validation boundary for one pilot endpoint.
4. Keep its public route and business service unchanged.
5. Verify all role, CSRF, object-scope, and audit behavior before reuse.
