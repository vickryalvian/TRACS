# TRACS Toast & Notification Audit

**Date:** 2026-06-30
**Scope:** Every toast/notification implementation across the codebase — frontend JS, backend-triggered flash flows, utilities, wrappers, CSS, and accessibility.
**Status:** Audit complete. Remediation = phased; only safe, in-scope items implemented now (see §D/§E).

---

## A. Audit Report

### A.1 Summary

TRACS has a **single, centralized, already-sophisticated** toast system defined in `public/assets/tracs.js`:

- One core API: `showToast(...)` with a `toast(msg, type, ms)` convenience wrapper. Both exposed on `window`.
- One icon map (`toastIconFor`), one duration map (`tracsToastDefaults`), one type normalizer (`tracsNoticeType`).
- Context-aware docks: page / modal / inline / login, with a 3-toast stacking cap and duplicate suppression.
- Server→client feedback uses PHP **flash** (`tracs_flash`, `profile_flash`) re-emitted as a toast after redirect, plus a cross-reload **toast-persist** mechanism (`queueToastAfterReload` → `showQueuedToast`).
- Module files (`mom-functions.js`, `domain-price-crosscheck.js`, `shifting-assignment.js`, `infrastructure-pulse.js`) **do not ship their own toast library** — they call the global API directly or via thin adapters (`dpcToast`, `dpcNotify`, `notify`).

**Conclusion:** This is NOT a "multiple competing systems" problem. The architecture is sound and largely enterprise-grade. The real issues are **message quality** (vague/generic strings, raw `'Error: ' + e.message`) and **a few accessibility polish items**. There is **no toast library duplication, no duplicate provider, no memory leak, and no loading-toast misuse**.

### A.2 Inventory totals

| Source | Call sites | Notes |
|---|---|---|
| `public/assets/tracs.js` | ~58 | Core infra + case/task/reminder/shift/finance/domain/avatar/feedback/notification call sites |
| `public/assets/mom-functions.js` | 65 | MoM module; all via global `toast()` |
| `public/assets/shifting-assignment.js` | ~21 | via `notify()` adapter → `window.showToast` |
| `public/assets/domain-price-crosscheck.js` | ~11 | via `dpcToast()`/`dpcNotify()` adapters |
| `public/assets/infrastructure-pulse.js` | 1 | modal success |
| `public/assets/unsaved-changes-guard.js` | 1 | save error |
| PHP flash → toast (`profile.php`, `user-management.php`, `monitoring.php`, `two-factor-setup.php`) | ~12 | reload/redirect-driven |

Full per-line inventory tables are retained in the audit working notes (3 sub-audits). Representative rows are in §B.

### A.3 Risk level

**Overall: LOW–MEDIUM.** No data-loss or security risk. User-facing polish/clarity issues only. No blocking failures are incorrectly routed to toasts (critical errors already become persistent + closable).

### A.4 UX findings

| # | Finding | Severity | Where |
|---|---|---|---|
| UX-1 | Raw `'Error: ' + e.message` in catch blocks | **High** | mom-functions.js ×24 |
| UX-2 | Generic server-error fallbacks: "Error", "Error updating", "Save failed", "Delete failed", "Error saving/deleting transfer", "Unknown error" | Medium | tracs.js (ticker, domain, finance, feedback), dpc |
| UX-3 | Validation surfaced as toast instead of inline (e.g. "Title is required", "Amount is required", "Invalid sender email") | Medium | tracs.js ×16, mom ×7, shifting ×2 |
| UX-4 | Info toast with no value on a visible UI change ("Loaded action into inline editor…") | Low | mom-functions.js:658 |
| UX-5 | "success toast + reload" suspected double-toast | **Not a violation** (false positive) | global flash mechanism persists ONE toast and skips errors |
| UX-6 | Notification-receive loop could emit several toasts in one tick | Low (capped at 3 by stacking limit + dedup) | tracs.js:~3739 |

### A.5 Accessibility findings

