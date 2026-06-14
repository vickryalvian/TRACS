# TRACS Tailwind Design System Plan

## Purpose

Phase 3 consolidates the existing TRACS visual language into a documented
Tailwind foundation for future React modules. It does not redesign pages,
replace legacy CSS, load Tailwind globally, or convert a module.

`public/calendar.php` and `assets/react/calendar/` are the zero-mistake visual
and interaction reference. `public/assets/tracs.css` and
`public/assets/tracs-spacing.css` remain the current token and legacy-component
sources of truth.

## Audit Summary

The existing system already defines:

- Light and dark surface, border, text, accent, and status palettes.
- A 4px-based spacing scale from `0` through `32px`.
- Semantic page, section, card, toolbar, form, table, modal, and empty-state
  spacing variables.
- Inter for interface text and IBM Plex Mono-compatible stacks for metadata.
- Compact radii centered on `6px` and `8px`.
- Card and modal elevation tokens.
- A `36px` standard control height and `44px` table row target.
- Shared button, form, modal, toast, badge, empty-state, table, and responsive
  behavior.
- Calendar-specific React primitives with prefixed Tailwind utilities and no
  Preflight.

The main consolidation task is naming and reusing these existing decisions, not
inventing new values.

## Safety Decision

Phase 3 adopts all four isolation controls:

1. **No Preflight:** Future entries import Tailwind theme and utilities only.
2. **Prefix:** Shared future utilities use the `tr:` prefix. Calendar retains
   its existing `cal:` prefix until a separately approved migration.
3. **Separate output:** Each React module receives CSS only through its Vite
   entry and manifest assets.
4. **React-root scope:** Handwritten selectors begin at `.tracs-react-root` or a
   more specific module root.

Tailwind CSS must not be linked from the shared PHP header. A PHP page receives
Tailwind output only when that page has an approved React module and its
allowlisted manifest entry is loaded.

Legacy PHP classes such as `.btn`, `.panel`, `.modal`, and `.form-input` remain
unchanged. Future React components use prefixed utilities and semantic
component APIs; they must not redefine those legacy selectors.

## Minimal Scaffold

Phase 3 adds two non-loaded templates:

```text
frontend/src/styles/
  tokens.css
  tracs-tailwind.css
```

They are not referenced by the current root Vite config, package scripts, PHP
pages, or production assets. Their purpose is to review semantic names and the
safe Tailwind import pattern before shared components are implemented.

No `tailwind.config.js` is needed yet because Tailwind v4 supports CSS-first
theme declarations. No `postcss.config.js` is needed while the approved
architecture uses `@tailwindcss/vite`. Add either file only for a demonstrated
requirement.

## Tailwind Theme Rules

- Semantic tokens reference existing TRACS CSS variables.
- Do not copy light/dark hex values into React module styles.
- Do not use arbitrary Tailwind palette classes such as `bg-slate-900` for
  normal TRACS surfaces.
- Arbitrary values are acceptable only for measured module geometry that is
  documented and not yet a shared token.
- Promote a repeated arbitrary value only after at least two modules need it.
- Use status colors by meaning, never decoration.
- Blue remains the active/highlight accent rather than a general card color.
- Completed states remain readable and are not faded until context is lost.

## Component Pattern Contracts

Each future component must support light/dark states, keyboard access, disabled
and loading behavior where applicable, and Calendar-level density.

### Button

- Visual: primary, secondary/ghost, danger, quiet, and icon variants.
- Density: standard `36px`; compact Calendar actions may use `32px`.
- Dark mode: semantic surface, border, and accent variables.
- Accessibility: native button, visible focus, accessible icon label, disabled
  semantics, no duplicate submission while loading.
- Calendar relation: follow `TracsButton` spacing, icon scale, and focus ring.

### Input And SearchInput

- Visual: surface-2 background, standard border, primary text, faint
  placeholder, accent focus, danger invalid state.
- Density: `36px`, `10px` horizontal padding; search reserves icon space.
- Dark mode: native date/search controls remain legible.
- Accessibility: visible label, description/error association, autocomplete and
  input mode where appropriate.
