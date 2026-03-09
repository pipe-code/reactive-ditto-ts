<?php
/**
 * SSR Component Example — copy this file, rename it to match your acf_fc_layout,
 * and implement the HTML that mirrors your React component's initial render.
 *
 * HOW SSR WORKS IN THIS THEME
 * ----------------------------
 * 1. footer.php fetches the current page's ACF components and sets a query var.
 * 2. ssr/dynamicZone.php loops over them and includes the matching PHP file here.
 * 3. This HTML renders at opacity:0 — visible to crawlers, hidden to users.
 * 4. React mounts, replaces the SSR DOM with its own render, and takes over.
 *
 * IMPORTANT: React uses createRoot() (not hydrateRoot()), so this HTML is
 * fully replaced — not hydrated. Keep SSR templates simple; their only job
 * is to put meaningful content in the HTML source for SEO.
 *
 * CSS CLASS NAMES
 * ---------------
 * Webpack is configured with localIdentName: "[name]__[local]" (no hash).
 * This means you can safely hardcode SCSS module class names here, e.g.:
 *   <div class="MyComponent__wrapper">
 * The class will always match what React generates — it won't break on rebuild.
 *
 * EXAMPLE
 * -------
 * For an ACF layout named "TextBlock" with fields "title" and "body":
 *
 * Rename this file to: ssr/components/TextBlock.php
 * Then implement it like this:
 *
 *   $title = $component['title'] ?? '';
 *   $body  = $component['body']  ?? '';
 *   ?>
 *   <section class="TextBlock__wrapper">
 *     <h2 class="TextBlock__title"><?= esc_html($title) ?></h2>
 *     <div class="TextBlock__body"><?= wp_kses_post($body) ?></div>
 *   </section>
 *   <?php
 *
 * The $component variable is available because dynamicZone.php runs include,
 * which shares the same variable scope.
 */
?>
