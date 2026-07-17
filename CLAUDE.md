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

## Working standard — how to hit production quality

These are the habits that separate a "looks about right" build from a client-ready one. They cost minutes and save review rounds. Apply them by default.

1. **Verify live, never assume.** After `npm run prod`, actually load the page and *measure* — read computed styles and element rects (`getBoundingClientRect`, `getComputedStyle`) in the browser/devtools, don't eyeball a screenshot and declare victory. "Footer is one viewport tall" should be confirmed as `height === innerHeight - headerHeight`, not guessed. When you fix a reported bug, reproduce it first, then prove the fix with the same measurement.
2. **Confirm the asset actually changed.** Before concluding "the code is wrong," verify the browser is serving the new bundle/chunk (check the `?ver=` and chunk filename — see the cache-busting notes in `.agent/CONTEXT.md`). A stale asset is the #1 false bug on this stack.
3. **Pull exact values from Figma — never guess.** Every size, weight, line-height, tracking, color, and copy string comes from the design via the Figma MCP tools (`get_design_context`, `get_metadata`, `get_screenshot`), not from a similar-looking component or memory. Load copy through ACF, never hard-code it.
4. **Figma spacing is ink-to-ink.** Figma measures gaps from cap-height to baseline (text-box-trim); the browser adds line-box leading on top. When a vertical gap looks larger in the browser than the Figma number, the fix is usually a small negative/reduced margin on the text element — reason about the leading rather than blindly matching the px value.
5. **Report faithfully.** If something is blocked (missing credentials, a server-side dependency), say so and why — don't paper over it. If a test/verification was skipped, state it. "Done" means verified.