- Calendar relation: follow `TracsInput` and Calendar search density.

### Select

- Visual/density: match Input and preserve native option readability.
- Dark mode: use the current page color scheme and semantic option surfaces.
- Accessibility: native select by default, explicit label, valid keyboard behavior.
- Calendar relation: dense toolbar selects and full-width modal selects use the
  same control token.

### Checkbox And Radio

- Visual: native control with TRACS accent and clear checked/invalid state.
- Density: minimum comfortable click target even when the visible control is compact.
- Dark mode: border and check contrast must remain visible.
- Accessibility: label wraps control and text; group uses fieldset/legend when needed.
- Calendar relation: match filter behavior rather than introducing toggle styling.

### Card

- Visual: card surface, border-1, radius-lg, card shadow.
- Density: semantic header/body padding and `16px` default body padding.
- Dark mode: existing card surface and shadow variables.
- Accessibility: semantic section/article only when content structure warrants it.
- Calendar relation: follows `TracsCard`, summary cards, and month cards.

### SectionHeader

- Visual: compact title, optional metadata, contextual icon, right-aligned actions.
- Density: minimum `44px` header with `12px`/`16px` padding rhythm.
- Dark mode: surface/card hierarchy remains subtle.
- Accessibility: correct heading level; actions retain visible labels/tooltips.
- Calendar relation: Calendar modal, agenda, and month headers.

### Toolbar And FilterBar

- Visual: bordered card, compact controls, result metadata, clear reset action.
- Density: `12px` padding, `8px` gaps, `36px` controls.
- Responsive: full controls only when they fit; otherwise search plus filter drawer.
- Accessibility: labels for every filter and predictable Apply/Reset behavior.
- Calendar relation: Calendar toolbar is the primary reference.

### DatePicker

- Visual: TRACS card/surface hierarchy, compact date cells, selected accent,
  muted out-of-range dates, readable event/status markers.
- Density: match Calendar popup and date-cell rhythm.
- Dark mode: no native white popup surfaces where custom rendering is used.
- Accessibility: keyboard date navigation, announced selected/current date.
- Calendar relation: display `dd-mm-yyyy`; state/API remains ISO.

### CalendarShell

- Visual: sticky header, summary strip, toolbar, context line, and selected view.
- Density/responsive: preserve Calendar's current layouts.
- Accessibility: keyboard navigation and view state announcement.
- Reuse rule: remain module-specific until another scheduler proves common needs.

### DataTable

- Visual: compact header, border dividers, readable row states, optional sticky context.
- Density: `44px` row target; `10px 12px` body cells; `9px 12px` headers.
- Responsive: horizontal overflow rather than clipped columns.
- Accessibility: semantic table, sortable header state, caption/accessible name.
- Calendar relation: Agenda and Week views set the compact information density.

### Modal And ConfirmDialog

- Visual: strong overlay, bordered card, header/body/footer regions, modal shadow.
- Density: `16px` body padding, `12px 16px` header/footer; default width near
  Calendar's `680px`, with explicit small/large variants.
- Responsive: bottom-sheet behavior on compact screens where appropriate.
- Dark mode: existing overlay, surface, border, and shadow identity.
- Accessibility: focus trap/restore, Escape, labelled dialog, destructive
  confirmation language, no overlay close during irreversible loading.
- Calendar relation: `BookScheduleModal` is the primary reference.

### Toast

- Visual: semantic accent, filled readable surface, icon/title/message/dismiss.
- Density: `12px` padding and gap; page or modal-context placement.
- Dark mode: use existing filled toast variables and shadow.
- Accessibility: polite live region for normal messages, assertive for critical
  failures, dismiss button, sufficient reading duration.
- Calendar relation: preserve modal stacking and server-message mapping.

### EmptyState And LoadingState

- Visual: contextual icon, short title, supporting copy, one clear recovery action.
- Density: generous vertical breathing room inside the same card system.
- Dark mode: muted copy remains readable.
- Accessibility: loading status is announced without repeated noise; skeletons
  do not masquerade as content.
