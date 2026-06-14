# TRACS React And Tailwind Architecture

## Decision

TRACS will use an incremental React-island architecture while the current PHP
application remains operational. Each migrated page keeps its authenticated PHP
route and shared application shell, then mounts one React module inside an
explicit root container.

`public/calendar.php` is the zero-mistake reference implementation. Its current
source and build paths remain unchanged until a separately approved migration.

```text
Browser
  -> public/<module>.php
     -> hardened PHP session and authentication
     -> page permission check
     -> shared header/sidebar/ticker/theme
     -> React root with a PHP fallback state
     -> Vite manifest-loaded module CSS and JavaScript

React module
  -> same-origin PHP API
     -> authentication, session, CSRF, permission, and object scope
     -> validation and service layer
     -> MySQL transaction
     -> audit, notification, ticker, or linked-module effects
     -> standard JSON response
```

React is responsible for interactive presentation and client state. PHP remains
authoritative for authentication, permissions, validation, business rules,
database writes, audit logging, uploads, exports, and server-side security.

## Tailwind Isolation Strategy

Use a hybrid of three protections:

1. Do not import Tailwind Preflight into hybrid page bundles.
2. Build separate CSS output for React module entries.
3. Prefix generated utilities and mount them inside an isolated React root.

Calendar currently uses the `cal:` prefix. Future shared React modules should
use one documented common prefix such as `tr:` so shared components can be used
across modules. Existing Calendar classes must not be mechanically renamed.

The root container should also have a stable module class such as:

```html
<div id="shift-assignment-react-root" class="tracs-react-root tracs-react-shift-assignment">
</div>
```

The prefix prevents class-name collisions. The root class scopes the small
amount of handwritten module CSS required for native controls, portals,
scrollbars, complex grids, and browser-specific behavior. Separate build output
prevents an unfinished module from changing unrelated PHP pages.

Do not use a global Tailwind reset, global element selectors, or unprefixed
utility output while server-rendered PHP pages remain.

## Design Token Mapping

The existing CSS custom properties in `public/assets/tracs.css` remain the
source of truth during migration. Tailwind theme values should reference those
variables rather than copy light/dark color values.

| Tailwind semantic token | Existing TRACS source |
| --- | --- |
| Page/background | `--bg`, `--bg2` |
| Card/surfaces | `--s1` through `--s5` |
| Borders | `--bd1` through `--bd4` |
| Primary/secondary/muted text | `--tx1` through `--tx4` |
| Accent | `--blue`, `--blue-lt`, `--blue-bd` |
| Success | `--green`, `--green-lt`, `--green-bd` |
| Warning | `--amber`, `--amber-lt`, `--amber-bd` |
| Danger | `--red`, `--red-lt`, `--red-bd` |
| Additional status colors | Purple and cyan variable families |
| Spacing | `--space-0` through `--space-8` |
| Card padding/gaps | `--card-*`, `--content-gap` |
| Page/section rhythm | `--page-*`, `--layout-*` |
| Controls/toolbars | `--control-height`, `--toolbar-*` |
| Table density | `--table-*` |
| Modal spacing | `--modal-*` |
| Typography | `--font`, `--mono`, existing item-title variables |
| Radius | `--radius`, `--radius-lg`, compatible aliases |
| Shadows | `--shadow`, `--shadow-lg` |
| Motion | `--ease`, `--dur` |

Dark mode continues to be controlled by the PHP shell through
`[data-theme="dark"]`. React components consume the same semantic variables and
must not maintain a separate dark-mode palette.

## UI Pattern Mapping

