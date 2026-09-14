# Dirty-state audit (before implementation)

2026-09-14. Scope: application source, excluding generated bundles and backups.

| Area | Existing detection | Findings / planned changes |
| --- | --- | --- |
| `public/assets/unsaved-changes-guard.js` | Per-control WeakMap baseline plus event-maintained dirty Set; global navigation/unload protection | Recompute state at exit; preserve meaningful whitespace; support multiple selects, picker source values and custom state; explicit initialization lifecycle; avoid duplicate prompts and unsafe reset events. |
| `public/assets/tracs.js`, shared footer / Cases | Modal baseline captured on animation frame; edit API populates later; files and removal IDs in JS arrays | Capture initialized state synchronously; finish asynchronous edit before enabling editing; track staged attachment state; sync visible dates with saved values. |
| Clients (`frontend/src/modules/clients/main.jsx`) | Global input listeners, attachment picker ignored | Close/Cancel unmount directly; attachment-only changes invisible; use state snapshots and guarded modal exits. |
| Calendar (`assets/react/calendar/components`) | Global input listeners | Booking and reminder editors close directly; initial React effects can run after baseline; guard close and successful save explicitly. |
| Shift assignment create/edit React modals | JSON draft comparison, separate beforeunload and synchronous confirm | Reversion works, but TRACS overrides window.confirm asynchronously; consolidate navigation and prompt handling. |
| Sales (`public/assets/infrastructure-configurator.js`) | Hard-coded draft predicate and independent beforeunload | Missing service/period/row changes; native dialogs close directly; initialization/save lifecycle absent. |
| MoM (`public/assets/mom-functions.js`) | Shared guard and per-field successful autosave acknowledgement | Header defaults need initialized baseline; linked case selection is custom state. Screenshots persist immediately and should not become staged changes. |
| Domain price matrix | Explicit register with currency normalization and save/discard | Preserve integration; shared helper must respect scope exclusions and successful saves. |
| Domains/finance, infrastructure, shifts, profile, user/intern management, monitoring, activity, reminders/checklist, feedback | Shared POST-form/modal detection, some successful-save hooks and ignore markers | Benefit from common baseline/normalization fixes; check modal entry points and staged uploads. Preserve autosaved and filter exclusions. |
| Abuse reports | Explicit guarded close and scoped save acknowledgement | Preserve unrelated in-progress changes; no blanket save acknowledgement. |

False positives: initialization events; late edit hydration; formatted dates; stale native file selection after removing staged images; filter controls; duplicate unload guards. False negatives: programmatic updates without events; hidden picker sources; attachment removal IDs; first change on late-added controls; direct React/native-dialog unmount/close; unconditional whitespace trimming.

Common behavior: meaningful current state differs from the fully initialized baseline. Opening/defaulting/loading is clean; reversion is clean; successful save acknowledges only the saved scope; failure preserves it. Internal discard uses the existing TRACS dialog, actual unload uses beforeunload. Custom JS state needs a small explicit snapshot adapter, not a new dependency.

Planned modifications: shared guard and modal/date/Case handlers; React modal, client/calendar/shift editors; configurator; MoM linked-case initialization where needed; focused executable regression coverage. No database changes or visual redesign. Existing unrelated working-tree edits are preserved.