Scope discipline: build only what the current release needs (check the project's first-release scope), keep unfinished work off `main`, and confirm before anything hard to reverse or outward-facing (form submissions to live CRMs, deploys, deletes).

## Styling Conventions

- **Never use raw `px` values** in SCSS — always use `fluid($size)` defined in `src/styles/_vars.scss`. It produces `clamp(size@1024, scaleVW(size, 1440), size@1680)`.
- **Column percentages** must be calculated against the **inner content width** (container width minus horizontal padding), not the full viewport. Example: a 50px-padded section at 1440px has 1340px inner width, so a 459px column = `459/1340 = 34.254%`.
- **Element max-widths** inside columns must reflect the Figma values (e.g. form row `max-width: fluid(532)`, description `max-width: fluid(533)`), not the column width.
- `display: table` on inline text elements (e.g. email links) prevents them from stretching to full column width.

## Responsive Strategy

Desktop styles use `fluid()` (clamps between 1024–1680px). Mobile/tablet use `scaleVW(value, $phone)` where `$phone = 414` — the Figma mobile design viewport.

**Breakpoints** (defined in `src/styles/_vars.scss`):
- `tablet-portrait` — max-width 991px + iPad portrait. Use this as the single breakpoint for both tablet and mobile when the design is the same at both sizes.
- `mobile` — max-width 767px only. Use when mobile needs different values than tablet-portrait.

**Pattern:**
```scss
.Element {
    // Desktop — fluid()
    font-size: fluid(24);
    padding: fluid(80) fluid(50);

    // Tablet + mobile (same design) — one breakpoint, scaleVW with $phone
    @include media(tablet-portrait) {
        font-size: scaleVW(18, $phone);
        padding: scaleVW(40, $phone) scaleVW(20, $phone);
    }

    // Mobile only override — when it differs from tablet
    @include media(mobile) {
        padding-top: scaleVW(60, $phone);
    }
}
```

**Key rules:**
- **Always include 20px horizontal padding on mobile/tablet.** Every component section uses `padding: [top] scaleVW(20, $tablet/phone) [bottom]` on responsive breakpoints — never omit it. This matches the consistent 20px side margin across the entire site.
- **`position: absolute` ignores container padding.** `left: 0` on an absolutely positioned child measures from the container's border edge, not the content area. For components with absolute children that need 20px margins, apply the offset directly to each child: `left: scaleVW(20)` for left-aligned elements, and add 20 to any Figma x-coordinates that were measured from the padded content area. For full-width elements use `width: calc(100% - #{scaleVW(40, $phone)})` with `left: scaleVW(20, $phone)`.
- Always read Figma mobile values directly — never guess or scale from desktop proportionally.
- **Two independent breakpoints** — `tablet-portrait` uses same px values as desktop with `scaleVW(X, $tablet)`. `mobile` uses Figma mobile values with `scaleVW(X, $phone)`. Breakpoints do NOT cascade.
- **Figma frame narrower than canvas** — if a mobile frame is narrower than 414px, the side margins are already baked into the design. Use 20px CSS padding on the container and do not add extra offsets.
- Elements hidden on mobile use `display: none` inside `@include media(tablet-portrait)`.
- Heights that are `100dvh` on desktop often become a fixed `scaleVW(Xpx, $phone)` on mobile — check Figma for the exact frame height.
- `align-items` on flex containers often needs to change on mobile (e.g. `center` → `flex-start`) when vertical position is controlled via `padding-top` instead of centering.
- **Column reordering**: use CSS `order` to visually reorder flex children on mobile without changing the HTML.
- **`fluid()` on mobile**: `fluid()` uses a clamp that bottoms out at 1024px — on mobile it produces the minimum value (e.g. `fluid(17)` → ~12px). Always override interactive text (buttons, labels, links) with `scaleVW()` in responsive breakpoints even when keeping the same visual padding/border-radius.
- **Full-width bleed inside padded container**: when a section (e.g. an image) needs to be edge-to-edge while siblings have side padding, put the padding on the container and use negative margin + extra width on the bleed element:
```scss
@include media(tablet-portrait) {
    .Container { padding: 0 scaleVW(20, $tablet); }
    .FullWidthChild {
        margin-left: scaleVW(-20, $tablet);
        margin-right: scaleVW(-20, $tablet);
        width: calc(100% + #{scaleVW(40, $tablet)});
    }
}
```
- **Absolute-child overlap in column layout**: when a `position: absolute` error message sits below an input and the form switches to `flex-direction: column`, the next sibling overlaps the error. Fix with `:has()` — no JS needed:
```scss
@include media(tablet-portrait) {
    .InputWrapper:has(.Input[data-error='true']) { margin-bottom: scaleVW(20, $tablet); }
}
```

## Hover States

**Always wrap hover rules in `@include hover`** to prevent stuck hover states on touch devices:

```scss
@include hover {
    .Card:hover .ArrowSvg {
        rect { fill: $green; stroke: $green; }
        path { stroke: #fff; }
    }
}
```

The `@mixin hover` is defined in `_vars.scss` as `@media (hover: hover) and (pointer: fine)`. Never write bare `:hover` selectors at the top level — always wrap them.

## Footer Architecture

- `navigation/footer` endpoint returns all fields individually from ACF options (not via a group field wrapper). Add fields in `inc/endpoints.php` → `footer_handler()` and mirror them in `src/interface/footer.tsx`.
- ACF field groups for options pages live in `acf-json/` as JSON files. Location rule: `{ "param": "options_page", "operator": "==", "value": "acf-options-{slug}" }`.
- Newsletter form submits to CF7 via `contact-form-7/v1/contact-forms/{id}/feedback` using `contactFormRequest` utility. The form ID comes from the `newsletter_form_id` ACF field.
- Static SVG assets (e.g. logos) belong in `src/assets/` and are imported as typed modules (see `src/declarations.d.ts`). Use them as fallbacks when no CMS image is uploaded.

## ACF Field Conventions

- **Dynamic component layouts** are registered alphabetically in both the page builder JSON (`acf-json/group_ditto_page_builder.json` — renamed per project) and `src/UI/DynamicZone/DynamicZone.tsx`.
- **File fields** that accept images or videos must set `"mime_types": "jpg,jpeg,png,gif,svg,webp,mp4,webm"` and `"return_format": "array"` so the REST API returns the full object (`url`, `mime_type`, `alt`). Render them by branching on `mime_type`: a looping muted `<video autoPlay muted playsInline loop preload="metadata">` for `mime_type.includes('video')`, otherwise `<img loading="lazy" decoding="async">`. This lets editors swap any image for a video with zero code change — prefer a single `file` field over separate image/video fields. Where a mobile crop differs, add an optional `*_mobile` file field that falls back to the desktop one.
- **CTA fields** use ACF `link` type (`"return_format": "array"`) — returns `{ url, title, target }`. Never use `url` type for CTAs since it rejects hash anchors (`#section-id`).
- **Stats / fixed-count sub-fields**: prefer flat named fields (`stat_1_value`, `stat_1_label`, …) over a repeater when the design has a fixed number of items.

## ACF Textarea with HTML

For fields that need line breaks (but not full rich text), use `"new_lines": "br"` on the textarea field in the ACF JSON. ACF will convert `\n` to `<br>` before returning the value via REST. Render in React with `dangerouslySetInnerHTML={{ __html: content.field }}`. Safe for CMS-sourced content; avoid for any user-facing input.

To allow inline HTML (e.g. `<strong>` for highlights) in a textarea field:
- Set `"new_lines": ""` — no automatic line-break conversion
- Add an instruction note for editors, but **escape angle brackets** as `&lt;` / `&gt;` — ACF renders `instructions` as HTML, so raw tags get interpreted and the text disappears
- In React, render with `dangerouslySetInnerHTML={{ __html: content.field }}` and use a `<div>` container instead of `<p>` (a `<p>` cannot contain block-level children)
- Style inline highlights via a nested `strong` rule in the SCSS module

## Smooth Anchor Scroll

`scroll-behavior: smooth` is set globally on `html` in `src/styles/app.scss`. All `<a href="#anchor">` links animate smoothly without any JS. Target sections must have a matching `id` attribute.

For cross-page anchor navigation (e.g. `/#about` from another page), `scroll-behavior` alone is not enough — React Router renders the new page without processing the hash. The Layout component (`src/UI/Layout/Layout.tsx`) should have a `useLocation` effect that polls for the target element every 100ms (up to 2s) and calls `scrollIntoView({ behavior: 'smooth' })` once it appears.

## WordPress Nav Menu — Custom Links

In `inc/endpoints.php → main_menu_handler()`, the `path` is built from `get_post_field('post_name', $item->object_id)`. This breaks for custom link menu items (type `custom`, `object_id = 0`) — they return an empty slug and `path` becomes `/`.

Fix: detect custom links and use `wp_make_link_relative()` on `$item->url`:
```php
if ( $item->type === 'custom' ) {
    $path = wp_make_link_relative( $item->url );
} else {
    $path = ($frontpage_id == $item->object_id) ? '/' : '/'.get_post_field('post_name', $item->object_id);
}
```
This correctly handles hash anchors (`/#about`), external links, and any other custom URL.

## Flex Column Containers

Always add `align-items: flex-start` to `flex-direction: column` wrappers that contain inline/auto-width children (buttons, tags, links). Without it, flex children stretch to 100% width.

## Global CSS inside SCSS Modules

To target third-party classes (e.g. react-slick) inside a CSS module, wrap the entire block in `:global { }` — not `:global(.className)`. Using `:global(.className)` only makes that one selector global; nested selectors are still scoped by CSS Modules and won't match.

```scss
.SliderWrap {
    :global {
        .slick-dots { ... }
        .slick-dots li.slick-active button { ... }
    }
}
```

## Figma Width Values

**Always apply `w-[Xpx]` values from Figma** — including on text elements (`<p>`, `<span>`), not only on containers. Use `max-width: fluid(X)` so the element scales correctly.

## Repeater Items — Auto-Numbered

When a repeater has sequential numbers in the design (1, 2, 3…), generate them from the array index (`i + 1`) in React instead of adding a CMS field.

## Reusable UI Patterns

### CTA Primary button (green pill)
```scss
.CTA {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: $green;
    border-radius: fluid(30);
    padding: fluid(12) fluid(20);
    text-decoration: none;

    span {
        font-family: $f1;
        font-weight: 600;
        font-size: fluid(17);
        line-height: 1.4;
        letter-spacing: -0.01em;
        color: #fff;
        white-space: nowrap;
    }
}
```
Render as `<a className={styles.CTA} href={link.url} target={link.target || undefined}><span>{link.title}</span></a>`.

**Responsive override (required in every component with a CTA):**
```scss
@include media(tablet-portrait) {
    .CTA {
        padding: scaleVW(12, $tablet) scaleVW(20, $tablet);
        border-radius: scaleVW(30, $tablet);
        span { font-size: scaleVW(17, $tablet); }
    }
}
@include media(mobile) {
    .CTA {
        padding: scaleVW(12, $phone) scaleVW(20, $phone);
        border-radius: scaleVW(30, $phone);
        span { font-size: scaleVW(17, $phone); }
    }
}
```
The `.CTA` base definition must always appear **before** the responsive breakpoints in the file.

## Modal / Popup Pattern

For components with a click-to-open popup:
- Render the overlay and modal inside the same component — `position: fixed` handles viewport coverage regardless of DOM position
- Use a **two-state close flow** to enable fade-out: `isClosing` triggers the CSS exit animation, a `setTimeout` (matching animation duration) then sets `selected = null` to unmount
- Lock body scroll with `document.body.style.overflow = 'hidden'` when open; clean up in the `useEffect` return
- Close on: X button, overlay click, `Escape` key (useEffect with keydown listener)

```tsx
const close = useCallback(() => {
    setIsClosing(true);
    setTimeout(() => { setSelected(null); setIsClosing(false); }, 200);
}, []);
```

```scss
@keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
@keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

.Overlay, .Modal {
    animation: fadeIn 0.2s ease forwards;
    &.closing { animation: fadeOut 0.2s ease forwards; }
}
```

## SVG Icons — Hover State with rect + path

When an SVG contains a `<rect>` (circle border) and a `<path>` (icon), `currentColor` alone cannot independently fill the rect and turn the path white on hover. Target each element separately:

```scss
.ArrowSvg {
    rect { transition: fill 0.2s ease; }
    path { transition: stroke 0.2s ease; }
}
@include hover {
    .Card:hover .ArrowSvg {
        rect { fill: $green; stroke: $green; }
        path { stroke: #fff; }
    }
}
```

## Custom Post Types — No-Internal-Page Pattern

For content types that link out exclusively to external URLs (e.g. News), register the CPT with `'public' => false, 'publicly_queryable' => false, 'show_ui' => true`. Serve the data via a custom REST endpoint that returns `{ items: [...], total: N }`.

## Custom Post Types — With Internal Page Pattern

For CPTs that have individual detail pages (e.g. Projects), register with `'public' => true, 'publicly_queryable' => true` and a `'rewrite' => ['slug' => 'cpt-slug']`. Add `'page-attributes'` to `supports` so editors can control display order via `menu_order`.

**Routing wiring (3 places):**
1. `inc/endpoints.php` → `router_pages_handler()`: add the CPT slug to the `post_type` array.
2. `src/app.tsx`: add a new `if (item.post_type === "cpt-slug")` branch that renders the detail container.
3. Create `src/containers/CptDetail/CptDetail.tsx` — must call `setAssetsLoaded` so the page loader resolves.

**`has_internal_page` toggle**: add a `true_false` ACF field so editors can mark individual items as non-linkable. The grid component conditionally wraps the card in a `<NavLink>` or a plain `<div>` based on this flag.

After registering the CPT, run `wp rewrite flush` so the new slug URLs resolve.

## Dynamic List Components — Load More Pattern

```tsx
const load = async (offset: number) => {
    const res = await fetch(`/wp-json/content/news?per_page=${perPage}&offset=${offset}`);
    const data = await res.json();
    setItems(prev => offset === 0 ? data.items : [...prev, ...data.items]);
    setTotal(data.total);
};
const hasMore = items.length < total;
```

The "Load more" button is a `<button>` styled as a green-border pill with `border: 1px solid $green`. Always render the footer wrapper even when the button is hidden so bottom spacing is preserved.

## Navbar Height Reference

The fixed header height is `fluid(54)` — composed of `fluid(15)` padding top + `fluid(24)` logo + `fluid(15)` padding bottom. Use `margin-top: fluid(54)` on any first-section component that should not be obscured by the navbar.

## CPT Detail Page Pattern — Fixed Component + DynamicZone

1. **Separate REST endpoint** `GET /wp-json/content/cpt-slug/{id}` returns all CPT-specific fields plus `ditto_components`.
2. **ACF page builder location rules** — add a second entry to the `location` array in the page builder JSON so `ditto_components` appears on CPT posts in the admin.
3. **Fixed component** renders first (hero, heading, structured data), then `ditto_components` is mapped through `<DynamicZone>`.
4. **Domain label for project URLs** — extract hostname: `new URL(value).hostname.replace(/^www\./, '')`.

## Typography — Always Verify Against Figma

**Every font-size, font-weight, line-height, and letter-spacing value must be taken directly from the Figma design.** Never assume or copy from a similar component. Text elements that look visually similar can have different sizes.

## Gallery Slider Pattern

For a gallery with fixed height and variable-width slides:

- **Multi-item**: `.Slide { height: fluid(730); width: auto; flex-shrink: 0 }` + `.Media { height: 100%; width: auto; display: block }` — each slide adopts its media's natural width, no cropping.
- **Single item**: `.SlideSingle { width: 100%; position: relative }` + `.MediaCover { @include image-cover; position: absolute; inset: 0 }` — cover crop fills the container.
- **Translation**: use `target.offsetLeft` measured from the DOM to drive `trackRef.current.style.transform = translateX(-Npx)`. Re-apply on `window resize`.
- **Non-infinite arrows**: Prev hidden when `current === 0`, Next hidden when `current === count - 1`.

## react-slick Carousel Pattern

- **Always disable all pause behaviors**: `pauseOnHover: false`, `pauseOnFocus: false`, `pauseOnDotsHover: false`.
- **Variable-width slides**: use `variableWidth: true` + a `.Slide` wrapper with `padding-right: fluid(40); box-sizing: content-box`.
- **Manual prev/next buttons**: set `arrows: false` and control with `sliderRef.current?.slickPrev()` / `slickNext()`.
- **Responsive breakpoints**: do NOT use the `responsive` prop — known bug in this setup. Use the `useWindowWidth` hook (`src/hooks/windowWidth.tsx`) instead.
- **Slide gap in fixed-width mode**: use `padding: 0 halfGap` on the user-controlled `.Slide` div with `box-sizing: border-box` — NOT `padding-right` on `.slick-slide`.
- **Infinite on mobile only**: `infinite: isMobile`, `variableWidth: !isMobile` where `isMobile = !!windowWidth && windowWidth <= 991`.
- **Editable speed**: expose `autoplay_speed` as an ACF `number` field. Clamp in React: `Math.max(1000, content.autoplay_speed || 4000)`.

## WP-CLI — ACF Sync and Page Setup

When adding a new dynamic component, two WP-CLI steps are needed after updating the ACF JSON:

**1. Sync the field group to the database:**
```php
wp eval '
$json  = file_get_contents(get_template_directory() . "/acf-json/group_ditto_page_builder.json");
$group = json_decode($json, true);
acf_import_field_group($group);
'
```

**2. Add the component row to the target page** (use the field key, not the field name):
```php
wp eval '
$components = array(
    array("acf_fc_layout" => "my_component", "field_one" => false),
);
update_field("field_KEY_HERE", $components, $page_id);
'
```

## SSR Architecture

The theme outputs semantic HTML in `#ssr-content` (opacity 0) for SEO. React mounts with `createRoot()` and fully replaces the DOM — this is **not** hydration.

### File structure
```
ssr/
├── index.php                — directory listing protection (Silence is golden)
├── dynamicZone.php          — loops ditto_components, includes ssr/components/{layout}.php
├── components/              — one PHP file per acf_fc_layout (dynamic components)
│   ├── index.php            — directory listing protection
│   ├── _example.php         — copy this to add a new SSR component
│   └── ...
└── fixed/                   — always-present components (Header, Footer) + conditional (CPT pages)
    ├── index.php            — directory listing protection
    ├── Header.php           — SSR mirror of React Header; reads WP nav menu natively
    ├── Footer.php           — SSR mirror of React Footer; reads ACF options natively
    └── ...
```

`footer.php` orchestrates the render order inside `#ssr-content`:
```php
get_template_part('ssr/fixed/Header');
// conditional fixed components (e.g. CPT detail pages):
if ( is_singular('projects') ) get_template_part('ssr/fixed/ProjectInfo');
get_template_part('ssr/dynamicZone');
if ( is_singular('projects') ) get_template_part('ssr/fixed/ProjectNav');
get_template_part('ssr/fixed/Footer');
```

### CSS class names
Webpack uses `localIdentName: "[name]__[local]"` (no hash). Hardcode class names in PHP as `ComponentName__ClassName` — they are stable across rebuilds.

### Dynamic component SSR rules
- `$component` variable is available in scope (passed by `dynamicZone.php` via `include`)
- Use `esc_html()` for plain text, `wp_kses_post()` for fields that use `dangerouslySetInnerHTML` in React
- Skip `<video>` tags — render `<img>` only. Check: `! str_starts_with($mime, 'video')`
- For sliders/carousels: render items as a flat list, no carousel markup needed
- For components that fetch data at runtime: render a static shell with heading only

### Fixed component SSR rules
- **Header**: read nav with `wp_get_nav_menu_items($menu_locations['main_menu'])`. Use the same custom link detection logic as `main_menu_handler()`.
- **Footer**: read all fields with `get_field('field_name', 'options')`. Omit the newsletter form.
- **CPT detail components**: guard with `if ( ! is_singular('cpt-slug') ) return;`.
- **`ditto_organization_schema()`** (`inc/wordpress_settings.php`, called from `header.php` right after `wp_head()`) emits an `Organization` JSON-LD block — name, logo, phone, email, address, `sameAs` social links — sourced from the same Footer options `ssr/fixed/Footer.php` reads. Change `@type` to `RealEstateAgent`/`LocalBusiness`/whatever fits the project (see the docblock). Keep this wired on every project — it's the machine-readable half of the identity fix below.

### Why `ssr/fixed/Header.php` and `ssr/fixed/Footer.php` are not optional

**This happened for real, not hypothetically.** A project built on this base shipped with `Header` and `Footer` as 100% client-only React components — the fixed SSR templates existed in this base theme, but got dropped somewhere in that project's fork lineage (it was cloned from an older sibling project, not straight from this base, and nobody noticed the `ssr/fixed/` wiring was missing from `footer.php`). Months later, **Google Ads suspended the account for "Unacceptable Business Practices."** Root cause, found by diffing `curl` (no JS) against a real browser render: the raw HTML had a lead-gen form asking for name/email/phone, and *zero* visible business identity — no company name, no address, no phone, no Privacy Policy/Terms links. All of it rendered correctly for real users after React hydrated; none of it reached the crawler, because Ad platforms' compliance crawlers are known to not reliably execute JS (or use a much shorter render budget than organic search).

Takeaways for every project built on this base:
1. **Never treat `ssr/fixed/Header.php` / `Footer.php` as boilerplate to fill in "later."** They exist specifically so a no-JS crawler can see who operates the site. A lead-gen or e-commerce site with a client-only footer is a live compliance risk, not just an SEO nice-to-have.
2. **When starting a project by cloning an existing sibling project instead of this base directly**, diff its `footer.php` / `ssr/` against this base first. It's easy for a fork-of-a-fork to silently lose structural safety nets like this one.
3. **Verify by curling the raw page, not by checking the browser.** `curl https://yoursite.com/ | grep -c '<footer'` (or equivalent) — if the business name/address/phone/Privacy-Terms links aren't in that output, a no-JS crawler doesn't see them either, no matter how correct the JS-rendered page looks. This is exactly how the bug above was found — the JS-rendered site had looked fine the whole time.
4. Ship `ditto_organization_schema()` (above) alongside the SSR templates — it's a second, independent identity signal that doesn't depend on crawl timing at all.

## Analytics on an SPA — client-side navigation never fires a "page view" on its own

**Also happened for real.** GTM's container load, `gtag('config', …)` (`header.php`), and any standalone pixel snippet a project adds all run **once**, on the actual browser page load. Every navigation after that in this app — React Router's `navigate()`, any `<Link>`/`<NavLink>` — is a client-side `history.pushState`, never a browser reload. None of those scripts see it happen by themselves.

A project built on this base installed its tracking codes and the client immediately reported that the form-submission conversion (redirecting to a "thank you" page) wasn't registering. Root cause: a URL/page-view-based conversion trigger (in GTM, or an ad platform's own UI) can never fire for a redirect that never reloads the browser — exactly the pattern above.