**Strengths (already implemented):**
- `role="alert"` + `aria-live="assertive"` for errors; `role="status"` + `aria-live="polite"` otherwise (tracs.js:339-340).
- Close button has `aria-label="Dismiss notification"` (tracs.js:367); keyboard-focusable `<button>`.
- Critical errors are persistent (no premature auto-dismiss) and always closable.
- Icon **and** text both communicate; color is not the sole differentiator.
- Responsive max-width (`min(380px, calc(100vw - 32px))`) and dark-mode color handling exist.

**Gaps (in-scope, low-risk to fix):**
- A11Y-1: Toast **docks** have no container role/label — fine because each toast carries its own live-region role, but adding `role="region" aria-label="Notifications"` improves SR grouping. *(optional)*
- A11Y-2: The decorative toast type-icon is not `aria-hidden` — screen readers may read the lucide glyph name. **Fix: mark icon `aria-hidden="true"`.**
- A11Y-3: No `@media (prefers-reduced-motion: reduce)` rule covers toast enter/leave animations (two reduced-motion blocks exist but neither targets `.toast`). **Fix: add reduced-motion rule for toasts.**

### A.6 Visual consistency findings

- Position: consistent (top-right page, centered modal) ✓
- Icons: consistent via single `toastIconFor` map ✓
- Colors: consistent via per-type accent blend ✓ (success green / error red / warning amber / info blue)
- Typography: consistent (`.toast-title`/`.toast-msg`) ✓
- Dark mode: handled ✓
- Mobile: max-width scales ✓
- **Timing inconsistency:** a handful of call sites hardcode durations (e.g. infra 1800ms, notification 7000ms) that bypass the central duration map. Low impact.

### A.7 Technical findings

| Check | Result |
|---|---|
| Duplicate toast libraries | **None** — single home-grown system |
| Duplicate providers | **None** |
| Multiple notification systems | **No** — module adapters (`dpcToast`/`notify`) delegate to the one global API |
| Dead / legacy toast code | **None material** |
| Unused helpers | None found |
| Race conditions | Mitigated — dedup key + 3-stack cap + modal single-toast replace |
| Memory leaks | None — docks reused via Map/WeakMap; toasts removed on dismiss (`setTimeout … remove()`) |
| State conflicts | None observed |
| Missing cleanup | Modal docks cleaned on close (tracs.js:493-497) ✓ |

---

## B. Toast Inventory (representative)

| File:Line | Module | Trigger | Type | Message | Auto-dismiss | Actions | Current → Recommended |
|---|---|---|---|---|---|---|---|
| tracs.js:118 | infra | duration map | — | `{success:3500,info:4000,warning:7000,error:9000}` | — | — | Keep — matches standard |
| tracs.js:339-340 | infra | every toast | — | role+aria-live set | — | close | Keep |
| mom-functions.js:181 | MoM | save objective catch | error | `'Error: ' + e.message` | 9s | close | → "Couldn't update the objective. Please try again." |
| mom-functions.js:176 | MoM | save objective ok | success | "Objective updated" | persist-across-reload | — | Keep (single persisted toast) |
| tracs.js:3879 | Ticker | archive fail | error | `d.message \|\| 'Error'` | 9s | close | → `… \|\| 'Couldn't archive the announcement.'` |
| tracs.js:4735 | Domain | move fail | error | `d.message \|\| 'Error updating'` | 9s | close | → `… \|\| "Couldn't update the move-domain field."` |
| tracs.js:1999 | Feedback | delete fail | error | `res.error \|\| 'Delete failed'` | 9s | close | → `… \|\| "Couldn't delete the feedback entry."` |
| tracs.js:3116 | Case | title empty | error | "Case title is required" | 9s | close | Inline validation (phase 2) |
| tracs.js:3367 | Checklist | task deleted | success | "Task deleted." | 3.5s | — | Keep |
| profile.php:307 | Profile | password changed | success | flash message | 3.5s | — | Keep |
| infrastructure-pulse.js:796 | Infra | server removed | success | "Server removed from monitoring." | 1800ms | — | → use default success duration |

