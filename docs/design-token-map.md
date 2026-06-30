# TRACS Design Token Map

## Authority And Naming

Current authority:

1. `public/assets/tracs.css` for palette, typography, radius, motion, elevation,
   and semantic layout variables.
2. `public/assets/tracs-spacing.css` for shared density and responsive spacing.
3. `assets/react/calendar/styles.css` and Calendar components for the proven
   Tailwind translation and interaction density.

The future Tailwind token names in this document are semantic. They map to
existing variables and therefore inherit current light/dark behavior.

## Colors

| Future token | Current variable | Purpose |
| --- | --- | --- |
| `page` | `--bg` | Main application background |
| `page-subtle` | `--bg2` | Secondary page background |
| `card` | `--s1` | Primary card/modal surface |
| `surface-2` | `--s2` | Inputs, headers, secondary surfaces |
| `surface-3` | `--s3` | Hover and nested surface |
| `surface-4` | `--s4` | Strong hover/tooltip surface |
| `surface-5` | `--s5` | Strongest neutral surface |
| `border` | `--bd1` | Standard divider and card border |
| `border-muted` | `--bd2` | Intermediate border |
| `border-strong` | `--bd3` | Modal/dropdown/active structure |
| `border-emphasis` | `--bd4` | Highest neutral contrast |
| `text-primary` | `--tx1` | Titles and primary content |
| `text-secondary` | `--tx2` | Supporting content |
| `text-muted` | `--tx3` | Metadata and helper text |
| `text-faint` | `--tx4` | Placeholder and low-emphasis context |
| `accent` | `--blue` | Active state and primary action |
| `accent-strong` | `--blue-dk` | Accent hover/emphasis |
| `accent-soft` | `--blue-lt` | Selected/soft accent background |
| `accent-border` | `--blue-bd` | Accent border/focus context |
| `success` family | `--green*` | Successful/completed state |
| `warning` family | `--amber*` | Warning/attention state |
| `danger` family | `--red*` | Error/destructive/critical state |
| `info` family | `--cyan*` or accent by context | Informational operational state |
| `purple` family | `--purple*` | Existing additional category/status tone |

Do not expose arbitrary Tailwind default color scales as the normal component API.

## Spacing

| Future token | Current value/source | Use |
| --- | --- | --- |
| `0` | `--space-0`, `0` | Reset |
| `1` | `--space-1`, `4px` | Tight icon/label or form label gap |
| `2` | `--space-2`, `8px` | Toolbar/action gap |
| `3` | `--space-3`, `12px` | Content/form/modal section gap |
| `4` | `--space-4`, `16px` | Default card/page padding |
| `5` | `--space-5`, `20px` | Page section gap |
| `6` | `--space-6`, `24px` | Large empty-state/section spacing |
| `7` | `--space-7`, `28px` | Exceptional large spacing |
| `8` | `--space-8`, `32px` | Largest standard spacing |
| `page-inline` | `--page-padding-inline`, `16px` | Desktop page gutter |
| `page-block` | `--page-padding-block`, `16px` | Desktop page gutter |
| `section-gap` | `--page-section-gap`, `20px` | Major page sections |
| `card-gap` | `--card-gap`, `16px` | Card/grid separation |
| `card-body` | `--card-body-padding`, `16px` | Default card body |
| `toolbar` | `--toolbar-padding`, `12px` | Toolbar container |
| `modal` | `--modal-padding`, `16px` | Viewport overlay padding |

At `760px` and below, current shared spacing reduces page/card values primarily
from `16px` to `12px`; modal viewport padding becomes `10px`.

## Typography

| Role | Planned token/value | Calendar/current evidence |
| --- | --- | --- |
| UI font | `--font` | Inter/system stack |
| Metadata font | `--mono` | IBM Plex Mono-compatible stack |
| Page heading | `20px`, semibold | Calendar `text-xl` |
| Section heading | `14px`, semibold | Calendar panel/modal headings |
| Card/item title | `12px`, semibold | Existing item-title variables |
| Body | `12px`-`13.5px` | Compact operational content |
| Control | `11.5px`-`12px`, medium | Calendar buttons and global toolbar |
| Label | `8.5px`-`10px`, bold/uppercase where current | Calendar field labels/metadata |
| Helper/error | `10px` | Calendar field hint/error |
| Table | `10px`-`12px` | Existing dense tables and Calendar agenda |
| Badge/pill | `8.5px`-`10px`, bold | Existing pill and Calendar event badges |

