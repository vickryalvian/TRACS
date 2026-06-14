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
