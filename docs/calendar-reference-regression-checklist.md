# Calendar Zero-Mistake Reference Regression Checklist

`public/calendar.php` is the primary reference implementation for the full TRACS
refactor. Changes to other modules must preserve its quality and must not break
it. This checklist does not authorize redesigning Calendar.

## Architecture Boundary

- [ ] PHP starts the hardened session and requires full authentication.
- [ ] The existing PHP header, sidebar, ticker, theme, footer, and CSRF metadata load.
- [ ] The Vite manifest resolves the current Calendar JavaScript and CSS assets.
- [ ] React mounts only at `#calendar-react-root`.
- [ ] Tailwind utilities remain prefixed with `cal:`.
- [ ] Tailwind Preflight is not loaded and legacy PHP pages are unaffected.
- [ ] Calendar colors, spacing, typography, radii, borders, and shadows continue
      mapping to existing TRACS CSS variables.
- [ ] PHP APIs remain authoritative for authentication, permissions, validation,
      database writes, and audit effects.

## Data And Date Behavior

- [ ] API and React state use ISO dates.
- [ ] User-facing dates use `dd-mm-yyyy`.
- [ ] Timezone-sensitive behavior uses `Asia/Jakarta`.
- [ ] Cases, reminders, checklist tasks, MoM meetings/actions, shifts, holidays,
      maintenance, domains, user dates, and manual schedules normalize correctly.
- [ ] Missing optional tables or columns produce empty sources without PHP warnings.
- [ ] Existing source records are not copied into `calendar_events`.
- [ ] Shift 3 displays `16:00-24:00` without leaking into the next date.

## Layout And Interaction

- [ ] Header, summary cards, toolbar, result count, and selected-date context align.
- [ ] Year, Month, Week, Day, and Agenda layouts preserve density and readability.
- [ ] Search, date range, type, status, user, division, role, priority, and source
      filters work together.
- [ ] Today, year navigation, date selection, event selection, and view switching work.
- [ ] Keyboard arrows, Enter, and Escape behave as documented outside form controls.
- [ ] Event detail and booking surfaces open, close, and restore context correctly.
- [ ] Loading skeleton, empty state, error message, and Retry remain usable.

## Modal, Toast, And Mutation Behavior

- [ ] Booking create/edit validation reports field errors clearly.
- [ ] Save/delete actions show a correct loading state and prevent duplicate submission.
- [ ] Success and error toasts have correct severity, placement, and readable text.
- [ ] Toasts inside modal context are not hidden behind overlays.
- [ ] Escape, close controls, overlay behavior, and focus handling remain accessible.
- [ ] Manual schedule actions are hidden or disabled when permission is absent.
- [ ] Source-owned mark-done actions refresh Calendar after successful completion.

## Responsive And Visual Identity

- [ ] Desktop layout matches current spacing rhythm and card density.
- [ ] Tablet and mobile controls wrap without overlap or clipping.
- [ ] Mobile modals use the intended bottom-sheet behavior.
- [ ] Tables/grids remain scrollable where needed.
- [ ] Dark-mode colors, borders, text contrast, and native controls remain readable.
- [ ] Reduced-motion preferences minimize animation and transition duration.
- [ ] No Tailwind styles leak into the sidebar, ticker, global modals, or other pages.
- [ ] No unrelated generic Tailwind visual identity replaces TRACS styling.

## Refactor Acceptance Rule

Every future React/Tailwind module should be compared against Calendar for:

- Layout and spacing rhythm.
- Toolbar and filter behavior.
- Date-picker and date-format behavior.
- Modal and toast behavior.
- Table/list density.
- Loading, empty, error, retry, and success states.
- Responsive behavior and keyboard accessibility.
- Tailwind token mapping and isolation.
- PHP permission and API enforcement.

Calendar regressions block release of the refactoring batch that caused them.
