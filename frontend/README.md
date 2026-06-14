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
