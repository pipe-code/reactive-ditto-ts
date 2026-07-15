# Reactive Ditto TS — AI Agent Context

This file gives any AI assistant the full context needed to work effectively in this codebase.

---

## What This Theme Is

**Reactive Ditto TS** is a WordPress theme framework that replaces traditional PHP template rendering with a **React 18 + TypeScript SPA** served over custom WordPress REST API endpoints. PHP handles routing, data injection, and SSR scaffolding; React handles all UI rendering.

---

## Build Commands

```bash
npm run watch   # Development build with file watching
npm run dev     # One-off development build
npm run prod    # Production build (minified, tree-shaken)
```

Output: `dist/app.bundle.js`. No test runner configured.

---

## Rendering Pipeline

```
1. WordPress loads header.php
   └── Outputs <head> with resource hints, global JS variables, deferred GA

2. inc/wordpress_settings.php → ditto_scripts()
   ├── wp_add_inline_script('before') → window.__ROUTER_DATA__ (all routes pre-loaded)
   └── wp_add_inline_script('before') → window.__PAGE_DATA__ (current page ACF content)

3. footer.php
   ├── Sets query_var('components') with ACF flexible content for current page
   ├── Outputs #page-loader div (instant visual — painted before JS)
   ├── Outputs <div id="app"> containing <main id="ssr-content">
   ├── Calls ssr/dynamicZone.php → renders initial HTML at opacity:0 (SEO + slow-JS)
   └── wp_footer() enqueues dist/app.bundle.js

4. src/app.tsx mounts
   ├── Removes #page-loader synchronously
   ├── Reads window.__ROUTER_DATA__ (skips API call if present)
   └── Builds React Router routes from the router map

5. src/containers/Page/Page.tsx
   ├── Reads window.__PAGE_DATA__ on first load (skips API call if present)
   └── Falls back to GET /wp-json/page/{id} for subsequent navigations

6. src/UI/DynamicZone/DynamicZone.tsx
   └── Maps component.acf_fc_layout → lazy-loaded React component
```

---

## Key Global Variables (injected in header.php)

| Variable | Type | Description |
|---|---|---|
| `window._dittoURI_` | string | Theme directory URI (for asset paths) |
| `window._dittoURL_` | string | WordPress site URL (axios base URL) |
| `window._recaptchaSiteKey_` | string | reCAPTCHA v3 public site key |
| `window.__ROUTER_DATA__` | RouterProps | All published pages/posts (injected before bundle) |
| `window.__PAGE_DATA__` | PageProps | Current page ACF content (injected before bundle) |

---

## Custom REST Endpoints (inc/endpoints.php)

All GET endpoints return `Cache-Control: public, max-age=300` headers.

| Endpoint | Method | Returns |
|---|---|---|
| `/wp-json/router/pages` | GET | All published pages + news posts for client-side routing |
| `/wp-json/page/{id}` | GET | ACF flexible content for a specific page |
| `/wp-json/navigation/main_menu` | GET | Main nav menu items + logo |
| `/wp-json/navigation/footer` | GET | Footer ACF options content |
| `/wp-json/recaptcha/v1/verify` | POST | Verifies a reCAPTCHA v3 token server-side |
| `/wp-json/proxy/v1/submission` | POST | Proxies a form submission to a third-party API |

---

## ACF Flexible Content — The Core Pattern

Page content is stored in an ACF flexible content field named **`ditto_components`** on each page.

The REST API endpoint `GET /wp-json/page/{id}` returns the field as an array:

```json
{
  "have_post": true,
  "content": [
    { "acf_fc_layout": "MyComponent", "field_one": "...", "field_two": "..." },
    { "acf_fc_layout": "AnotherComponent", "..." }
  ]
}
```

`DynamicZone.tsx` maps `acf_fc_layout` → lazy-loaded React component.

### Adding a New ACF Component

1. Create `src/components/MyComponent/MyComponent.tsx` and `MyComponent.module.scss`
2. Register it in `lazyComponents` in `src/UI/DynamicZone/DynamicZone.tsx`:
   ```tsx
   MyComponent: lazy(() => import('@components/MyComponent/MyComponent')),
   ```
3. The key **must** match the `acf_fc_layout` string set in ACF
4. Add a matching PHP SSR template at `ssr/components/MyComponent.php` — required, not optional, for any component with real text/business content. See CLAUDE.md → "Why `ssr/fixed/Header.php` and `ssr/fixed/Footer.php` are not optional" for why this stopped being a nice-to-have.

---

