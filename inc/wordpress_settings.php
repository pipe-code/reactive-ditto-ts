<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register Theme Scripts
 * https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
 */
function ditto_scripts() {
  // Cache-bust with the bundle's mtime so every build invalidates the browser
  // and CDN copy automatically. Falls back to the theme version if dist is
  // missing (e.g. before the first build). Lazy chunks are busted separately by
  // their content hash (chunkFilename in webpack.config.js).
  $bundle_path = get_template_directory() . '/dist/app.bundle.js';
  $appVersion  = file_exists( $bundle_path ) ? filemtime( $bundle_path ) : wp_get_theme()->get( 'Version' );
  wp_enqueue_script( 'app-scripts', get_template_directory_uri() . '/dist/app.bundle.js', array(), $appVersion, true );

  // Pre-load router data so React doesn't need an extra API round-trip on first paint
  if ( function_exists( 'router_pages_handler' ) ) {
    $router_data = router_pages_handler();
    wp_add_inline_script( 'app-scripts', 'window.__ROUTER_DATA__ = ' . wp_json_encode( $router_data ) . ';', 'before' );
  }

  // Pre-load current page ACF content so Page.tsx skips its initial API call
  global $post;
  if ( $post && $post->ID && function_exists( 'get_field' ) ) {
    $page_components = get_field( 'ditto_components', $post->ID );
    if ( $page_components ) {
      $page_data = array(
        'have_post' => true,
        'title'     => get_the_title( $post->ID ),
        'content'   => $page_components,
      );
      wp_add_inline_script( 'app-scripts', 'window.__PAGE_DATA__ = ' . wp_json_encode( $page_data ) . ';', 'before' );
    }
  }

  // Wordpress Front Trash
  wp_dequeue_script( 'contact-form-7');
  wp_dequeue_script( 'jquery-ui-core' );
  wp_dequeue_style( 'contact-form-7' );
  wp_dequeue_style( 'global-styles' );
  wp_dequeue_style( 'wp-block-library' );
}
add_action( 'wp_enqueue_scripts', 'ditto_scripts');

/**
 * Theme support
 * https://developer.wordpress.org/reference/functions/add_theme_support/
 */
add_theme_support( 'title-tag' );   // lets Yoast SEO (via wp_head) manage <title> — removes deprecated wp_title()
add_theme_support( 'custom-logo' );
add_theme_support( 'post-thumbnails' );

/**
 * Register Navigation Menus
 * https://developer.wordpress.org/reference/functions/register_nav_menus/
 */
function ditto_navigation_menus() {
  $locations = array(
    'main_menu' => __( 'Main Menu', 'text_domain' )
  );
  register_nav_menus( $locations );
}
add_action( 'init', 'ditto_navigation_menus' );

/**
 * Login Styles
 */
function ditto_login_styles() { ?>
  <style type="text/css">
    body {
      background-color: #222 !important;
    }
    #login h1 a, .login h1 a {
      display: none;
    }
    #login h1 img {
      width: 100%;
      max-width: 240px;
      max-height: 180px;
    }
  </style>
  <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function(event) {
      let loginImg = document.createElement("img");
        loginImg.src = "<?= esc_js( get_template_directory_uri() ) ?>/src/assets/pipe-code-logo.svg";
        loginImg.alt = "WordPress login image";
        document.querySelector('#login h1').appendChild(loginImg);
    });
  </script>
<?php }
add_action( 'login_enqueue_scripts', 'ditto_login_styles' );

/**
 * Disable Gutenberg
 */
add_filter('use_block_editor_for_post', '__return_false', 10);
add_filter('use_block_editor_for_post_type', '__return_false', 10);

/**
 * Allow big images
 */
add_filter( 'big_image_size_threshold', '__return_false' );

/**
 * Register ACF Custom Menu
 * https://www.advancedcustomfields.com/resources/acf_add_options_page/
 */
function ditto_menu_settings() {
  if (function_exists('acf_add_options_page')) {
    acf_add_options_page(array(
      'page_title'    => 'Theme Settings',
      'menu_title'    => 'Theme Settings',
      'menu_slug'     => 'theme-settings',
      'capability'    => 'edit_posts',
      'redirect'      =>  true
    ));

    acf_add_options_sub_page(array(
      'page_title' 	=> 'Footer',
      'menu_title' 	=> 'Footer',
      'parent_slug'   => 'theme-settings',
    ));
  }
}
add_action('init', 'ditto_menu_settings');

/**
 * Security Headers
 * Set baseline HTTP security headers for every front-end response.
 * HSTS and CSP should be configured at the server (nginx/apache) level;
 * these cover what is practical to set from within WordPress.
 */
function ditto_security_headers() {
  if ( is_admin() ) return;
  header( 'X-Content-Type-Options: nosniff' );
  header( 'X-Frame-Options: SAMEORIGIN' );
  header( 'Referrer-Policy: strict-origin-when-cross-origin' );
  header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
}
add_action( 'send_headers', 'ditto_security_headers' );

