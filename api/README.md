# TRACS Internal API Foundation

This directory contains the isolated Phase 5 foundation for future JSON
endpoints. It is outside the current `public/` web root and is not wired into
existing routes.

Functions live in the `TRACS\Api` namespace:

- `json_success()` and `json_error()` emit the standard response envelope.
- `require_auth()` validates full authentication, idle timeout, and active user.
- `require_permission()` and `require_any_permission()` reuse current database
  permission rules.
- `verify_csrf()` reuses the current session token and request-header strategy.
- `get_request_json()` parses JSON objects with explicit malformed-body errors.
- `validate_required_fields()` supplies basic required-field errors.
- `write_audit_log()` delegates to the existing user activity logger.
- `write_error_log()` records a private request ID and redacted context.
- `safe_date_parse()` validates backend ISO dates without changing UI formats.
- `current_user()` resolves the active session user through existing helpers.

Future public route pattern:

```php
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/_bootstrap.php';

$context = \TRACS\Api\bootstrap(
    $conn,
    methods: ['POST'],
    permissions: ['example.manage']
);

$input = \TRACS\Api\get_request_json();
```

This example is documentation only. No current route loads this foundation.
Future endpoint batches must add contract, authentication, permission, CSRF,
object-scope, validation, audit, and regression tests before production use.

## Pilot Contract

Phase 5.5 adds one public adopter:

```text
GET /api/v1/context.php
```

The route is available only to a fully authenticated, active account. It
returns the current user's ID, display name, role, effective permission keys,
the current CSRF token/header name, and a request ID. It does not return email,
account status fields, division internals, 2FA data, credentials, environment
data, server metrics, paths, logs, or database details.

GET does not require CSRF because it is read-only. The returned token is for
future same-session state-changing requests, which must still run
`verify_csrf()` server-side. The endpoint intentionally has no module
permission requirement: every authenticated React shell needs to discover the
permissions PHP already grants. Module routes remain responsible for enforcing
their own permissions and object scope.

## Shift Assignment Context

Phase 6 adds:

```text
GET /api/v1/shift-assignment/context.php
```

It requires `shifts.view` and returns safe, role-scoped bootstrap metadata for
a future Shift Assignment React module. It does not return assignments, mutate
data, replace the legacy route, or authorize an action by itself.

```text
public/api/v1/shift-assignment/context.php
  -> authenticated GET bootstrap and shifts.view
api/v1/shift-assignment/context.php
  -> allowlisted resource formatter
```

See `docs/shift-assignment-api-contract.md`.

## Shift Assignment Read Resource

Phase 7 adds:

```text
GET /api/v1/shift-assignment/assignments.php
```

It requires `shifts.view`, validates an ISO date range and allowlisted filters,
and delegates scoped reads and calculations to the existing module service.
The v1 resource formatter excludes email, notes, credentials, actor IDs, and
server internals. It supports no write method and does not replace the legacy
Shift Assignment endpoint.
