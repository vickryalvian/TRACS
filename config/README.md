# TRACS Database Configuration

This directory contains the database installer, reusable schema modules, incremental migrations, and archived legacy SQL for TRACS.

## Overview

`install.sql` is the main fresh-install entry point. It creates the active TRACS schema with `utf8mb4`, InnoDB tables, core indexes, seed admin data, and all currently used module tables.

The `schema/` directory mirrors the active schema by module so future developers can inspect or reuse focused table definitions without reading the full installer.

The `migrations/` directory contains incremental, re-runnable SQL changes for existing installations. Deprecated or superseded SQL files are preserved under `archive/` after backup.

## Installation Flow

1. Back up the target database if it contains data.
2. Review the database name in `install.sql`.
3. Run `install.sql` against a clean MySQL/MariaDB database.
4. Log in with the seeded admin account and immediately change the password.

`install.sql` should remain complete enough for a fresh TRACS install. Do not require PHP runtime table creation for core modules.

## Migration Flow

For existing installations:

1. Create a database backup.
2. Run migration files in chronological filename order.
3. Confirm application modules still load.
4. Keep destructive changes in separate migrations with explicit rollback notes.

Current migrations:

- `2026_05_16_add_creator_tracking.sql`
- `2026_05_16_add_operational_metadata.sql`
- `2026_05_16_add_theme_preferences.sql`
- `2026_05_16_cleanup_unused_tables.sql`
- `2026_05_17_cancellation_feedback_multiselect.sql`
- `2026_05_17_intern_user_management.sql`
- `2026_05_17_user_management.sql`
- `2026_05_18_task_management.sql`
- `2026_05_18_task_metrics.sql`
- `2026_05_18_user_avatars.sql`
- `2026_05_20_domain_price_crosscheck.sql`
- `2026_05_21_domain_price_crosscheck_audit.sql`
- `2026_05_21_login_hardening.sql`
- `2026_05_21_mandatory_2fa.sql`
- `2026_05_22_domain_price_crosscheck_sources.sql`
- `2026_05_23_domain_price_task_links.sql`
- `2026_05_23_domain_price_workflow.sql`
- `2026_05_24_case_attachments.sql`
- `2026_05_24_shift_report_attachments.sql`
- `2026_05_24_tracs_ui_theme_cleanup.sql`
- `2026_05_25_domain_price_matrix_registrar_defaults.sql`
- `2026_05_26_domain_price_computed_summaries.sql`
- `2026_05_26_notifications.sql`
- `2026_05_26_shift_report_resolved_status.sql`
- `2026_05_27_case_in_progress_status.sql`
- `2026_05_27_domain_price_cctld_pricing.sql`
- `2026_06_08_shifting_assignment.sql`
- `2026_06_13_main_shift_hours.sql`
- `2026_06_13_shifting_assignment_audit_fixes.sql`
- `2026_06_30_user_removed_status.sql` — adds the `removed` user status value.
- `2026_06_30_user_removal_release.sql` — safe user removal (archive + identity
  release) plus `dashboard.view` repair for operational roles. Idempotent;
  supersedes the status-only migration above by also ensuring the enum.

## File Structure

```text
config/
  install.sql
  README.md
  schema/
    auth.sql
    users.sql
    cases.sql
    reminders.sql
    checklist.sql
    finance.sql
    domains.sql
    moms.sql
    shift_reports.sql
    activity_logs.sql
    notifications.sql
    preferences.sql
    shared.sql
  migrations/
    YYYY_MM_DD_description.sql
  archive/
    backups/
    deprecated/
```

## Table and Module Mapping

| Module | Tables |
| --- | --- |
| Users/Auth | `tracs_users`, `tracs_login_attempts`, `tracs_auth_events` |
| Cases | `tracs_cases`, `case_attachments` |
| Reminders | `tracs_reminders` |
| Checklist | `tracs_side_tasks`, `tracs_side_task_logs` |
| Finance | `balance_transfers`, `tracs_finance_transfers` |
| Domain Transfers | `domain_transfers`, `activity_feed`, `tracs_domains` |
| MoM | `tracs_moms`, `tracs_mom_agenda`, `tracs_mom_notes`, `tracs_mom_decisions`, `tracs_mom_actions`, `tracs_mom_case_links`, `tracs_mom_screenshots`, `tracs_mom_audit_log` |
| Task Management | `tracs_tasks`, `tracs_task_assignments`, `tracs_task_logs`, `tracs_task_reviews`, `tracs_task_reminders` |
| Shift Reports | `tracs_shift_reports`, `tracs_shift_activities`, `shift_report_attachments` |
| Shift Assignment | `shift_assignment_types`, `shift_templates`, `shift_assignments`, `shift_monthly_templates`, `shift_monthly_template_items`, `shift_workload_settings`, `shift_coverage_rules`, `shift_warnings`, `assignment_audit_logs` |
| Activity Logs | `tracs_activity_logs` |
| Ticker/Notifications | `tracs_ticker_messages`, `tracs_ticker_events`, `tracs_notifications`, `tracs_notification_triggers`, `tracs_notification_logs` |
| Theme/User Preferences | `tracs_user_preferences` |
| Shared/Ops | `ops_status`, `tracs_currency_history` |
| Domain Price Crosscheck | `domain_price_months`, `domain_price_tlds`, `domain_price_sources`, `domain_price_entries`, `domain_price_summaries`, `domain_price_audit_logs`, `domain_price_tld_notes`, `domain_price_task_links` |

