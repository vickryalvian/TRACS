# TRACS Permission And API Contract Checklist

Use this checklist with `docs/API_SECURITY_INVENTORY.md` and
`SECURITY_AUDIT_CHECKLIST.md`. This phase documents expected behavior; it does
not normalize legacy responses or permissions.

## Required Test Identities

- [ ] Super Admin
- [ ] Admin
- [ ] Supervisor with a known division
- [ ] Agent with owned and non-owned records
- [ ] Intern
- [ ] Viewer / Auditor
- [ ] Inactive or suspended user
- [ ] Pending-2FA user
- [ ] Unauthenticated client

## Baseline Contract For Every Endpoint

- [ ] Only documented HTTP methods are accepted; others return `405` and `Allow`.
- [ ] Unauthenticated and pending-2FA requests return `401`.
- [ ] Inactive/suspended accounts return `403`.
- [ ] Mutating methods require a valid authenticated session and CSRF token.
- [ ] Missing permissions return `403`, or `404` where intentional concealment applies.
- [ ] Object ownership and division scope are enforced after route permission.
- [ ] Cross-user IDs cannot bypass ownership, division, or hierarchy rules.
- [ ] Validation failures return a safe `4xx` response with useful field errors.
- [ ] Server failures return sanitized messages and log private detail server-side.
- [ ] JSON endpoints send `application/json` and valid JSON.
- [ ] Responses never expose SQL, stack traces, filesystem paths, secrets, or tokens.
- [ ] Successful writes create required activity/audit/ticker/notification effects.
- [ ] Repeated requests respect idempotency or deduplication where documented.

Target future envelope:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": [],
  "meta": {}
}
```

Record current legacy differences as characterization results. Do not change an
endpoint solely to make it match the target during baseline creation.

## P0 Endpoint Groups

### Calendar

- [ ] `GET /api/calendar/events.php`
- [ ] `GET /api/calendar/metadata.php`
- [ ] `POST /api/calendar/create.php`
- [ ] `POST /api/calendar/update.php`
- [ ] `POST /api/calendar/delete.php`
- [ ] Supervisor writes remain division-scoped.
- [ ] Source-owned reminder/checklist completion uses source-module permissions.

### Shift Assignment

- [ ] `GET|POST /api/shifting-assignment.php`
- [ ] `GET /api/v1/shift-assignment/context.php` requires `shifts.view`.
- [ ] V1 context returns `401` unauthenticated, `403` without permission, and
      `405` for non-GET methods.
- [ ] V1 context omits email, credentials, 2FA data, internal notes, SQL,
      paths, logs, and server details.
- [ ] Agent/Intern options remain self-scoped and Supervisor options remain
      division-scoped.
- [ ] Data, assignment, history, and monthly-template reads are scoped.
- [ ] Save, resize, status, confirm, replace, copy, warning, template, holiday,
      coverage, settings, apply, archive, and deactivate actions enforce their
      distinct management permissions.
- [ ] No assignment DELETE behavior is assumed; deactivate is limited to
      configuration records.
- [ ] Invalid overlap, duration, date/time, availability, and enum input returns
      safe validation errors without partial writes.

### Authentication And Administration

- [ ] `POST /auth/login.php` requires CSRF and applies lockout/CAPTCHA behavior.
- [ ] `POST /auth/logout.php` requires CSRF.
- [ ] Protected APIs reject pending 2FA.
- [ ] `GET /api/server-health.php` is exact-role `super_admin` only.
- [ ] `POST /api/user-avatar.php` enforces own-profile or managed-user permission.

## P1 Endpoint Groups

- [ ] Cases: create, get, update, status, resolve, delete, and attachment.
- [ ] Reminders: create, get, update, toggle, and delete.
- [ ] Checklist: task create, update, toggle, and delete.
- [ ] Shift Reports: create, list, history, update, resolve, delete, and attachment.
- [ ] MoM: current/legacy actions and protected screenshots.
- [ ] Notifications: list, mark-read, push-claim, and push-status.
- [ ] Ticker and ops-status actions.

For each group verify page permission, endpoint permission, object access,
cross-user denial, validation, audit effects, and protected file access.

## P2 Endpoint Groups

- [ ] Domain Transfer: create, update, and delete.
- [ ] Finance: balance-transfer and finance create/update/delete.
- [ ] Domain Price: matrix, workflow, task, recalculation, and export.
- [ ] Cancellation Feedback: list, create, update, and delete.
- [ ] Currency endpoints.
- [ ] Holiday and TV Mode summary role gates.

## CSV Export Contracts

- [ ] Activity requires `reports.export` and activity-view permission.
- [ ] Cases, Domains, Feedback, Finance, MoM, Shift Reports, and Domain Price
      exports require both export and module-view permissions.
- [ ] Unauthenticated, pending-2FA, and unauthorized exports are rejected.
- [ ] Export rows use the same ownership/division scope as the source page.
- [ ] Response content type and download filename are correct.
- [ ] Header order, escaping, UTF-8 content, commas, quotes, and line breaks are safe.
- [ ] Empty exports contain expected headers and no unauthorized records.

## Permission Matrix

- [ ] Super Admin receives full catalog access and exact-role protected tools.
- [ ] Admin cannot access exact Super Admin-only monitoring or recovery actions.
- [ ] Supervisor access remains limited by assigned permissions and division.
- [ ] Agent can perform normal owned operational work but cannot administer users.
- [ ] Intern remains limited to the approved intern scope.
- [ ] Viewer / Auditor remains read-oriented.
- [ ] Navigation visibility never substitutes for server-side authorization.
- [ ] React metadata and controls never grant access absent from PHP checks.
- [ ] Direct page URL, API URL, export URL, and attachment URL enforce equivalent access.

## Upload And Monitoring Contracts

- [ ] File extension, detected MIME, decoded content, size, and image dimensions
      are validated where implemented.
- [ ] Re-encoding and generated filenames prevent executable uploads.
- [ ] Case, Shift Report, and MoM evidence is served through protected endpoints.
- [ ] Avatars are the only intended directly public upload type.
- [ ] Server monitoring accepts no user-controlled path or command input.
- [ ] Log output is sanitized and bounded.
- [ ] Missing metrics return `Unavailable` instead of weakening permissions.
