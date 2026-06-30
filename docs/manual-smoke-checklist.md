# TRACS Manual Smoke Checklist

## Test Record

- Revision/commit:
- Environment:
- Database snapshot:
- Tester:
- Date and time:
- Browser and viewport:
- Role:
- Result: Pass / Fail / Blocked

Record screenshots, sanitized errors, failed request IDs, and affected records.
Do not include passwords, CSRF tokens, session IDs, TOTP secrets, or customer
data in test evidence.

## P0 Authentication And Security

- [ ] Login page loads over the expected route without PHP or JavaScript errors.
- [ ] Valid credentials enter pending 2FA rather than a full session.
- [ ] Required 2FA setup and normal 2FA verification complete successfully.
- [ ] Invalid credentials and invalid TOTP produce generic safe errors.
- [ ] Logout uses POST plus CSRF and destroys the authenticated session.
- [ ] Pending-2FA and expired sessions cannot open protected pages or APIs.
- [ ] Mutating requests without a valid CSRF token are rejected.
- [ ] Inactive or suspended users cannot continue using an old session.
- [ ] Navigation visibility agrees with direct page and API authorization.
- [ ] Server Health & Logs is available only to the exact `super_admin` role.
- [ ] Error logs are sanitized and unavailable paths remain unavailable.
- [ ] Server CPU, RAM, disk, storage, database, and deployment metrics expose
      only the fixed allowed values.

## P0 Calendar Reference

- [ ] `calendar.php` loads the PHP shell and React bundle successfully.
- [ ] Calendar metadata and events load without console or request errors.
- [ ] Year, Month, Week, Day, and Agenda views render and navigate correctly.
- [ ] Search and all supported filters update visible results correctly.
- [ ] Dates display as `dd-mm-yyyy`; API/state dates remain ISO.
- [ ] Event detail, manual schedule create/edit/delete, and source links work
      according to the current user's permissions.
- [ ] Loading, empty, error, retry, and success states remain usable.
- [ ] Calendar-specific regression checks in
      `docs/calendar-reference-regression-checklist.md` pass.

## P0 Shift Assignment

- [ ] Page loads for authorized roles and is denied to unauthorized roles.
- [ ] Daily view shows assignments for the selected date.
- [ ] Weekly view shows Monday through Sunday assignments.
- [ ] Monthly view shows the complete calendar month.
- [ ] Seeded schedules appear in Daily and Weekly views.
- [ ] Shift 1 displays `00:00-08:00`.
- [ ] Shift 2 displays `08:00-16:00`.
- [ ] Shift 3 displays `16:00-24:00` while preserving cross-day storage.
- [ ] Filters, search, risk filters, timeline, recap, warnings, and audit use
      the same filtered assignment set.
- [ ] Overlap, jumpshift/rest-under-eight-hours, overtime, weekly totals,
      holiday coverage, and coverage-gap warnings remain correct.
- [ ] Copy Last Week, replacement, drag/resize, confirmation, and warning
      dismissal work only for authorized roles.
- [ ] Monthly template Draft, Previewed, Applied, and Archived behavior remains
      intact and protected assignments are not overwritten.

## P1 Operational Pages

- [ ] Dashboard loads authorized statistics and widgets without broken sections.
- [ ] Cases list/search/filter opens the shared ticket modal and supports
      authorized create, update, status, resolve, delete, and attachment flows.
- [ ] Checklist create/update/toggle/delete and completed-item visibility work.
- [ ] Reminder create/update/toggle/delete, due state, and dashboard visibility work.
- [ ] Task Assignment/Monitoring preserves assignment, review, checklist,
      reminder, notification, and audit synchronization.
- [ ] Shift Reports preserves Active, On Hold, Resolved, handover, history,
      export, and attachment behavior.
- [ ] MoM preserves schedule, lifecycle, agenda, notes, decisions, actions,
      reminders, case links, screenshots, history, ticker, and export behavior.
- [ ] Notification center lists only the current user's notifications, marks
      records read, and preserves deduplication and click-through behavior.
- [ ] The notification worker can run once without duplicate due notifications.

## P2 Administrative And Supporting Pages

- [ ] Domain Price Crosscheck preserves month selection, matrices, calculations,
      notes, workflow, approval lock, tasks, audit, sources, extensions, and export.
- [ ] Domain Transfer Log preserves create/update/delete, filtering, and export.
- [ ] Finance preserves authorized transfer logging, filters, conversion, and export.
- [ ] Cancellation Feedback preserves multi-value fields, CRUD, filters, and export.
- [ ] User Management preserves user, role, permission, division, intern, avatar,
      password reset, suspension, activation, and exact Super Admin 2FA reset rules.
- [ ] Activity Log preserves filters, user scope, details, and export.
- [ ] Profile preserves account, preferences, security, avatar, and password behavior.

## P3 Monitoring And Display

- [ ] Infrastructure Pulse remains clearly identified as partial/mock telemetry
      where no real backend exists.
- [ ] OpsTrack signals render only from currently implemented data.
- [ ] Network/TV Mode loads for Super Admin, Admin, and Supervisor only.
- [ ] TV Mode works at normal, fullscreen, narrow, compact, and large-display sizes.
- [ ] Monitoring routes do not expose arbitrary paths, commands, logs, or secrets.

## Cross-Cutting UI And Data

- [ ] Toast severity, placement, stacking, persistence, and modal context work.
- [ ] Modals trap/restore focus, close correctly, and protect unsaved changes.
- [ ] Filters, sorting, pagination, sticky headers, and table scrolling work.
- [ ] CSV files use correct access scope, content type, headers, escaping, and rows.
- [ ] JPG, JPEG, PNG, and WebP upload validation rejects invalid type/content/size.
- [ ] Protected images load through permission-checked endpoints; direct protected
      upload paths return `403` or `404`.
- [ ] Create/update/delete actions persist correctly after page reload.
- [ ] Activity and audit records identify the correct actor and object.
- [ ] No page shows raw SQL, stack traces, filesystem paths, credentials, or tokens.