Fixed generically in `src/utils/tracking.tsx`, wired into `Layout.tsx`:
- **`pushVirtualPageview(path)`** — called on every route change (`useLocation().pathname` in `Layout.tsx`). Pushes a `virtual_page_view` dataLayer event and calls `gtag('event', 'page_view', {page_path})` so GA4's own pageview count and any GTM "History Change"/custom-event trigger see SPA navigation like a real page load. **Every project should have this wired from day one**, whether or not tracking codes are installed yet — it costs nothing and the alternative is silently under-counting every pageview past the first.
- **`trackConversion(eventName, params?)`** — call this directly at the exact moment a form/purchase/signup succeeds, *before* navigating to any confirmation route. Never assume a URL change alone will be tracked; the reliable signal is firing the event at the point of success, independent of whatever page it redirects to afterwards or however (or whether) a page-view trigger is configured downstream.
- **Verify without ever submitting a real form**: temporarily stub the network call (whatever utility POSTs the form) to return success with no request, submit the form, check `window.dataLayer` and a monkey-patched `window.gtag`/`window.fbq` for the expected calls, then revert the stub immediately via `git checkout` before committing anything. Never let a tracking-verification test hit a real CRM/API endpoint.

## GitHub Actions — Build & Deploy

The workflow at `.github/workflows/build.yml` builds on push to `main` and force-pushes `dist/` to a `production` branch.