*(Complete line-by-line tables for all ~158 call sites are in the audit working notes; the above captures every distinct pattern.)*

---

## C. Standardization Recommendations (TRACS Notification Standard)

The existing system **already encodes** the target standard. Codify it:

| Type | Color | Duration | Dismiss | Role / aria-live |
|---|---|---|---|---|
| Success | green | 3.5s (3–5s) | auto | status / polite |
| Info | blue | 4s (4–6s) | auto | status / polite |
| Warning | amber | 7s (6–8s) | auto + closable | status / polite |
| Error | red | 9s, **persistent if critical** | closable; manual preferred | alert / assertive |
| Loading | — | n/a | — | Use **button loading state** (`withLoadingState`), not a toast |

**Single architecture (already in place — keep, don't fork):**
- Single provider/utility: `showToast` / `toast` in `tracs.js`.
- Single API signature; module adapters (`dpcToast`, `dpcNotify`, `notify`) are thin and acceptable — **do not introduce new ones**.
- Single icon map (`toastIconFor`), single duration map (`tracsToastDefaults`), single dedup + stacking policy.
- Single error normalizer (`getFriendlyErrorMessage`).

**Message style rules (enforce going forward):**
- Success: name the object + verb ("Objective updated.", "Domain transfer recorded."). Avoid bare "Success/Done".
- Error: say what failed + that it can be retried ("Couldn't update the objective. Please try again."). Never raw exception text or "Error".
- Warning: indicate risk/incomplete state.
- Validation: prefer inline; if toast, use `warning` not `error`.

---

## D. Refactor Plan (phased)

**Phase 1 — Safe, in-scope, implemented now** (message quality + a11y polish; no business-logic or form-structure change):
1. mom-functions.js: replace 24 `'Error: ' + e.message` with contextual, retry-able messages.
2. tracs.js: replace generic fallbacks ("Error", "Error updating", "Save failed", "Delete failed", "Error saving/deleting transfer") with specific fallbacks.
3. domain-price-crosscheck.js: replace "Unknown error" fallbacks with specific text.
4. tracs.js:658-equivalent / infra duration: route hardcoded durations through defaults where trivial.
5. A11Y-2: add `aria-hidden="true"` to the decorative toast icon.
6. A11Y-3: add `@media (prefers-reduced-motion: reduce)` rule for `.toast` animations.
7. mom-functions.js:658 info toast: keep but soften (no functional change) — low priority.

**Phase 2 — Recommended, NOT done now** (larger UX change; would touch form markup/behavior across modules — out of the "toast-only / no unrelated module change" guardrail):
- Convert "X is required" / "Invalid email" validation toasts to inline field validation (`aria-invalid` + helper text), reusing the existing `tracsFocusInvalidField` helper.
- Decide whether monitoring.php's panel-style flash should become a toast (currently intentional persistent panel).

**Phase 3 — Optional polish:**
- Container `role="region" aria-label="Notifications"` on docks.
- Centralize the few remaining hardcoded durations.

---

## E. Files Requiring Modification (Phase 1)

| File | Change |
|---|---|
| `public/assets/mom-functions.js` | Replace 24 generic catch messages with contextual ones |
| `public/assets/tracs.js` | Specific error fallbacks; `aria-hidden` on toast icon |
| `public/assets/domain-price-crosscheck.js` | Specific "Unknown error" fallbacks |
| `public/assets/infrastructure-pulse.js` | Use default success duration |
| `public/assets/tracs.css` | `prefers-reduced-motion` rule for toasts |

---

## Safety statement

This audit was produced **before** any code change. Phase 1 changes touch **only notification text and toast presentation/accessibility** — no business logic, no form behavior, no unrelated modules, and no notifications removed. Validation-to-inline conversion (Phase 2) is deferred precisely because it would change form behavior beyond toast scope.
