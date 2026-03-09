# Reactive Ditto TS

**Reactive Ditto** is the agency base WordPress theme. It replaces traditional PHP templates with a **React 18 + TypeScript SPA** that fetches all content through custom WordPress REST API endpoints. PHP handles routing, data injection, and SSR scaffolding; React owns all UI rendering.

Use this as the starting point for every new project. Fork or copy the theme, rename it, and follow the setup checklist below.

---

## Quick Start

```bash
npm install
npm run watch   # development build with file watching
npm run dev     # one-off development build
npm run prod    # production build (minified, tree-shaken)
```

Output: `dist/app.bundle.js`

---

## New Project Checklist

When starting a project from this base, go through these steps in order:

### 1. Rename the theme
- Update `style.css` → `Theme Name`, `Description`, `Version`
- Rename the theme folder

### 2. Set the ACF field name
All page content lives in an ACF flexible content field. The default name is `ditto_components`. Rename it to something project-specific (e.g. `myproject_components`) and update it in three places:
- `inc/wordpress_settings.php` → `ditto_scripts()` (the `get_field()` call)
- `inc/endpoints.php` → `page_handler()` (the `get_field()` call)
- `footer.php` (the `get_field()` call)

### 3. Configure Google Analytics
In `header.php`, set the `gaId` variable to your GA4 Measurement ID (e.g. `'G-XXXXXXXXXX'`).

### 4. Configure reCAPTCHA (if using forms)
- Go to **Theme Settings → reCAPTCHA** in the WordPress admin
- Enter your Site Key (public) and Secret Key (private)
- The site key is automatically exposed to the frontend via `window._recaptchaSiteKey_`

### 5. Configure the form proxy (if using a third-party form API)
In your `.env` file, set `PROXY_SUBMISSION_URL` to the target endpoint. See `.env.example`.

### 6. Set up fonts
Place `.woff` / `.woff2` files in `src/fonts/` and declare them in `src/styles/_fonts.scss`.

### 7. Update design tokens
Set project colors, fonts, and breakpoints in `src/styles/_vars.scss`.

### 8. 404 vs. redirect
- **Multi-page site** — keep the default `<Error404 />` wildcard route in `src/app.tsx`
- **Single-page landing** — swap it for `<Navigate to="/" />` (the comment in `app.tsx` marks the exact line)

### 9. Register custom post types / taxonomies
Uncomment and adapt the examples in `inc/post-types.php` and `inc/taxonomies.php`. If a post type needs client-side routes, add it to the `post_type` array in `router_pages_handler()` in `inc/endpoints.php`.

---

## Architecture

```
PHP (WordPress)
  header.php          <head>, resource hints, global JS vars, deferred GA
  footer.php          #page-loader, #app container, SSR scaffold
  inc/                WordPress config, endpoints, post types, reCAPTCHA
  ssr/                PHP mirrors of React components (SEO initial render)

React (SPA)
  src/app.tsx         Router bootstrap — reads window.__ROUTER_DATA__
  src/UI/             Reusable UI primitives (DynamicZone, Loader, Layout…)
  src/components/     ACF-driven page components (one per acf_fc_layout)
  src/containers/     Route containers (Page, Index, Error404)
  src/hooks/          Custom React hooks
  src/utils/          Axios instance, Recoil atoms, API helpers
  src/interface/      TypeScript type definitions
  src/styles/         Global SCSS (vars, fonts, grid, app)
```

For a full explanation of every layer — data flow, SSR strategy, reCAPTCHA, i18n, performance patterns — see **[`.agent/CONTEXT.md`](.agent/CONTEXT.md)**.

---

## Adding an ACF Component

1. Create `src/components/MyComponent/MyComponent.tsx` and `MyComponent.module.scss`
2. Register it in `src/UI/DynamicZone/DynamicZone.tsx`:
   ```tsx
   MyComponent: lazy(() => import('@components/MyComponent/MyComponent')),
   ```
   The key must match the `acf_fc_layout` string set in ACF exactly.
3. Optionally add `ssr/components/MyComponent.php` for the initial server render (see `ssr/components/_example.php`)

---

## REST Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `/wp-json/router/pages` | GET | All published pages for client-side routing |
| `/wp-json/page/{id}` | GET | ACF flexible content for a specific page |
| `/wp-json/navigation/main_menu` | GET | Main nav items + logo |
| `/wp-json/navigation/footer` | GET | Footer ACF options |
| `/wp-json/recaptcha/v1/verify` | POST | Server-side reCAPTCHA v3 token verification |
| `/wp-json/proxy/v1/submission` | POST | Third-party form proxy |

All GET endpoints return `Cache-Control: public, max-age=300`.

---

## WordPress Plugin Dependencies

| Plugin | Required | Purpose |
|---|---|---|
| ACF Pro | Yes | All page content uses flexible content fields |
| Contact Form 7 | Optional | Form submissions via `contactFormRequest` helper |
| Yoast SEO | Optional | Page titles read from `_yoast_wpseo_title` meta |
| WPM | Optional | Multilingual support via `wpm-config.json` |

---

## Path Aliases

| Alias | Path |
|---|---|
| `@components` | `src/components/` |
| `@containers` | `src/containers/` |
| `@ui` | `src/UI/` |
| `@hooks` | `src/hooks/` |
| `@utils` | `src/utils/` |
| `@interface` | `src/interface/` |
| `@styles` | `src/styles/` |
| `@assets` | `src/assets/` |
| `@fonts` | `src/fonts/` |
