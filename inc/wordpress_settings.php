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
 */
function render_media($media) {
  if (!$media) return '';

  if ($media['mime_type'] === 'video/mp4') {
    return sprintf(
      '<video autoplay muted loop playsinline><source src="%s" type="video/mp4"></video>',
      esc_url($media['url'])
    );
  }

  return sprintf('<img src="%s" alt="%s" />', esc_url($media['url']), esc_attr($media['alt'] ?? ''));
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