## SSR Strategy (ssr/ directory)

The SSR layer renders initial HTML **before JavaScript loads**, for two purposes:
1. **SEO** — search engines see content in the HTML source
2. **Perceived performance** — content is visible if JS is slow

### How It Works

- `footer.php` fetches ACF components and passes them via `set_query_var('components', $components)`
- `ssr/dynamicZone.php` loops over components and includes matching PHP templates from `ssr/components/`
- SSR output is wrapped in `#ssr-content` with `opacity: 0` (hidden visually, present for crawlers)
- React mounts on `#app`, replaces `#ssr-content` with its own DOM tree
- React uses `createRoot()` (not `hydrateRoot()`), so SSR HTML is fully replaced — **not hydrated**

### CSS Class Names and SSR

CSS modules use **deterministic class names** (no hash): `[name]__[local]`

This is configured in `webpack.config.js`:
```js
localIdentName: "[name]__[local]"
```

This means PHP SSR templates can safely hardcode class names like `Contact__wrapper` and they will always match what React generates.

---

## reCAPTCHA v3 Integration

### Setup

1. Go to **Theme Settings → reCAPTCHA** in the WordPress admin
2. Enter your **Site Key** (public) and **Secret Key** (private) from Google reCAPTCHA console
3. The site key is exposed to the frontend via `window._recaptchaSiteKey_` (injected in `header.php`)

### Client-Side Flow

Use the ready-made helper `src/utils/recaptchaVerify.tsx` — call `await recaptchaVerify('submit')` in a form's submit handler and only proceed if it returns `true`. It encapsulates the full flow:

```
1. Lazy-load the reCAPTCHA script the first time it's needed (never on page load)
2. grecaptcha.execute(siteKey, { action }) → token
3. POST token to /wp-json/recaptcha/v1/verify
4. Server verifies with Google and returns { success: true, score: 0.9 }
5. Returns true only if success && score >= 0.5 (threshold constant in the file)
```

The site key comes from `window._recaptchaSiteKey_`; the helper warns and returns `false` if it's unset, so forms fail closed rather than sending unverified.

### Server-Side (inc/recaptcha.php)

- Registers the ACF options sub-page for key management
- Registers the `POST /wp-json/recaptcha/v1/verify` endpoint
- The secret key is never exposed to the browser — only used server-side

---

## Contact Form 7 Integration

`src/utils/contactFormRequest.tsx` sends form data to CF7's REST API:

```
POST /wp-json/contact-form-7/v1/contact-forms/{id}/feedback
```

The CF7 frontend scripts are intentionally dequeued in `inc/wordpress_settings.php` (the React form handles everything).

---

## Client Utilities (src/utils/)

Small, dependency-free helpers reused across projects — prefer these over re-implementing:

| Helper | Use |
|---|---|
| `recaptchaVerify(action)` | Full reCAPTCHA v3 execute-and-verify; returns `Promise<boolean>`. See the reCAPTCHA section. |
| `smoothScrollTo(targetY, duration?)` | Eased (`easeInOutCubic`) programmatic scroll. Use for long, deliberate jumps (e.g. a header CTA scrolling to the footer form) where native `scroll-behavior: smooth` is too fast/linear. Default duration 1600ms. |
| `htmlentities(str)` | (in `functions.tsx`) decode entities before setting `document.title`. |

When you add a helper that a future project would want, put it here as a single-purpose default export and document it in this table.

---

## Internationalization (WPM)

WPM (WordPress Multilingual) support is configured in `wpm-config.json`. The `lang` field is included on every route returned by `router/pages`. `Page.tsx` passes `lang` to its API call, and the `languageAtom` (Recoil) tracks the current language globally.

---

## State Management (Recoil)

Two atoms in `src/utils/recoilStates.tsx`:

| Atom | Default | Purpose |
|---|---|---|
| `siteLoadedAtom` | `{ assets: false, header: true, footer: false, components: false }` | Tracks loading phases; `header: true` because the header is SSR-rendered immediately |
| `languageAtom` | `'en'` | Current language code for i18n routing |

---

## Path Aliases

Configured in both `webpack.config.js` and `tsconfig.json`:

| Alias | Resolves To |
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

---

## Directory Conventions

