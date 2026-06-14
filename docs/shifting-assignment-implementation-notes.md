# Shifting Assignment Implementation Notes

## What changed

- Kept Add Assignment and Monthly Template forms canonical: each modal renders once in PHP and JavaScript only resets or populates that form in place.
- Added inline assignment validation, division/template autofill, live net-duration calculation, cross-day messaging, and toast-before-close save behavior.
- Added server validation for active agents/templates, valid dates and times, zero duration, breaks, overlap, availability, enums, and protected creation statuses.
- Reworked monthly templates around `Draft -> Previewed -> Applied -> Archived`; Preview persists the draft before generating the preview, and Apply is separately confirmed.
- Flattened the desktop global toolbar, removed duplicate Assignment Audit filters, and made Risk Filters update timeline, recap, warnings, audit, and insights.
- Split Configuration into Shift Patterns, Monthly Templates, Holidays, Coverage Rules, and Workload Settings.
- Clarified Assignment Audit actions and added Source and Last Modified display.
- Replaced generic rest status with the calculated minimum rest gap and kept standby/cancelled handling aligned with workload settings.
- Added actionable warning links, persistent dismissal support, compact timeline empty states, cross-day continuation markers, and persistent Schedule Insights collapse state.
- Removed the toast and warning-card accent strip and retained TRACS spacing, colors, helpers, CSRF, sessions, and permission checks.
- Aligned the main CS templates to Shift 1 `00:00-08:00`, Shift 2 `08:00-16:00`, and Shift 3 `16:00-24:00`; Shift 3 stores `00:00` on the next day and uses `24:00` only as a display label.
- Added `bin/seed-default-shift-schedule.php` for idempotent default-agent and real dated schedule seeding from the current month through year-end.
- Normalized Timeline ranges per mode and grouped Monthly cells by shift so Daily, Weekly, and Monthly all render the same live assignment source.
- Corrected midnight overlap handling so Shift 3 does not leak into the next day's range.

## Global filters

Date, agent, division, assignment type, status, holiday-only, and search are sent to the shared data endpoint. The returned filtered dataset drives timeline, workload recap, warnings, Assignment Audit, and Schedule Insights. Search is debounced; Apply handles the remaining scope controls. Risk Filters are a client-side refinement across the same five views.

## Modal rendering rule

`#shiftAssignmentForm` and `#shiftMonthlyTemplateForm` are rendered once by PHP. On open, JavaScript removes stray duplicate form nodes and restores a cloned canonical modal body only if the expected field counts are invalid. It never appends another form body during normal open/populate behavior.

## Monthly template lifecycle

- **Draft:** editable definition; saving does not create live assignments.
- **Previewed:** Preview saves the current draft and records that a generated preview was reviewed.
- **Applied:** created only through the confirmed Apply action; conflicts are reported and protected assignments are skipped.
- **Archived:** manual action from the Monthly Templates list; generated assignments remain unchanged.

Applied and archived templates must be duplicated before editing or applying again.

Exact seeded templates use `settings.schedule_mode = weekly_matrix`. Their matrix includes each real date in the month, supports a fifth weekday occurrence, and records Week 5 as a repeat of the Week 4 pattern. The generic one-shift monthly generator remains unchanged.

## Default CS seed

Preview first, then apply:

```bash
php bin/seed-default-shift-schedule.php
php bin/seed-default-shift-schedule.php --apply
php bin/seed-default-shift-schedule.php --start=2026-06 --end=2026-12 --apply
```

The seed uses the existing `Super Admin` role for Vickry, the existing `Agent` role as the canonical Customer Support role for the other six agents, and the `Customer Support` / `CS` division for all seven. Existing matched accounts retain passwords, emails, avatars, 2FA state, and personal settings. New accounts receive random temporary CLI credentials and require normal TRACS 2FA setup; because first-login password rotation is not enforced, an administrator must rotate those credentials before normal use.

The database source of truth is English/numeric: `Week 1`-`Week 5`, Monday `1` through Sunday `7`, and `Monday`-`Sunday`. Actual assignments are generated for every date; Week 5 uses the same approved pattern as Week 4. A rerun deletes and recreates only items and live assignments owned by `default_cs_monthly_shift_v1`, removes only the three explicitly identified June 2026 dummy test rows, preserves other manual/special rows, and aborts on a protected overlap.

## Timeline ranges

- **Daily:** fetches exactly the selected date.
- **Weekly:** fetches Monday through Sunday for the selected week.
- **Monthly:** fetches the first through last calendar day of the selected month.

All modes use the same `shift_assignments` query and include `source = monthly_template`. Monthly rendering groups rows by date and shift, then aggregates all assigned agents. Database range overlap is half-open, so an assignment ending exactly at range start is excluded.

## Smart warning actions

Warning actions switch to Timeline, Workload Recap, or Assignment Audit, prefill the related agent/date when available, refresh the shared dataset, then scroll to and briefly highlight the related row or block. Dismiss writes to `shift_warnings` and `assignment_audit_logs` when the audit migration is installed.

## Known limitations

- The guarded migration is in `config/migrations/2026_06_13_shifting_assignment_audit_fixes.sql`. It was applied and rerun successfully against the local TRACS database; each deployment environment still needs the same migration before release.
- Warning categories that require external operational events, such as last-minute change metadata or public-holiday feeds beyond current TRACS data, are shown only when the underlying data exists.
- Final permission-specific testing should use authenticated admin, supervisor, and agent accounts in the deployment environment.
