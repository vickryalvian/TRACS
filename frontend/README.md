# TRACS Frontend Foundation

This package is the isolated Phase 4 foundation for future TRACS React modules.
It is not loaded by current PHP pages and does not replace the existing
Calendar pilot build.

## Local validation

From `frontend/`:

```bash
npm install
npm run build
npm run dev
```

`npm run dev` serves the sandbox on the local Vite URL. `npm run build` writes
ignored validation output to `frontend/dist/`; it does not write into the
production web root.

Contract validation:

```bash
npm run test:contracts
```

## Safety boundaries

- `src/modules/_sandbox/` contains demonstration data and has no TRACS API
  dependency.
- `src/styles/tracs-tailwind.css` imports Tailwind theme and utilities without
  Preflight and uses the `tr:` prefix.
- Current PHP pages do not load this package.
- Future approved module entries will be added as named Vite inputs.
- Future production assets are planned for `public/assets/react-dist/` and must
  be loaded through an allowlisted PHP manifest helper.
- PHP remains authoritative for authentication, permissions, CSRF validation,
  request validation, business rules, audit logs, and database access.

Tailwind v4 is configured through `src/styles/tokens.css` and
`src/styles/tracs-tailwind.css`. A JavaScript `tailwind.config.js` or
`postcss.config.js` is intentionally absent because the approved CSS-first
configuration and `@tailwindcss/vite` plugin do not require them.

## Shift Assignment read-only entry

Phase 8 adds the named `shiftAssignment` Vite entry under
`src/modules/shift-assignment/`. It consumes only:

- `GET /api/v1/context.php`
- `GET /api/v1/shift-assignment/context.php`
- `GET /api/v1/shift-assignment/assignments.php`

No PHP page or navigation item loads this entry yet. Local Vite output alone
does not provide an authenticated TRACS session, so authenticated browser
preview remains deferred until an approved PHP pilot mount is added.

## Authenticated preview build

Phase 9 adds a dedicated build:

```bash
npm run build:preview
```

It writes only the `shiftAssignment` entry and manifest to
`public/assets/react-dist/`. The unlinked authenticated preview page resolves
that manifest at `/shift-assignment-react-preview.php`. The regular
`npm run build` output remains isolated under ignored `frontend/dist/`.

Validate the Phase 12 production-candidate budget after building:

```bash
npm run test:preview-bundle
```

The check requires exactly one `shiftAssignment` entry and caps uncompressed
preview output at 300 KB JavaScript and 50 KB CSS.