```
src/
  components/   Page-level ACF components (matched by acf_fc_layout)
  UI/           Reusable UI elements (DynamicZone, Loader, Layout, etc.)
  containers/   Route-level containers (Page, Index, Error404)
  hooks/        Custom React hooks (e.g. useWindowWidth)
  utils/        Axios instance, Recoil atoms, API request helpers
  interface/    TypeScript type definitions
  styles/       Global SCSS (app.scss, _vars.scss, _grid.scss, _fonts.scss)
  assets/       Static assets (SVGs, images)
  fonts/        Self-hosted font files (.woff, .woff2)

inc/
  wordpress_settings.php   Enqueue scripts, login styles, ACF options, data pre-loading
  endpoints.php            REST API endpoint registration and handlers
  post-types.php           Custom post type registration (project, news)
  taxonomies.php           Custom taxonomy registration (press_taxonomy)
  recaptcha.php            reCAPTCHA v3 settings page + verify endpoint

ssr/
  dynamicZone.php          Loops over ACF components and includes PHP templates
  components/              PHP mirrors of React components for initial server render

acf-json/                  ACF field group JSON sync files (auto-generated by ACF)
dist/                      Compiled JS/CSS bundles (never edit manually)
```

---

## Custom Post Types

`inc/post-types.php` contains a commented-out example showing how to register a custom post type. Uncomment and adapt it for each project. After registering a post type that needs client-side routes, add its slug to the `post_type` array in `router_pages_handler()` inside `inc/endpoints.php`.

---

## Taxonomies

`inc/taxonomies.php` contains a commented-out example showing how to register a custom taxonomy. Uncomment and adapt it as needed.

---

## Styling System

- Components use **SCSS modules** (`*.module.scss`)
- Global styles in `src/styles/app.scss`
- `_vars.scss` contains: color tokens, font variables, breakpoints, `scaleVW()`, `fluid()`, `media()` mixin, `LH()` function, `image-cover` mixin, `hover` mixin
- Viewport scaling: `scaleVW($px, $viewportWidth)` converts pixel sizes to `vw` units at a reference width
- `fluid($size)` scales from the 1440 design, clamped between 1024–1680px — use for all desktop styles
- Reference widths: `$desk: 1440`, `$tablet: 768`, `$phone: 414`
- Breakpoints: `mobile`, `tablet-portrait`, `tablet-landscape`, `desktop`, `big-screen`
- `@include hover { ... }` — wraps `@media (hover: hover) and (pointer: fine)` — always use for `:hover` styles to prevent stuck states on touch devices
- `scroll-behavior: smooth` is set on `html` in `app.scss` — all `<a href="#anchor">` links scroll smoothly

---

## Performance Patterns

1. **PHP data pre-loading** — `window.__ROUTER_DATA__` and `window.__PAGE_DATA__` eliminate API round-trips on first paint
2. **Instant loader** — `#page-loader` div gives immediate visual feedback before JS executes
3. **Deferred analytics** — Google Analytics loads on `window.load` (never blocks rendering)
4. **Resource hints** — `preconnect` and `dns-prefetch` for Google services in `<head>`
5. **REST API caching** — `Cache-Control: public, max-age=300` on all GET endpoints
6. **Code splitting** — all ACF components are lazily imported via `React.lazy()`
7. **Deterministic CSS** — no hash in class names; PHP SSR templates can hardcode them safely

### Cache busting — two independent layers (get this right or "my change didn't work")

The single most common false bug on this stack is a **stale asset in the browser or CDN**: the code is correct, the build ran, but the visitor is served an old file. Two layers prevent it, and they cover different files:

- **Main bundle** (`dist/app.bundle.js`) — enqueued in `ditto_scripts()` with `filemtime()` as the `?ver=` query arg, so every rebuild changes the URL. Never hard-code a version here; `wp_get_theme()->get('Version')` only changes when you bump `style.css`, which you will forget to do.
- **Lazy chunks** (one per `React.lazy()` component) — `webpack.config.js` sets `chunkFilename: '[name].[contenthash:8].app.bundle.js'` so a changed chunk gets a new filename, and `output.clean: true` wipes `dist/` each build so old hashes don't pile up.

Rule of thumb when debugging "the change isn't showing": rebuild (`npm run prod`), then confirm the served filename/`?ver=` actually changed before assuming the code is wrong. A chunk **cannot** update without a rebuild — watch mode does rebuild, but a one-off `npm run dev`/`prod` is required after pulling.

### Scroll ownership

`src/app.tsx` sets `window.history.scrollRestoration = 'manual'`. The SPA owns scroll positioning (`Page.tsx` resets scroll on navigation); leaving it on the browser's default `auto` leaves the page half-scrolled into the previous view when the user hits back/forward. Any component that scrolls programmatically (e.g. a header CTA jumping to the footer) should assume it is the only thing moving the viewport.

