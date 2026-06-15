# Shift Assignment Role Test Matrix

## Authority And Test Rule

Both `public/shifting-assignment.php` and
`public/shift-assignment-react-preview.php` require a fully authenticated,
active session and `shifts.view`.

Role names describe the default migration grants, but the effective permission
rows in the test environment are authoritative. A role fixture must therefore
record its actual Shift Assignment permissions before parity testing. Removing
`shifts.view` must deny both pages and both v1 read APIs regardless of role.

The React preview is intentionally read-only. Legacy actions shown below are
parity context, not features expected in the preview.

## Default Role Matrix

| Role | Legacy page | React preview | Expected visible filters and scope | Legacy allowed actions | React allowed actions | API expectation | Notes and risks |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Super Admin | Allowed with `shifts.view` | Allowed with `shifts.view` | All active non-viewer agents and active divisions | View, manage, settings, monthly templates, export by default | Read-only GET filters and view controls | `200`; global service scope | Confirm sensitive fields remain filtered despite global visibility. |
| Admin | Allowed with `shifts.view` | Allowed with `shifts.view` | All active non-viewer agents and active divisions | View, manage, settings, monthly templates, export by default | Read-only GET filters and view controls | `200`; global service scope | Same read scope as Super Admin, but role remains distinct. |
| Supervisor | Allowed with `shifts.view` | Allowed with `shifts.view` | Assigned division only; own user only when no division is assigned | View, manage, monthly templates, export by default; no settings default | Read-only GET filters and view controls | `200`; division scope or self fallback | Test both a division fixture and a no-division fixture. |
| Agent | Allowed with `shifts.view` | Allowed with `shifts.view` | Current user and current division only | View only by default | Read-only GET filters and view controls | `200`; self assignment scope | A division option must not expand assignment visibility beyond self. |
| Intern | Allowed with `shifts.view` | Allowed with `shifts.view` | Current user and current division only | View only by default | Read-only GET filters and view controls | `200`; self assignment scope | Confirm internship metadata is never returned by Shift Assignment APIs. |

Viewer is supported by the service and migration but is outside the required
Phase 10 role set. If tested, it is view-only by default and is not included in
the agent filter because `getAgents()` excludes viewer accounts.

The preview currently shows an API-supported role filter while the context
contract reports `role_filter_supported: false`; record this discrepancy for
every role and confirm it never expands the service-enforced scope.

## Permission Variants

| Fixture state | Legacy page | React preview | Context API | Assignments API |
| --- | --- | --- | --- | --- |
| Fully authenticated, active, `shifts.view` | Render | Render | `200` | `200` for valid query |
| Fully authenticated, active, no `shifts.view` | Generic page denial (`404`) | Generic page denial (`404`) | `403` | `403` |
| Unauthenticated | Redirect to `/login.php` | Redirect to `/login.php` | `401` JSON | `401` JSON |
| Expired idle session | Redirect through normal auth handling | Redirect through normal auth handling | `401` JSON | `401` JSON |
| Pending or incomplete 2FA | Do not render | Do not render | `401` JSON | `401` JSON |
| Inactive or suspended account | Do not render | Do not render | `403` JSON after session validation | `403` JSON after session validation |
| Authenticated invalid query | Legacy behavior remains unchanged | Page remains mounted | Context remains `200` | `422` five-key JSON |
| Authenticated invalid method | Page route is not an API mutation surface | Page route is not an API mutation surface | `405` | `405` |

Page permission denial intentionally uses a generic `404`; API permission
denial uses a structured `403`. This difference is expected and must not be
"normalized" in the frontend.

## Fixture Record

Complete this table for each disposable test account. Do not record passwords,
2FA secrets, session IDs, or production user data.

| Fixture label | Role | Division | `view` | `manage` | `settings` | `monthly_templates` | `export` | Expected scope |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `phase10-super-admin` | Super Admin | Test division or global |  |  |  |  |  | Global |
| `phase10-admin` | Admin | Test division or global |  |  |  |  |  | Global |
| `phase10-supervisor` | Supervisor | Test Division A |  |  |  |  |  | Division A |
| `phase10-supervisor-no-division` | Supervisor | None |  |  |  |  |  | Self |
| `phase10-agent` | Agent | Test Division A |  |  |  |  |  | Self |
| `phase10-intern` | Intern | Test Division A |  |  |  |  |  | Self |
| `phase10-denied` | Any | Any | No | N/A | N/A | N/A | N/A | Denied |

## Evidence Record

For each fixture, record:

- test date, environment, commit SHA, and tester;
- permission snapshot without credentials;
- selected ISO range and displayed `dd-mm-yyyy` range;
- legacy assignment IDs/count and preview assignment IDs/count;
- legacy and preview agent/division filter IDs;
- warning types/counts and workload totals;
- HTTP status for context and assignments APIs;
- screenshots at desktop, tablet, and mobile widths;
- browser console result;
- pass, fail, or blocked with a defect reference.

Production records must not be changed to manufacture test cases. Use existing
read-only data or an approved disposable database fixture.
