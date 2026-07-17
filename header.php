<?php
/**
 * 
 * Header.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <!-- Title and meta description are injected by Yoast SEO via wp_head() -->
  <!-- Requires add_theme_support('title-tag') in inc/wordpress_settings.php -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <!-- Resource hints — establish connections early to cut latency -->
  <link rel="preconnect" href="https://www.googletagmanager.com">
  <link rel="dns-prefetch" href="//www.googletagmanager.com">
  <link rel="preconnect" href="https://www.google-analytics.com">
  <link rel="dns-prefetch" href="//www.google-analytics.com">
  <link rel="preconnect" href="https://www.recaptcha.net">
  <link rel="dns-prefetch" href="//www.recaptcha.net">

  <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
  <?php wp_head(); ?>
  <?php if ( function_exists( 'ditto_organization_schema' ) ) ditto_organization_schema(); ?>

  <!-- Critical runtime variables — must execute before the JS bundle -->
  <script>
    window._dittoURI_ = "<?= esc_js( get_template_directory_uri() ) ?>";
    window._dittoURL_ = "<?= esc_js( get_site_url() ) ?>";
    window._recaptchaSiteKey_ = "<?= esc_js( function_exists('get_field') ? get_field('recaptcha_site_key', 'options') : '' ) ?>";
  </script>

  <!-- Google Analytics — deferred to after page load so it never blocks rendering -->
  <script>
    window.addEventListener('load', function() {
      var gaId = ''; // TODO: set your GA4 measurement ID (e.g. 'G-XXXXXXXXXX')
      if (!gaId) return;
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + gaId;
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      // Assigned to window.gtag (not just a local function) so code outside
      // this closure — src/utils/tracking.tsx's pushVirtualPageview/
      // trackConversion — can call it too. Confirmed the hard way: without
      // this line, window.gtag is undefined and every gtag('event', ...)
      // call from the app silently no-ops.
      function gtag(){dataLayer.push(arguments);}
      window.gtag = gtag;
      gtag('js', new Date());
      gtag('config', gaId);
    });
  </script>
</head>

<body <?php body_class(); ?>>