## Naming Conventions

- Use `id` as the primary key.
- Use `created_at` and `updated_at` for mutable records.
- Use `created_by` and `created_by_name` when user attribution must survive later profile edits.
- Use `status` for lifecycle state.
- Use `deleted_at` only when soft delete is required by the product flow.
- Prefix TRACS-owned tables with `tracs_` unless legacy PHP already queries an unprefixed table.
- Keep legacy unprefixed names such as `balance_transfers`, `domain_transfers`, `activity_feed`, and `ops_status` until PHP references are migrated safely.

## Database Standards

- Engine: InnoDB
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Prefer additive migrations.
- Use `CREATE TABLE IF NOT EXISTS`.
- Add indexes for user-scoped filters, status filters, date ranges, and dashboard queries.
- Avoid dropping tables or columns in the same migration that introduces replacements.

## Adding New Modules

1. Create `schema/<module>.sql` with the new module tables.
2. Add the same definitions to `install.sql` so fresh installs are complete.
3. Create a dated migration under `migrations/`.
4. Document the module in this README.
5. Keep cross-module links explicit and indexed.

## Creating New Migrations

Use this naming pattern:

```text
YYYY_MM_DD_short_description/
  up.sql
  down.sql
```

Existing single-file migrations remain part of the current deployment history.
New refactor-phase migrations must use paired `up.sql` and `down.sql` files.
They should be safe to re-run when practical. For column/index additions, use
`information_schema` checks or helper procedures. For destructive changes,
include backup instructions, rollback notes, verification queries, data-loss
warnings, and a clear reason. A database backup remains mandatory when a down
migration cannot recreate removed or transformed data.

## Backup Recommendation

Before changing schema or moving SQL files, create a backup under:

```text
config/archive/backups/YYYY_MM_DD_description/
```

For production databases, also export the live database before running migrations.

## Deprecated SQL Notes

Deprecated scripts are archived instead of deleted. They may contain old sample data, duplicate table definitions, or module-specific migrations that have been consolidated into current installer/migration files.

Do not run archived SQL against production unless you have reviewed it against the current schema.

## Future Development Guidelines

TRACS should remain modular. Future analytics, AI summaries, SLA monitoring, escalation rules, automation, notifications, audit logs, attachments, role permissions, APIs, portals, and reporting should be added as separate schema modules plus dated migrations.

Prefer stable extension tables and clear foreign keys over rewriting existing operational tables. When in doubt, add documentation first and defer deletion until usage is proven obsolete.

## Default CS Shift Seed

After the Shift Assignment migrations, preview and apply the default agent/monthly schedule seed:

```bash
php bin/seed-default-shift-schedule.php
php bin/seed-default-shift-schedule.php --apply
php bin/seed-default-shift-schedule.php --start=2026-06 --end=2026-12 --apply
```

Without range options, the command targets the current month through December of the current year. `--start=YYYY-MM --end=YYYY-MM` selects an explicit inclusive month range, while `--month=YYYY-MM` reconciles one month. It maps Vickry to the canonical `Super Admin` role and Aria, Lala, Annisa, Gagas, Nurlina, and M. Idris to the canonical Customer Support-equivalent `Agent` role. All seven users are active members of the `Customer Support` / `CS` division.

The seed stores English `Week 1`-`Week 5`, numeric day indexes, and `Monday`-`Sunday` values in `settings_json`, then creates actual assignments for every date. Week 5 repeats the Week 4 pattern. Shift 1 is `00:00-08:00`, Shift 2 is `08:00-16:00`, and Shift 3 is stored as `16:00` through next-day `00:00` while the UI displays `16:00-24:00`.

Reruns match users by normalized name, username, or seed email. Existing passwords, email addresses, avatars, 2FA state, and personal settings are preserved. New users receive random temporary passwords printed once by the CLI and remain subject to TRACS mandatory 2FA setup. TRACS does not currently enforce a first-login password change, so an administrator must rotate those temporary passwords before normal use. The command replaces only assignments linked to its `default_cs_monthly_shift_v1` templates and refuses to overwrite conflicting manual schedules. Its cleanup removes only the three known June 2026 dummy slots; other manual, leave, off-day, and special rows are preserved.

## Login Hardening Migration

Run `migrations/2026_05_21_login_hardening.sql` on existing databases. It adds:

- `tracs_login_attempts`: failed-attempt counters, temporary locks, and CAPTCHA-required state by normalized identifier hash and IP address.
- `tracs_auth_events`: authentication audit events for login success/failure, lockouts, CAPTCHA challenges, logout, and idle timeout.

No passwords, session IDs, CSRF tokens, or CAPTCHA secrets are stored in these tables. Rollback is to restore the PHP/CSS files from backup and drop the two tables after exporting audit data if needed.

## Mandatory 2FA Migration

Run `migrations/2026_05_21_mandatory_2fa.sql` after the login-hardening and user-management migrations. It adds encrypted TOTP state to `tracs_users`:

- `two_factor_enabled`
- `two_factor_secret`
- `two_factor_confirmed_at`
- `two_factor_reset_required`
- `two_factor_failed_attempts`
- `two_factor_locked_until`
- `two_factor_last_verified_at`

Existing users are marked as requiring 2FA setup on their next successful password login. Back up the database before applying this migration. Rollback should restore the pre-migration database backup; if that is not possible, remove or ignore the `two_factor_*` columns only after confirming no encrypted 2FA secrets are still needed.
