<?php
/**
 * Register endpoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Add Cache-Control headers to all public GET REST API responses
add_filter( 'rest_post_dispatch', function( $response, $server, $request ) {
    if ( 'GET' === $request->get_method() && ! is_wp_error( $response ) ) {
        $response->header( 'Cache-Control', 'public, max-age=300, s-maxage=300' );
        $response->header( 'Vary', 'Accept-Encoding' );
    }
    return $response;
}, 10, 3 );

add_action( 'rest_api_init', function () {
    // Page
    register_rest_route( 'page', '/(?P<id>\d+)', array(
        array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => 'page_handler',
            'permission_callback'   => '__return_true',
        )
    ));
    // Main Menu
    register_rest_route( 'navigation', '/main_menu', array(
        array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => 'main_menu_handler',
            'permission_callback'   => '__return_true',
        )
    ) );
    // Footer
    register_rest_route( 'navigation', '/footer', array(
        array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => 'footer_handler',
            'permission_callback'   => '__return_true',
        )
    ) );
    // Router
    register_rest_route( 'router', '/pages', array(
        array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => 'router_pages_handler',
            'permission_callback'   => '__return_true',
        )
    ) );
    // Third-party form proxy — define PROXY_SUBMISSION_URL in wp-config.php (see proxy_submission_handler)
    register_rest_route( 'proxy', '/v1/submission', array(
        array(
            'methods'               => WP_REST_Server::CREATABLE,
            'callback'              => 'proxy_submission_handler',
            'permission_callback'   => '__return_true',
        )
    ) );
});

function main_menu_handler() {
    $output = [];
    $menu = wp_get_nav_menu_items(get_nav_menu_locations()['main_menu'], null);

    $frontpage_id = get_option( 'page_on_front' );
    $output["has_logo"] = has_custom_logo();

    if($output["has_logo"]) {
        $output["logo"] = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ) , 'full' )[0];
    } else {
        $output["logo"] = null;
    }

    $output["title"] = get_bloginfo( 'name' );

    if($menu) {
        foreach ($menu as $key => $item) {
            // Custom link menu items (type 'custom', object_id = 0) have no post
            // to read a slug from — use their URL directly. Handles hash anchors
            // (/#section), external links, and any hand-typed URL. Mirrored in
            // ssr/fixed/Header.php.
            if ( $item->type === 'custom' ) {
                $path = wp_make_link_relative( $item->url );
            } else {
                $path = ($frontpage_id == $item->object_id) ? '/' : '/'.get_post_field( 'post_name', $item->object_id );
            }
            $output["menu"][] = [
                "ID"        => (int)$item->ID,
                "title"     => $item->title,
                "url"       => $item->url,
                "slug"      => get_post_field( 'post_name', $item->object_id ),
                "path"      => $path,
                "page_id"   => (int)$item->object_id,
                "parent"    => (int)$item->post_parent,
                "classes"   => $item->classes,
                "target"    => $item->target
            ];
        }
    } else {
        $output["menu"] = [];
    }

    return $output;
}

function page_handler( $request ) {
    $params = $request->get_params();

    $page = get_posts([
        'p'                 => absint( $params['id'] ),
        'post_type'         => 'page',
        'post_status'       => 'publish',
    ]);

    if ($page):
        $output = [
            'have_post' => true,
            'title'     => get_the_title($page[0]->ID),
            'content'   => function_exists('get_field') ? get_field('ditto_components', $page[0]->ID) : null
        ];
    else:
        $output = [
            'have_post' => false,
            'title'     => null,
            'content'   => null
        ];
    endif;

    return $output;
}

function router_pages_handler() {
    $posts = get_posts([
        "posts_per_page" => -1,
        "post_status" => "publish",
        "post_type" => ["page"],  // add custom post types here if they need routes, e.g. ["page", "news"]
    ]);
    $frontpage_id = get_option( 'page_on_front' );

    $yoast_front_meta_title = get_post_meta( $frontpage_id, '_yoast_wpseo_title', true );
    $formatedPosts[] = [
        "ID"            => (int)$frontpage_id,
        "post_name"     => '/',
        "post_parent"   => '',
        "post_title"    => $yoast_front_meta_title ? $yoast_front_meta_title : get_bloginfo('name'),
        "post_type"     => get_post_type($frontpage_id),
        "lang"          => 'en',
    ];

    if($posts) {
        foreach ($posts as $key => $item) {
            if($item->ID != $frontpage_id) {
                $post_type = get_post_type($item->ID);
                $raw_post = get_post($item->ID);
                $post_parent = $raw_post->post_parent ? get_post_field( 'post_name', $raw_post->post_parent ) : '';
                $post_type_path = "/";
                if($post_type != "page") {
                    $post_type_path = "/{$post_type}/";
                }
                if ($post_parent && $raw_post->post_parent != $frontpage_id) {
                    $post_type_path .= "{$post_parent}/";
                }
                $formatedPosts[] = [
                    "ID"            => $item->ID,
                    "post_name"     => $post_type_path . $item->post_name,
                    "post_parent"   => $post_parent,
                    "post_title"    => get_post_meta($item->ID, '_yoast_wpseo_title', true) ? get_post_meta($item->ID, '_yoast_wpseo_title', true) : get_the_title($item->ID) . ' | ' . get_bloginfo('name'),
                    "post_type"     => $post_type,
                    "lang"          => 'en',
                ];
            }
        }
    }

    return [
        "basename"  => site_url( '', 'relative') . '/',
        "items"     => $formatedPosts
    ];
}

function footer_handler() {
    return function_exists('get_field') ? get_field('footer_content', 'options') : null;
}

/**
 * Generic third-party form proxy.
 * Set PROXY_SUBMISSION_URL in .env or override the $url variable below.
 */
function proxy_submission_handler( $request ) {
    $params = $request->get_json_params();
    $url = defined('PROXY_SUBMISSION_URL') ? PROXY_SUBMISSION_URL : '';

    if ( empty( $url ) ) {
        return new WP_Error( 'proxy_not_configured', 'Proxy submission URL is not configured.', array( 'status' => 500 ) );
    }

    $response = wp_remote_post( $url, array(
        'method'      => 'POST',
        'timeout'     => 15,   // reduced: 45s allowed slow-POST abuse
        'redirection' => 0,    // never follow redirects — prevents open redirect via proxy
        'httpversion' => '1.1',
        'blocking'    => true,
        'headers'     => array(
            'Content-Type' => 'application/json',
        ),
        'body'        => json_encode( $params ),
        'cookies'     => array()
    ) );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'proxy_error', $response->get_error_message(), array( 'status' => 500 ) );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body );

    return new WP_REST_Response( $data, wp_remote_retrieve_response_code( $response ) );
}