| Pattern | TRACS/Tailwind direction |
| --- | --- |
| Buttons | Preserve 36px control rhythm, compact labels/icons, existing primary/ghost/danger hierarchy, visible focus, and local loading state |
| Inputs and selects | Map surfaces, borders, text, placeholder, focus, invalid, disabled, and read-only states to TRACS variables |
| Checkbox and radio | Use accessible native controls or a tested shared primitive; preserve keyboard behavior and TRACS accent/status colors |
| Cards and sections | Use semantic card padding, header/body padding, section gaps, compact radius, and existing shadows |
| Toolbar and filters | Use `--toolbar-*`, compact wrapping, stable control heights, clear Apply/Reset behavior, and mobile stacking |
| Modal | Preserve overlay, header/body/footer rhythm, focus management, Escape behavior, scroll limits, and mobile bottom-sheet treatment |
| Toast | Preserve severity colors, page/modal context, stacking, readable dark mode, persistent critical errors, and dismiss behavior |
| Tables | Preserve 44px row target, compact headers/cells, horizontal overflow, sticky context where current behavior requires it, and readable completed states |
| Date picker | Display `dd-mm-yyyy`, retain ISO values internally, match Calendar popup density, and remain keyboard accessible |
| Empty/loading/error | Use the Calendar hierarchy: contextual icon, short title, supporting text, and one clear recovery action |
| Badges/pills | Use semantic status variants, compact height/padding, and sufficient light/dark contrast |
| Dropdown/More menu | Preserve current trigger hierarchy, click-away/Escape behavior, viewport collision handling, and permission-based action visibility |

Responsive planning starts from existing behavior rather than Tailwind defaults:

- Mobile: below approximately `520px`, single-column forms and wrapping actions.
- Compact/tablet: below approximately `760px`, reduced page/card padding and
  mobile modal treatment.
- Module-specific grid transitions may add named breakpoints only when the
  existing page requires them.
- TV Mode keeps its separate compact, narrow, fullscreen, and 4K behavior.

Exact breakpoints should become shared semantic screens only after a responsive
audit confirms the values used across current TRACS pages.

## Component Architecture

Future source should separate shared primitives from module-specific behavior:

```text
frontend/
  src/
    components/
      ui/
        Button/
        Input/
        Select/
        Checkbox/
        Modal/
        Toast/
        Card/
        Badge/
        Tabs/
        Dropdown/
        MoreMenu/
        Pagination/
      patterns/
        Toolbar/
        FilterBar/
        DatePicker/
        DataTable/
        SectionHeader/
        EmptyState/
        LoadingState/
        ConfirmDialog/
        UnsavedChangesBanner/
    hooks/
    lib/
      api/
      dates/
      permissions/
      validation/
    modules/
      calendar/
      shift-assignment/
      checklist-reminder/
      dashboard/
      cases/
      shift-reports/
      mom/
      domain-pricing/
      domain-transfer/
      cancellation-feedback/
      infrastructure-pulse/
      opstrack/
      user-management/
      reports/
      profile/
      auth/
      super-admin/
    styles/
      tokens.css
      utilities.css
      components.css
    entries/
  vite.config.js
  postcss.config.js
```

Tailwind v4 can keep theme declarations in CSS. A
`frontend/tailwind.config.js` should be added only if a later requirement cannot
be expressed cleanly through the CSS-first configuration. Do not add empty
configuration files merely to resemble an older Tailwind layout.

## Shared Component Rules

- Components expose semantic variants such as `primary`, `danger`, `quiet`, and
  `compact`, not arbitrary product-specific colors.
- Module components own domain behavior; shared UI components do not call APIs.
- Modal and toast portals must stay inside a known TRACS overlay layer and
  preserve focus, Escape behavior, stacking, and reduced motion.
- Inputs expose labels, descriptions, field errors, disabled/loading states, and
  accessible IDs.
- Data tables support compact density, horizontal overflow, empty/loading/error
  states, and server-driven pagination where required.
- DatePicker displays `dd-mm-yyyy`; API and internal state use ISO dates.
- CalendarShell remains calendar-specific until another module demonstrates a
  genuinely reusable scheduling abstraction.
- Existing vanilla helpers remain available for non-React pages during the
  transition. Shared React replacements are introduced only with parity tests.

## Vite Build And PHP Loading

The future Vite build should use named module entries and one manifest:

```text
frontend/src/entries/calendar.jsx
frontend/src/entries/shift-assignment.jsx
frontend/src/entries/checklist-reminder.jsx
...
```

Recommended output:

```text
public/assets/react-dist/
  .vite/manifest.json
  assets/<hashed-js-and-css>
```

