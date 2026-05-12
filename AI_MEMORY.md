# TRACS — AI Memory & Project Rules

> **READ THIS FIRST.** This file preserves critical project intelligence for future AI sessions, developers, or handoffs. Never delete or ignore this file.

---

## Project Identity
- **Name:** TRACS Operational Dashboard
- **Type:** PHP operational control panel (no framework)
- **Database:** MySQL/MariaDB (database name: `vickryid_tracs_alpha`)
- **Document Root:** `/public/` subdirectory — NOT project root
- **PHP Minimum:** 8.0+ (uses arrow functions, named args, match expressions)
- **Target User:** Legal/operational teams in Indonesia (text may be Bahasa Indonesia)
- **Timezone:** Asia/Jakarta (WIB, UTC+7)

---

## NEVER BREAK THESE

1. **Auth flow** — `auth_check.php` guards all pages. It redirects to `login.php` (relative, same directory). Never change redirect to absolute path without updating all pages.

2. **Session start** — every protected page does `if(session_status()===PHP_SESSION_NONE)session_start()` BEFORE including `auth_check.php`. Never remove this.

3. **API bootstrap** — all api/*.php files start with `require '_bootstrap.php'`. Never bypass this. It handles auth guard, JSON parsing, and `ok()`/`fail()` helpers.

4. **__DIR__ paths** — all require/include paths use `__DIR__.'/../...'`. Never use relative paths like `'../config/database.php'` without `__DIR__`.

5. **data-* attributes on rows** — every list row stores edit data in `data-cid`, `data-title`, `data-status` etc. JS reads these for instant edit modal population. Do not remove them.

6. **Ticker doubled HTML** — `formatAlertsForTicker()` returns items, then header.php duplicates them (`$_th .= $_th`) for seamless CSS animation loop. Never break this doubling.

7. **CSS custom properties** — all colors use `--red`, `--blue`, `--green` etc. Never hardcode hex colors in components. Always use CSS vars.

8. **New tables auto-create** — `tracs_ticker_messages`, `tracs_finance_transfers`, `tracs_domains` tables are created by their respective API endpoints if they don't exist. Do NOT move this logic to install.sql only.

---

## Coding Conventions

### PHP
- Use `esc()` helper from `page_helpers.php` for all HTML output: `esc($var)`
- Use `safe_dt()` for date formatting, `safe_dt_local()` for datetime-local input values
- Use `prio_badge()`, `status_badge()`, `prio_bar()`, `rem_status_class()` for consistent badge classes
- All DB queries use prepared statements via `$conn->prepare()`
- All API endpoints return `ok($data, $msg)` or `fail($msg, $code)`

### CSS
- Component classes: `.panel`, `.case-row`, `.rem-row`, `.task-row`, `.badge`, `.btn`
- Status badge classes: `.b-active`, `.b-pending`, `.b-stuck`, `.b-done`
- Priority badge classes: `.b-critical`, `.b-high`, `.b-medium`, `.b-low`
- Color cards: `.stat-card.red/amber/purple/blue/green/cyan` + `.stat-glow`
- Never add inline styles for colors — use CSS vars and classes

### JS
- All CRUD goes through `api(url, data)` helper — never use raw fetch
- `toast(msg, type)` for all user feedback (success/error/info)
- `confirm(msg, cb)` for all destructive actions
- `removeRow(selector)` for optimistic DOM removal
- `_reload()` after successful create/update operations

---

## Critical Implementation Decisions

1. **No framework** — deliberate choice. Keep it vanilla PHP + vanilla JS. Do not introduce Composer, npm, or any build tool.

2. **Single CSS file** — `tracs.css` contains the ENTIRE design system. Do not split it.

3. **Single JS file** — `tracs.js` contains ALL JavaScript. Do not split it.

4. **Modals in footer.php** — all shared modals (case, reminder, task, ticker, confirm) are in `footer.php` and reused across all pages. Page-specific modals (finance, domains) are inline in their respective pages.

5. **No client-side routing** — each page is a full PHP page. No SPA. Keep it this way.

6. **Activity logging is non-fatal** — wrapped in try/catch. A logging failure must never crash the main operation.

7. **Auth_check redirect is relative** — must stay as `login.php` (not `/public/login.php`) because the file is included FROM within `/public/`.

---

## Design System Rules

- **Dark theme:** `--bg: #07080a` (near-black, NOT pure black)
- **Surface layers:** `--s1` (panels) → `--s2` (panel headers) → `--s3` (hover) → `--s4` (deep)
- **Border hierarchy:** `--bd1` (subtle) → `--bd2` (normal) → `--bd3` (emphasis) → `--bd4` (strong)
- **Typography:** Inter for UI, JetBrains Mono for all data/numbers/timestamps
- **Priority visual:** Left-side colored bar on case rows (3px wide): red=critical, amber=high, #6ba3fa=medium, bd3=low
- **Stat cards:** Top gradient strip (`stat-glow`) + mono number + label. Colors must match: red/purple/amber/cyan/blue/green
- **Ticker bar:** 30px height, red "LIVE" badge with clip-path arrow, CSS animation 55s, pauses on hover
- **Sidebar:** 52px wide, icon-only, tooltips appear on hover
- **Modals:** Animated scale-in, backdrop blur, border-radius 16px, sticky header

---

## Future Development Guidance

### Adding a New Module
1. Create `modules/newmodule/model.php` + `controller.php`
2. Create `api/newmodule-create.php`, `update.php`, `delete.php` (require `_bootstrap.php`)
3. Create `public/newmodule.php` (use header/footer includes, follow same pattern as cases.php)
4. Add nav item to `public/includes/header.php`
5. Add modal to `public/includes/footer.php` OR inline in page
6. Add JS functions to `public/assets/tracs.js`
7. Add CSS classes to `public/assets/tracs.css`
8. Add table to `config/install.sql`

### Adding a New Field to Cases
1. `ALTER TABLE tracs_cases ADD COLUMN ...`
2. Update `api/case-create.php` and `api/case-update.php` SQL
3. Update modal in `footer.php` (add form field)
4. Update `openEditCase()` in tracs.js (read from data-* or API)
5. Update case-row HTML in index.php and cases.php (add data-* attribute)
6. Update `CaseController::formatCase()` if display formatting needed

---

## Known Limitations

1. **No real-time updates** — page must be refreshed to see other users' changes
2. **Single-user focused** — activity log shows all users' activity but no per-user filtering UI
3. **No file attachments** — cases/reminders have text-only notes
4. **No email notifications** — reminders are UI-only, no email alerts
5. **No pagination on cases page** — shows all cases (consider adding if >100 cases)
6. **Finance transfers are immutable** — no edit endpoint (by design, audit trail)

---

## Warnings for Future AI

- **Do NOT add `session_start()` at top of auth_check.php without the `session_status()` check** — will cause "headers already sent" errors
- **Do NOT change the `../api/` path in JS** — correct relative path from `/public/` to `/api/`
- **Do NOT remove `__DIR__` from require paths** — breaks when PHP is called from different working directories
- **Do NOT use `die()` for JSON APIs** — use `fail()` helper which sets proper HTTP status code
- **Do NOT put session_start() in multiple files** — use the `if(session_status()===PHP_SESSION_NONE)` guard
- **Do NOT move any files from `/public/` to root** — breaks `.htaccess` security model