/**
 * Render a media attachment (image or video) as HTML.
 * Used by SSR PHP templates to mirror the React MediaRender component.
 *
 * Matches any video/* mime, not just video/mp4 — a real project's media
 * library will have .webm (smaller, common for background/hero video) and a
 * strict 'video/mp4' check silently falls through to <img src="*.webm">,
 * which is broken. Confirmed the hard way on a derived project.
 */
function render_media($media, $class = '') {
  if (!$media || empty($media['url'])) return '';

  $class_attr = $class ? sprintf(' class="%s"', esc_attr($class)) : '';

  if (strpos($media['mime_type'] ?? '', 'video') === 0) {
    return sprintf(
      '<video%s src="%s" muted loop playsinline preload="metadata"></video>',
      $class_attr,
      esc_url($media['url'])
    );
  }

  return sprintf(
    '<img%s src="%s" alt="%s" loading="lazy" decoding="async" />',
    $class_attr,
    esc_url($media['url']),
    esc_attr($media['alt'] ?? '')
  );
}

/**
 * Organization / LocalBusiness structured data (JSON-LD), output in
 * header.php on every page.
 *
 * CUSTOMIZING
 * -----------
 * Field names below assume the same 'footer_content' options group that
 * ssr/fixed/Footer.php reads — adjust to match the real project's fields.
 * Change '@type' to whatever fits: 'RealEstateAgent' for a property
 * marketing site, 'LocalBusiness' for a physical storefront, 'ProfessionalService',
 * etc. — see https://schema.org/Organization for subtypes.
 *
 * WHY THIS EXISTS
 * ----------------
 * This gives Google (and any automated compliance crawler that doesn't wait
 * for React to hydrate — ad platforms' policy crawlers notably) a
 * machine-readable, unambiguous statement of who operates the site: name,
 * address, phone, email, social profiles, as a
 * <script type="application/ld+json"> in the raw HTML response, independent
 * of JS execution or crawl timing.
 *
 * This is not theoretical: a site built on this base was suspended from
 * Google Ads for "Unacceptable Business Practices" because Header and Footer
 * were 100% client-only React with no ssr/fixed/ mirror — a no-JS crawler
 * saw a lead-gen form with zero visible business identity. Ship this
 * function AND keep ssr/fixed/Header.php + ssr/fixed/Footer.php filled in
 * with real content on every project — see the "SSR Architecture" section
 * of CLAUDE.md for the full story and the escaping/verification conventions
 * that go with it.
 */
function ditto_organization_schema() {
  if ( ! function_exists( 'get_field' ) ) return;
  $footer = get_field( 'footer_content', 'options' );
  if ( ! $footer ) return;

  // Collapses <br>-separated lines to ", " and normalizes whitespace —
  // including U+2028/U+2029 line separators and non-breaking spaces, which
  // sneak into ACF text fields pasted from Word/Google Docs and would
  // otherwise leak as mangled bytes into the JSON-LD.
  $strip = function( $html ) {
    $text = str_replace( ['<br>', '<br/>', '<br />'], ', ', (string) $html );
    $text = wp_strip_all_tags( $text );
    $text = str_replace( ["\xE2\x80\xA8", "\xE2\x80\xA9", "\xC2\xA0"], ' ', $text );
    $text = preg_replace( '/\s+/u', ' ', $text );
    $text = preg_replace( '/\s*,\s*/u', ', ', $text );
    return trim( $text, " \t\n\r\0\x0B," );
  };

  $schema = array(
    '@context' => 'https://schema.org',
    '@type'    => 'Organization', // see CUSTOMIZING above
    'name'     => get_bloginfo( 'name' ),
    'url'      => home_url( '/' ),
  );

  if ( ! empty( $footer['logo']['url'] ) ) {
    $schema['logo']  = $footer['logo']['url'];
    $schema['image'] = $footer['logo']['url'];
  }

  if ( ! empty( $footer['phone']['title'] ) ) {
    $schema['telephone'] = $strip( $footer['phone']['title'] );
  }

  if ( ! empty( $footer['mail']['title'] ) ) {
    $schema['email'] = $strip( $footer['mail']['title'] );
  }

  if ( ! empty( $footer['address']['title'] ) || ! empty( $footer['city'] ) ) {
    $schema['address'] = array(
      '@type'           => 'PostalAddress',
      'streetAddress'   => $strip( $footer['address']['title'] ?? '' ),
      'addressLocality' => $strip( $footer['city'] ?? '' ),
    );
  }

  if ( ! empty( $footer['social_media'] ) ) {
    $same_as = array();
    foreach ( $footer['social_media'] as $row ) {
      if ( ! empty( $row['link']['url'] ) ) $same_as[] = $row['link']['url'];
    }
    if ( $same_as ) $schema['sameAs'] = $same_as;
  }

  echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/**
 * ACF Link Field Multilang — tell WPM to translate link title and URL fields.
 */
add_filter('wpm_acf_link_config', function() {
	return array(
		'title' => array(),
    'url' => array()
	);
});

?>