PHP should use one shared manifest helper that:

- Resolves an allowlisted entry name.
- Returns hashed JavaScript, CSS, and imported chunks.
- Escapes generated URLs.
- Fails closed to a useful PHP fallback state when assets are absent.
- Never accepts an arbitrary filesystem path from request input.
- Supports cache-busted production assets without `filemtime` coupling.

During migration, Calendar continues loading
`public/assets/calendar-dist/.vite/manifest.json`. A future approved build
consolidation can move it only after visual and behavioral parity is verified.

Production deploys build assets before switching the live release. Source maps
should not be publicly deployed unless access is explicitly restricted.

## React Mount Contract

Each PHP page supplies only non-sensitive bootstrap data required before the
first request:

```html
<div
  id="module-react-root"
  data-module="shift-assignment"
  data-locale="en"
  data-timezone="Asia/Jakarta"
></div>
```

Prefer a metadata API for users, permissions, filters, and module configuration.
If JSON bootstrap data is embedded, encode it with safe JSON flags and never
include secrets, unrestricted permission catalogs, or records outside the
current user's scope.

React should run in `StrictMode` during development. Module errors should be
caught by an error boundary that offers a retry or safe reload and does not
expose server internals.

## API Client Contract

Use one shared same-origin client:

- `credentials: "same-origin"`.
- `Accept: application/json`.
- JSON content type only when sending JSON.
- `X-CSRF-Token` for mutating requests, read from the PHP-provided meta tag.
- Abort support for superseded filters and unmounted views.
- Consistent parsing of success, errors, field validation, and metadata.
- No automatic mutation retry unless the endpoint is explicitly idempotent.

Target response:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": [],
  "meta": {}
}
```

Client behavior:

| Condition | React behavior |
| --- | --- |
| `200` success | Update state; show a toast only when useful |
| Empty result | Render module-specific empty state, not an error |
| `401` | Stop requests and redirect/reload through the normal login flow |
| `403` | Show a permission-safe state; do not reveal hidden controls |
| `404` | Show not-found or concealed-access state |
| `409` | Preserve user input and show conflict guidance |
| `422` | Map field errors to controls and focus the first invalid field |
| `429` | Show retry guidance and honor server timing metadata |
| `5xx` or invalid JSON | Show sanitized error/retry state and keep user input |

Toast messages may use the server's safe `message`; detailed validation stays
inline. Loading states are local to the affected control or region. Full-page
loading is reserved for initial module data.

## Query And Date Conventions

- Use ISO `YYYY-MM-DD` and ISO-compatible date-time values at API boundaries.
- Display dates as `dd-mm-yyyy`.
- Use `Asia/Jakarta` as the operational timezone unless a future API explicitly
  returns another timezone.
- Use query parameters for reads:
  `page`, `per_page`, `sort`, `direction`, `q`, `start`, `end`, and named filters.
- Return pagination and active-filter information under `meta`.
- Use stable snake_case field names in API data.
- Keep CSV downloads as normal authenticated browser requests rather than
  loading large exports into React memory.

## State Management

Start with React state, reducers, and focused hooks. Add a server-state library
only when repeated caching, invalidation, polling, or optimistic updates create
clear value across multiple modules. Add a global client-state library only
when state genuinely spans independent React roots.

The initial shared state should be limited to:

- Session-expired handling.
- Toast queue.
- Modal/confirmation infrastructure.
- Theme observation from the PHP shell.

Permissions returned by an API may control presentation, but they never replace
server enforcement.

## Acceptance Criteria

A React module is ready for cutover only when:

- Its PHP fallback remains available behind a server-controlled flag.
- Characterization, API contract, permission, and smoke tests pass.
- Calendar reference regression checks pass.
- Light, dark, desktop, mobile, keyboard, reduced-motion, loading, empty, error,
  and permission states are verified.
- No global CSS or Tailwind leakage affects non-React pages.
- Rollback requires no database reversal unless that batch explicitly includes
  reviewed migrations.

The canonical token and component contracts are maintained in:

- `docs/tailwind-design-system-plan.md`
- `docs/design-token-map.md`