- Calendar relation: Calendar empty/error cards and skeleton grid.

### Badge And Pill

- Visual: semantic status tone, border, compact radius, no decorative random colors.
- Density: approximately `20px` minimum height; compact `8.5px`-`10px` label.
- Dark mode: stronger status borders where current tokens require them.
- Accessibility: never rely on color alone; include status text.
- Calendar relation: event/status badges and dot stacks.

### Tabs

- Visual: bordered compact group or underline only when matching the host page.
- Density: approximately `32px` actions with `8px` group padding/gap rhythm.
- Responsive: horizontal scroll instead of compressed unreadable labels.
- Accessibility: tabs/tabpanel roles, arrow navigation, selected state.
- Calendar relation: Calendar view switcher.

### Dropdown And MoreMenu

- Visual: surface card, strong border, dropdown shadow, compact action rows.
- Density: `32px`-`36px` action targets.
- Dark mode: semantic surfaces and readable destructive action.
- Accessibility: menu semantics where appropriate, Escape, click-away, focus
  return, viewport collision handling.
- Calendar relation: preserve action hierarchy and permission-hidden actions.

### Pagination

- Visual: compact previous/next and page controls with active accent.
- Density: standard compact button system.
- Responsive: collapse long ranges; preserve current page and total context.
- Accessibility: navigation label, current-page state, disabled boundaries.
- Calendar relation: use Calendar toolbar metadata typography for totals.

### UnsavedChangesBanner

- Visual: sticky warning surface with concise message and Save/Discard actions.
- Density: compact but persistent; must not obscure page controls.
- Dark mode: warning contrast without overpowering content.
- Accessibility: announce once when becoming dirty; confirmations retain focus.
- Calendar relation: use shared modal/toast rhythm, not a browser-native-only flow.

## Responsive System

The current CSS contains many module-specific breakpoints. Phase 3 does not
normalize them prematurely. Shared future names should be based on observed
layout transitions:

| Proposed name | Planning range | Intended use |
| --- | --- | --- |
| `mobile` | up to about `520px` | Single-column forms, wrapped actions |
| `compact` | up to about `760px` | Reduced page/card padding, sheet modals |
| `tablet` | about `761px`-`960px` | Two-column/grid reductions |
| `laptop` | about `961px`-`1200px` | Dense toolbar/grid transitions |
| `desktop` | above about `1200px` | Full operational workspace |
| `wide` | above about `1536px` | Calendar full filter toolbar and wide tables |

These are planning labels, not yet Tailwind screen configuration. Each proposed
screen must be validated against Calendar and at least one additional module.
TV Mode retains separate viewport and fullscreen logic.

## Dark Mode

The PHP theme bootstrap sets `data-theme` and the existing CSS variables change
under `[data-theme="dark"]`. Future Tailwind tokens consume those variables, so
the same utility class works in both modes without duplicate `dark:` colors.

Use explicit dark variants only for behavior that cannot be expressed through
the semantic variables. Test:

- Text and placeholder contrast.
- Status borders and muted states.
- Modal and toast depth.
- Native select/date/control color scheme.
- Focus rings and disabled controls.
- Charts, event dots, and calendar density.

React must observe the shell theme; it must not create another theme source of truth.

## Governance

- New token proposals include source page, measured value, semantic purpose,
  light/dark behavior, and responsive behavior.
- Changes to shared tokens require Calendar regression review.
- Module-specific geometry stays local until reuse is demonstrated.
- Component contracts are documented before shared React implementation.
- No legacy CSS selector is removed while a PHP page depends on it.
- Tailwind output is production-purged through explicit source paths.

## Phase 3 Acceptance

- Token map reflects actual TRACS and Calendar values.
- Tailwind template excludes Preflight and uses `tr:` prefix.
- Scaffold is not loaded by any PHP page or Vite entry.
- No current layout, behavior, business logic, or schema changes.
- Architecture, migration, and roadmap docs point to the canonical plan.