---

## WordPress Plugin Dependencies

| Plugin | Required | Purpose |
|---|---|---|
| ACF Pro | Yes | All page content uses flexible content fields |
| Contact Form 7 | Optional | Form submissions via REST API |
| Yoast SEO | Optional | Page titles are read from `_yoast_wpseo_title` meta |
| WPM (WordPress Multilingual) | Optional | `wpm-config.json` controls translation scope |

---

## SSR Fixed Components

Fixed components (Header, Footer, CPT-specific) live in `ssr/fixed/`. `footer.php` orchestrates render order:

```php
get_template_part('ssr/fixed/Header');
// conditional CPT components:
if ( is_singular('projects') ) get_template_part('ssr/fixed/ProjectInfo');
get_template_part('ssr/dynamicZone');
if ( is_singular('projects') ) get_template_part('ssr/fixed/ProjectNav');
get_template_part('ssr/fixed/Footer');
```

Fixed component SSR rules:
- Guard CPT-specific templates with `if ( ! is_singular('cpt-slug') ) return;`
- **Header**: read nav with `wp_get_nav_menu_items($menu_locations['main_menu'])`. For custom link menu items (type `custom`, `object_id = 0`), use `wp_make_link_relative($item->url)` — plain `get_post_field('post_name', 0)` returns empty string causing `path = '/'`.
- **Footer**: read fields with `get_field('field_name', 'options')`. Skip the newsletter form (no SEO value).
- These two are **required on every project**, not starter boilerplate to skip — see CLAUDE.md for the incident that made this a hard rule. `ditto_organization_schema()` (`inc/wordpress_settings.php`) adds a JSON-LD identity block alongside them; keep it wired in `header.php`.

---

## Custom Post Types — Key Patterns

**No-internal-page CPT** (e.g. News, links out to external URLs):
- Register with `'public' => false, 'publicly_queryable' => false, 'show_ui' => true`
- Custom REST endpoint returns `{ items: [...], total: N }` for load-more pagination

**With-internal-page CPT** (e.g. Projects):
- Register with `'public' => true, 'publicly_queryable' => true, 'rewrite' => ['slug' => 'cpt-slug']`
- Add `'page-attributes'` to `supports` for `menu_order` control
- Wire routing in 3 places: `router_pages_handler()`, `src/app.tsx`, new detail container
- Add `has_internal_page` ACF `true_false` field so editors can mark non-linkable items
- After registration: `wp rewrite flush`
- Add CPT post type to `location` array in page builder ACF JSON to allow `ditto_components` on CPT posts
- Use `$post->post_title` (not `get_the_title()`) to avoid HTML entity encoding in REST responses
- Domain label from URL: `new URL(value).hostname.replace(/^www\./, '')`

---

## Common Tasks

### 404 vs redirect — multi-page site vs single-page landing

The wildcard route at the bottom of `src/app.tsx` renders `<Error404 />` by default, which is correct for **multi-page sites** (blog, corporate site, etc.) where unknown URLs should show a proper 404 page.

For **single-page landing sites** (one-pager where every URL should resolve to the homepage), replace it with a redirect:

```tsx
// Remove Error404 import and swap the wildcard route:
import { BrowserRouter, Route, Routes, Navigate } from 'react-router-dom';
// ...
<Route path="*" element={<Navigate to="/" />} />
```

The comment in `app.tsx` marks exactly where to make this change.

---

### Change the ACF flexible content field name
The field is named `ditto_components`. To rename it:
1. Update `inc/wordpress_settings.php` → `ditto_scripts()` (the `get_field()` call)
2. Update `inc/endpoints.php` → `page_handler()` (the `get_field()` call)
3. Update `footer.php` (the `get_field()` call)
4. Rename the field in ACF and update `acf-json/` sync files

### Add Google Analytics
In `header.php`, set the `gaId` variable to your GA4 Measurement ID (e.g. `'G-XXXXXXXXXX'`).

### Configure the form proxy endpoint
Define the PHP constant in `wp-config.php`: `define( 'PROXY_SUBMISSION_URL', 'https://…' );`. Note `.env` is webpack-only (dotenv at build time) — PHP never reads it, so putting the URL there does nothing. `proxy_submission_handler()` in `inc/endpoints.php` returns a 500 until the constant is defined.

### Add a new language
1. Install and configure WPM plugin
2. Update `wpm-config.json` for your post types
3. The `lang` field on each route drives `languageAtom` in Recoil
4. Pass `lang` as a query param when fetching `GET /wp-json/page/{id}`