**Critical:** the `.gitignore` sed command must use `'/^dist\b/d'` (word boundary) — NOT `'/^dist\//d'` (slash). The `.gitignore` entry is typically `dist` without a trailing slash, so the slash pattern won't match.

## Key Conventions

- Page content is stored in an ACF flexible content field named **`ditto_components`** — update this name in `inc/wordpress_settings.php`, `inc/endpoints.php`, and `footer.php` when starting a new project
- The reCAPTCHA site key global is named **`window._recaptchaSiteKey_`** (set in `header.php`). ACF field name is `recaptcha_site_key` (managed by `inc/recaptcha.php`). When migrating legacy themes that used `_RECAPTCHAKEY_`, update all component references.
- `add_theme_support('title-tag')` in `inc/wordpress_settings.php` is required for Yoast SEO to control `<title>` via `wp_head()`. Never use the deprecated `wp_title()` in `header.php`.
- `acf_fc_layout` names in ACF must match keys in the `lazyComponents` map in `src/UI/DynamicZone/DynamicZone.tsx` exactly
- CSS module class names are deterministic (`[name]__[local]`, no hash) so PHP SSR templates can reference them safely
- The wildcard route in `src/app.tsx` renders `<Error404 />` by default; swap for `<Navigate to="/" />` on single-page landing sites
- Every new dynamic component needs both a `ssr/components/{layout}.php` mirror and an entry in `DynamicZone.tsx`
- Every new fixed component needs a `ssr/fixed/{Component}.php` mirror and an include in `footer.php` (with any required context guard)
- Use `$post->post_title` (not `get_the_title()`) in REST endpoints to avoid HTML entity encoding
