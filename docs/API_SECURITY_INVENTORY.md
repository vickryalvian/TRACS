# TRACS API Security Inventory

Review date: 2026-06-08. All `public/api/` routes require a fully authenticated, active account through `_bootstrap.php` or `_export_helpers.php`. Pending-2FA sessions receive `401`. Mutating methods require CSRF. Internal helper files return `404` on direct execution and are denied by the web-server template.

| Endpoint | Purpose | Method | Required access | CSRF | Risk / action |
| --- | --- | --- | --- | --- | --- |
| `api/api_mom.php` | Current MoM actions | POST | `moms.manage` plus object ownership | Yes | High; authenticated/permission mapped |
| `api/mom-action.php` | Legacy MoM actions | POST | `moms.manage` plus controller ownership | Yes | High; retained legacy response, generic failures |
| `api/mom-screenshot.php` | Serve protected MoM image | GET | `moms.view` plus MoM object access | No | High; replaces direct upload URL |
| `api/bt-create.php` | Create balance transfer | POST | `finance.manage` | Yes | High; prepared statements |
| `api/bt-update.php` | Update balance transfer | POST | `finance.manage` plus object access | Yes | High; prepared statements |
| `api/bt-delete.php` | Delete balance transfer | POST | `finance.manage` plus object access | Yes | High; prepared statements |
| `api/finance-create.php` | Create finance entry | POST | `finance.manage` | Yes | High; owner-scoped |
| `api/finance-delete.php` | Delete finance entry | POST | `finance.manage` plus owner scope | Yes | High; owner-scoped |
| `api/case-create.php` | Create case and images | POST | `cases.manage` | Yes | High; validated/re-encoded images |
| `api/case-update.php` | Update case and images | POST | `cases.manage` plus object access | Yes | High; validated/re-encoded images |
| `api/case-delete.php` | Delete case | POST | Admin/Super Admin or `cases.delete`, plus object access | Yes | High; explicit delete gate |
| `api/case-resolve.php` | Resolve case | POST | `cases.manage` plus owner scope | Yes | High; prepared statement |
| `api/case-get.php` | Case detail | POST | `cases.view` plus object access | Yes | Medium; generic not-found |
| `api/case-attachment.php` | Serve protected case image | GET | `cases.view` plus owner scope | No | High; no direct folder access |
| `api/reminder-create.php` | Create reminder | POST | `reminders.manage` | Yes | High; owner-scoped |
| `api/reminder-update.php` | Update reminder | POST | `reminders.manage` plus owner scope | Yes | High; prepared statement |
| `api/reminder-delete.php` | Delete reminder | POST | `reminders.manage` plus owner scope | Yes | High; prepared statement |
| `api/reminder-toggle.php` | Toggle reminder | POST | `reminders.manage` plus owner scope | Yes | High; prepared statement |
| `api/reminder-get.php` | Reminder detail | POST | `reminders.view` plus owner scope | Yes | Medium; prepared statement |
| `api/task-create.php` | Create checklist item | POST | `checklist.manage` | Yes | High; owner-scoped |
| `api/task-update.php` | Update checklist item | POST | `checklist.manage` plus owner scope | Yes | High; owner-scoped |
| `api/task-delete.php` | Delete checklist item | POST | `checklist.manage` plus owner scope | Yes | High; owner-scoped |
| `api/task-toggle.php` | Toggle checklist item | POST | `checklist.manage` plus owner scope | Yes | High; owner-scoped |
| `api/shift-create.php` | Create shift report/images | POST | `reports.create` | Yes | High; validated image content |
| `api/shift-update.php` | Update shift report/images | POST | `reports.update` plus object access | Yes | High; validated image content |
| `api/shift-delete.php` | Delete shift report | POST | `reports.update` plus object access | Yes | High; object check |
| `api/shift-resolve.php` | Resolve shift report | POST | `reports.update` plus object access | Yes | High; object check |
| `api/shift-list.php` | Current shift data | GET | `reports.view` | No | Medium; method restricted |
| `api/shift-history.php` | Shift history | GET | `reports.view` | No | Medium; method restricted |
| `api/shift-attachment.php` | Serve protected shift image | GET | `reports.view` plus report access | No | High; no direct folder access |
| `api/domain-create.php` | Create domain record | POST | `domains.manage` | Yes | High; owner-scoped |
| `api/domain-update.php` | Update domain record | POST | `domains.manage` plus owner scope | Yes | High; prepared statement |
| `api/domain-delete.php` | Delete domain record | POST | `domains.manage` plus owner scope | Yes | High; prepared statement |
| `api/domain-price-matrix.php` | Save price matrix | POST | `domain_price.manage` | Yes | High; generic/sanitized exceptions |
| `api/domain-price-workflow.php` | Save price notes/status | POST | `domain_price.manage` | Yes | High; generic/sanitized exceptions |
| `api/domain-price-task.php` | Assign price-review task | POST | `domain_price.manage` or `domain_price.approve` | Yes | High; explicit permission |
| `api/domain-price-recalculate.php` | Recalculate price summary | POST | `domain_price.manage` or `domain_price.approve` | Yes | High; explicit permission |
| `api/feedback-create.php` | Create cancellation feedback | POST | `cancellation_feedback.manage` | Yes | High; controller validation |
| `api/feedback-update.php` | Update cancellation feedback | POST | `cancellation_feedback.manage` | Yes | High; controller validation |
| `api/feedback-delete.php` | Delete cancellation feedback | POST | `cancellation_feedback.manage` | Yes | High; controller validation |
| `api/feedback-list.php` | List cancellation feedback | GET | `cancellation_feedback.view` | No | Medium; method restricted |
| `api/currency.php` | Currency conversion | GET | `dashboard.view` | No | Low; fixed service behavior |
| `api/currency-converter.php` | Legacy currency conversion | GET | `dashboard.view` | No | Low; authenticated |
| `api/ticker-create.php` | Create ticker message | POST | `dashboard.view` | Yes | Medium; current product permission retained |
| `api/ticker-delete.php` | Delete own ticker message | POST | `dashboard.view` plus owner scope | Yes | Medium; owner-scoped |
| `api/ticker-list.php` | List ticker messages | GET | `dashboard.view` | No | Low; method restricted |
| `api/ops-status.php` | Manage operational status | POST | `settings.manage` or Super Admin/Admin/Supervisor | Yes | High; explicit role/permission gate |
| `api/notifications-list.php` | List own notifications | GET | `dashboard.view` or `profile.view_own` | No | Medium; user-scoped |
| `api/notification-mark-read.php` | Mark own notifications read | POST | `dashboard.view` or `profile.view_own` | Yes | Medium; user-scoped |
| `api/notification-push-claim.php` | Claim own push notification | POST | `dashboard.view` or `profile.view_own` | Yes | Medium; user-scoped |
| `api/notification-push-status.php` | Update own push state | POST | `dashboard.view` or `profile.view_own` | Yes | Medium; user-scoped |
| `api/holiday-indonesia.php` | Holiday feed/cache | GET | Super Admin/Admin/Supervisor TV access | No | Medium; role gated |
| `api/tv-mode-summary.php` | TV Mode summary | GET | Super Admin/Admin/Supervisor | No | Medium; role gated |
| `api/user-avatar.php` | Upload/remove avatar | POST | Own `profile.update_own` or managed-user `users.update` | Yes | High; MIME/content/size checks |
| `api/server-health.php` | Fixed server metrics and sanitized logs | GET | Exact `super_admin` role | No | Critical; no parameters, rate limited, fixed paths only |
| `api/export-activity.php` | Activity CSV | GET | `reports.export` and `users.view_activity` | No | High; authenticated export |
| `api/export-cases.php` | Cases CSV | GET | `reports.export` and `cases.view` | No | High; user-scoped export |
| `api/export-domain-price-crosscheck.php` | Domain pricing CSV | GET | `reports.export` and `domain_price.view` | No | High; permission checked |
| `api/export-domains.php` | Domains CSV | GET | `reports.export` and `domains.view` | No | High; permission checked |
| `api/export-feedback.php` | Feedback CSV | GET | `reports.export` and `cancellation_feedback.view` | No | High; permission checked |
| `api/export-finance.php` | Finance CSV | GET | `reports.export` and `finance.view` | No | High; permission checked |
| `api/export-moms.php` | MoM CSV | GET | `reports.export` and `moms.view` | No | High; permission checked |
| `api/export-shift-reports.php` | Shift report CSV | GET | `reports.export` and `reports.view` | No | High; permission checked |

## Non-API Actions

| Route | Exposure | Controls |
| --- | --- | --- |
| `auth/login.php` | Public POST by design | CSRF, generic failures, rate/lockout/CAPTCHA controls, pending 2FA only |
| `auth/logout.php` | Authenticated POST | CSRF and session destruction |
| `user-management.php` POST actions | Authenticated | CSRF plus controller permission/hierarchy/division checks; 2FA reset is exact Super Admin |
| `monitoring.php` POST actions | Authenticated | CSRF plus task permissions and assignment ownership |
| `profile.php` POST actions | Authenticated | CSRF plus own-profile permissions |
| `domain-price-crosscheck.php` POST actions | Authenticated | CSRF plus manage/approve and Super Admin-only delete checks |
| `domains.php` POST actions | Authenticated | CSRF plus domains permissions and prepared statements |

## Direct-Access Denials

`api/_bootstrap.php`, `api/_export_helpers.php`, `api/*-lib.php`, `includes/`, and `public/modules/` are not endpoints. PHP guards return `404`, and the Nginx/Apache rules deny them before PHP where possible.