Do not increase typography merely to match generic product dashboards; TRACS is
an intentionally compact operational interface.

## Radius

| Future token | Current source | Use |
| --- | --- | --- |
| `radius-sm` | `4px` | Date cells, badges, compact tab state |
| `radius` | `--radius`, `6px` | Buttons, inputs, standard controls |
| `radius-md` | `--r2`, `7px` | Compatible legacy middle radius |
| `radius-lg` | `--radius-lg`, `8px` | Cards, modals, toolbars |
| `radius-xl` | `--r4`, `10px` only where existing | Exceptional container |
| `radius-full` | `9999px` | Dots/avatars only; not standard cards/buttons |

## Elevation

| Future token | Current source | Use |
| --- | --- | --- |
| `shadow-card` | `--shadow` | Cards and toolbars |
| `shadow-overlay` | `--shadow-lg` | Drawers and dropdowns |
| `shadow-modal` | existing `.modal` shadow | Modal-specific strongest depth |
| `shadow-toast` | existing `.toast` light/dark shadow | Toast-specific depth |
| `shadow-sticky` | Calendar sticky-header formula | Sticky operational header |

Modal/toast formulas remain component tokens rather than being collapsed into
one generic shadow.

## Density And Geometry

| Element | Current/planned value |
| --- | --- |
| Standard control | `36px` |
| Compact/icon Calendar action | `32px` |
| Icon inside compact control | approximately `14px`-`16px` |
| Table row | `44px` target |
| Table header cell | `9px 12px` |
| Table body cell | `10px 12px` |
| Panel/header minimum | `44px` |
| Badge/pill | approximately `20px` minimum height |
| Card body | `16px`, compact `12px` |
| Toolbar | `12px` padding, `8px` gap |
| Modal body | `16px` |
| Modal header/footer | `12px 16px` |
| Default modal | up to about `680px` |
| Drawer | Calendar examples `380px` filter, `460px` detail |
| Mini calendar date cell | `28px`-`32px` |
| Month view cell | minimum approximately `112px` high |

Module geometry such as Shift Assignment timeline widths remains module-local.

## Motion

| Token | Current value | Rule |
| --- | --- | --- |
| `ease-standard` | `--ease`, cubic-bezier(.16,1,.3,1) | Standard UI transition |
| `duration-fast` | `--dur`, `120ms` | Hover/focus/simple state |
| Reduced motion | approximately `.01ms` | Disable meaningful animation loops |

Loading spinners may continue under reduced motion only if accessibility review
confirms a non-animated status indicator is also present.

## Responsive Mapping

Current CSS uses page-specific breakpoints. The shared planning set is:

| Name | Approximate boundary | Evidence/use |
| --- | --- | --- |
| `mobile` | `520px` | Single-column forms and wrapped actions |
| `compact` | `760px` | Shared spacing reduction and mobile modal |
| `tablet` | `960px` | Common grid/column reduction |
| `laptop` | `1200px` | Dashboard and dense layout transitions |
| `desktop` | above `1200px` | Full workspace |
| `wide` | `1536px`/Tailwind `2xl` | Calendar full filter toolbar |

These names are not enabled in config during Phase 3.

## Calendar Reference Notes

- Cards use card surface, border, `8px` radius, and card shadow.
- Controls use `36px` height in forms; compact actions use `32px`.
- Toolbar uses `12px` padding and `8px` gaps.
- Calendar summary cards use `12px` padding and compact icon blocks.
- Date labels and operational metadata use the mono stack.
- Modal maximum width is approximately `680px`.
- Mobile modal becomes a bottom sheet below `768px`.
- Calendar cells prioritize dense readable information over decorative whitespace.
- Loading, empty, and error states share one visual hierarchy.

Any shared-token change must be checked against
`docs/calendar-reference-regression-checklist.md`.
