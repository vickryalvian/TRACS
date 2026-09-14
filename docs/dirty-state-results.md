# Dirty-state implementation and verification

See [the pre-implementation audit](dirty-state-audit.md) for the inventory and original failure modes.

## Result

Dirty state is calculated from current meaningful values versus a fully initialized baseline. Events refresh the display; they are not permanent dirty flags. Reverting a value, removing a newly staged image, or restoring the initial selection returns the form to clean.

The existing TRACS confirmation appearance is retained, with **Unsaved changes**, **Keep Editing**, and **Discard Changes**. Internal links and supported modal exits share this prompt. Real browser Back, reload and tab/window unload use the native beforeunload guard. Artificial history entries and duplicate configurator/shift unload listeners were removed. Discard restores values without dispatching change events that could accidentally invoke autosave.

## Root causes and changes

| Source | Change |
| --- | --- |
| `public/assets/unsaved-changes-guard.js` | Re-evaluate captured controls at exit; track custom state with stable object-key ordering; capture initialized baselines; preserve whitespace, multiple selections and disabled draft values; track canonical picker inputs instead of display duplicates; honor scope exclusions; serialize Save now; prevent repeated prompts and save-in-progress closes. |
| `public/assets/tracs.js` | Capture modal baseline synchronously; sync split date/time controls with their underlying value; wait for Case edit hydration before exposing the editor; ignore stale Case requests; track Case attachment arrays/removal IDs, checklist images, and handover rows/images; protect Case saves from duplicate submission and concurrent editing. |
| `assets/react/calendar/hooks/useUnsavedForm.js` | Small shared React adapter for initialized state, normalization, cleanup and guarded exits. No added dependency. |
| `assets/react/calendar/components/TracsModal.jsx` | Route X, overlay and Escape through the shared guard; keep React responsible for unmounting. |
| `assets/react/calendar/components/BookScheduleModal.jsx` | Track create/edit snapshots; guard all exits; acknowledge successful saves; prevent editing during save. |
| `assets/react/calendar/components/EventDetailPanel.jsx` | Guard reminder-editor cancellation and enclosing drawer exits; reset baseline on successful save. |
| `frontend/src/modules/clients/main.jsx` | Track profile/record defaults and staged attachments; guard Cancel; retain failed-save drafts; reuse a created client ID when retrying after an attachment upload fails. |
| `frontend/src/modules/shift-assignment/components/ShiftCreateModal.jsx`, `ShiftEditModal.jsx` | Keep snapshot semantics but replace the incompatible synchronous confirm and duplicate unload handlers with the shared guard. |
| `public/assets/infrastructure-configurator.js` | Compare configuration snapshots, including service/period, rows and prices; guard native dialogs; capture defaults and saved states; exclude preview/filter interactions. |
| `public/assets/infrastructure-pulse.js` | Capture completed form setup; guard closing, resetting, cancelling edit mode and switching records before replacing draft values. |
| `public/assets/domain-price-crosscheck.js` | Restore the exchange-rate preview only after discard is accepted, preserving it when Keep Editing is chosen. |
| `public/assets/mom-functions.js` | Track linked-case selections in create/edit; include a preselected linked case in the initial baseline. |

The React and Calendar production assets/manifests were rebuilt. No database changes, new packages, or design-system changes were made. Pre-existing changes to Abuse Reports, monitoring, screenshots, theme CSS, AI memory, and other untracked work were preserved.

## Cases before and after

Before: baseline capture depended on an animation frame; asynchronous edit data arrived afterward; native file input state did not represent staged attachment additions/removals; changing the underlying date did not synchronize its displayed parts.

After: immediate open/close is clean, including before an animation frame. Edit data is fully populated before opening. Text, priority, dates and attachment state compare against that initialized state. Reversion becomes clean. Failed saves preserve input and dirty state. Successful saves acknowledge the modal before normal closure.

## Executable verification

- `node tests/dirty-state-browser.cjs`: real shared footer markup, Flatpickr and application scripts with controlled API responses. Tests immediate Case close, X/Cancel/Escape, text/select reversion, uploaded/pasted/removed images, async edit hydration, persisted-value deletion/reversion, attachment removal/reversion, failed/successful saves, internal navigation, actual browser Back/reload cancellation, clean reload, checklist images, handover rows/images, MoM linked-case reversion, whitespace, checkbox/multiselect reversion, busy fields, token exclusions and initialization suppression.
- `node tests/client-calendar-browser.cjs`: built Clients/Calendar application with the shared guard. Tests clean cancellation, dirty Keep Editing, text reversion, attachment-only discard, failed attachment save and retry without duplicate client creation, operational record creation, reminder-editor reversion/save, calendar integration, cross-tab refresh, keyboard close and mobile layout checks.
- `node tests/react-editor-dirty-state-browser.cjs`: actual Shift create/edit and Calendar components. Tests initial clean state, Escape, Cancel/Keep Editing, reversion, and failed/successful shift edit saves.
- `node tests/configurator-dirty-state-browser.cjs`: real configurator markup/script. Tests initialized defaults, period/row reversion, native-dialog close/Escape, dirty Keep Editing and failed/successful pricing-item save.
- Frontend `npm run test:contracts`: all seven Node contract groups pass.
- `php tests/unsaved-changes-submit-flow.php`: passes, including preservation of Abuse Reports' scoped save behavior.
- Frontend/Calendar builds, JavaScript syntax checks and `git diff --check`: pass.

The fixtures intercept API writes; no application records are created by these browser tests. Only decorative icon rendering is stubbed in the legacy fixture.

## Existing contract failures

Three older PHP source-contract checks (`shift-assignment-create-ui-pilot.php`, `shift-assignment-edit-ui-pilot.php`, `shift-assignment-create-edit-hardening.php`) now recognize the shared close guard but still fail their existing API mutation-count assertions. They expect three POST methods; the unchanged API source contains later preview/commit operations. `frontend/tests/preview-bundle-contract.mjs` expects only Clients and Shift Assignment, while the unchanged build configuration also includes Branch Network. These unrelated assertions were not weakened to make this change pass.

## Remaining authenticated manual verification

The available local Cases endpoint redirects unauthenticated requests to login. Verification above exercises real source and built components using controlled API fixtures, rather than an authenticated database session.

Verify real role-specific create/edit/save flows for profile and user/intern settings, domain/finance transfer forms, the domain price matrix, infrastructure server management, MoM inline autosave/screenshots, and Abuse Reports with its in-progress changes. Their shared guard behavior was audited or corrected, but their real backend saves and permission combinations were not exercised here. Browser tab/window-close dialogs also require a manual check in the deployment browser; actual Back and reload were tested in Chromium.
