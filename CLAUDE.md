# CLAUDE.md

This file is loaded automatically by Claude Code when working in this repository.

## Build Commands

- `npm run watch` — Development build with file watching (webpack dev mode)
- `npm run dev` — One-off development build
- `npm run prod` — Production build (minified, tree-shaken)

Output goes to `dist/app.bundle.js`. No test runner is configured.

## Full Architecture Context

All the detail an AI needs to work effectively in this codebase lives in **`.agent/CONTEXT.md`**: rendering pipeline, REST endpoints, ACF component pattern, SSR strategy, reCAPTCHA flow, Recoil state, performance patterns, i18n, and common tasks.

Read it before making non-trivial changes.

## Key Conventions

- Page content is stored in an ACF flexible content field named **`ditto_components`** — update this name in `inc/wordpress_settings.php`, `inc/endpoints.php`, and `footer.php` when starting a new project
- `acf_fc_layout` names in ACF must match keys in the `lazyComponents` map in `src/UI/DynamicZone/DynamicZone.tsx` exactly
- CSS module class names are deterministic (`[name]__[local]`, no hash) so PHP SSR templates can reference them safely
- The wildcard route in `src/app.tsx` renders `<Error404 />` by default; swap for `<Navigate to="/" />` on single-page landing sites
- All new ACF components should have an optional PHP mirror at `ssr/components/ComponentName.php` — see `ssr/components/_example.php` for the pattern
