<?php
/** Commerce Base: presentation only. */
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/inc/components.php';
require_once __DIR__ . '/inc/checkout-labels.php';
require_once __DIR__ . '/inc/discovery.php';
require_once __DIR__ . '/inc/catalog.php';

// With plain permalinks and an Apache front controller, core has no rewrite
// rules to distinguish an unknown path from the root. Preserve native query
// URLs and API handlers, but let WordPress render its real 404 for other paths.
add_action('parse_request', static function ($wp) {
    if (is_admin() || get_option('permalink_structure') !== '' || !empty($wp->matched_rule) || isset($wp->query_vars['rest_route']) || isset($wp->query_vars['wc-api'])) { return; }
    $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $root = trailingslashit(wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/');
    if (!is_string($path) || $path === $root || $path === $root . 'index.php') { return; }
    $wp->query_vars['error'] = '404';
}, 20);

add_action('after_setup_theme', static function () {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('custom-logo', ['height' => 80, 'width' => 280, 'flex-width' => true, 'flex-height' => true]);
    add_theme_support('editor-styles');
    add_editor_style('assets/css/system.css');
    add_editor_style('assets/css/pages.css');
    add_editor_style('assets/css/catalog.css');
    add_editor_style('assets/css/lookbook.css');
    register_nav_menus(['primary' => __('Navegación principal', 'commerce-base'), 'footer-social' => __('Redes del footer (URLs del comercio)', 'commerce-base')]);
});

add_filter('woocommerce_placeholder_img_src', static function ($source) {
    $id = absint(get_option('woocommerce_placeholder_image', 0));
    $file = $id ? basename((string) get_attached_file($id)) : '';
    // Keep a merchant's custom image; replace only WooCommerce's built-in fallback.
    return $id && !preg_match('/^woocommerce-placeholder\.(?:png|jpe?g|webp)$/i', $file) ? $source : get_theme_file_uri('assets/images/product-placeholder.svg');
});

// Give native WooCommerce block templates the same skip-link target and width
// without copying their templates or changing any commerce block behavior.
add_filter('render_block_core/group', static function ($html, $block) {
    if ('main' !== ($block['attrs']['tagName'] ?? '')) { return $html; }
    $processor = new WP_HTML_Tag_Processor($html);
    if ($processor->next_tag('main')) {
        if (!$processor->get_attribute('id')) { $processor->set_attribute('id', 'main-content'); }
        if (!$processor->has_class('cb-page-content')) { $processor->add_class('cb-native-main'); }
        if (function_exists('is_woocommerce') && is_woocommerce()) { $processor->add_class('cb-native-commerce'); }
    }
    return $processor->get_updated_html();
}, 10, 2);

add_action('wp_enqueue_scripts', static function () {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('commerce-base', get_theme_file_uri('assets/css/system.css'), [], $version);
    wp_enqueue_style('commerce-base-pages', get_theme_file_uri('assets/css/pages.css'), ['commerce-base'], $version);
    wp_enqueue_style('commerce-base-catalog', get_theme_file_uri('assets/css/catalog.css'), ['commerce-base-pages'], $version);
    wp_enqueue_style('commerce-base-lookbook', get_theme_file_uri('assets/css/lookbook.css'), ['commerce-base-pages'], $version);
    wp_enqueue_script('commerce-base', get_theme_file_uri('assets/js/interaction.js'), [], $version, ['in_footer' => true, 'strategy' => 'defer']);
    if (function_exists('WC')) {
        wp_enqueue_script('commerce-base-cart-presentation', get_theme_file_uri('assets/js/cart-presentation.js'), ['wp-element', 'wp-data', 'wc-blocks-data-store'], $version, true);
    }
    if (function_exists('is_woocommerce') && (is_woocommerce() || is_search())) {
        wp_enqueue_script('commerce-base-catalog', get_theme_file_uri('assets/js/catalog.js'), [], $version, ['in_footer' => true, 'strategy' => 'defer']);
    }
    if (function_exists('is_product') && is_product()) {
        $product = wc_get_product(get_queried_object_id());
        if ($product && $product->is_type('variable')) {
            wp_enqueue_script('commerce-base-variations', get_theme_file_uri('assets/js/variations.js'), ['jquery', 'wc-add-to-cart-variation'], $version, true);
        }
    }
});

// Before styles: resolve the visitor's preference without a network request.
add_action('wp_head', static function () {
    echo '<script id="commerce-base-color-init">' . file_get_contents(get_theme_file_path('assets/js/theme-init.js')) . '</script>' . "\n";
}, 0);

add_action('wp_body_open', static function () { echo '<div id="cb-page-top" tabindex="-1"></div>'; });

add_action('init', static function () {
    register_block_pattern_category('commerce-base', ['label' => __('Commerce Base', 'commerce-base')]);
    wp_register_script('commerce-base-editor', get_theme_file_uri('assets/js/editor.js'), ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'], wp_get_theme()->get('Version'), true);
    foreach (['header', 'footer', 'product-grid', 'category-links', 'search-tools'] as $component) {
        $attributes = [];
        if ($component === 'product-grid') { $attributes = ['limit' => ['type' => 'number', 'default' => 4]]; }
        if ($component === 'category-links') { $attributes = ['limit' => ['type' => 'number', 'default' => 4], 'termIds' => ['type' => 'array', 'default' => [], 'items' => ['type' => 'number']]]; }
        if ($component === 'search-tools') { $attributes = ['recovery' => ['type' => 'boolean', 'default' => false]]; }
        register_block_type('commerce-base/' . $component, [
            'api_version' => 3,
            'editor_script' => 'commerce-base-editor',
            'attributes' => $attributes,
            'supports' => ['html' => false, 'inserter' => !in_array($component, ['header', 'footer'], true), 'interactivity' => ['clientNavigation' => true]],
            'render_callback' => static function ($attributes) use ($component) {
                return call_user_func('commerce_base_' . str_replace('-', '_', $component), $attributes);
            },
        ]);
    }
});

function commerce_base_is_showcase() {
    return isset($_GET['commerce-base-showcase']) && '1' === $_GET['commerce-base-showcase'];
}

add_action('template_redirect', static function () {
    if (!commerce_base_is_showcase()) { return; }
    if ('local' !== wp_get_environment_type() && !current_user_can('manage_options')) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    add_filter('wp_robots', static function () { return ['noindex' => true, 'nofollow' => true, 'noarchive' => true]; });
    add_filter('template_include', static function () { return __DIR__ . '/inc/showcase.php'; });
    add_filter('pre_get_document_title', static function () { return 'Commerce Base — Sistema visual H3'; });
});

add_filter('body_class', static function ($classes) {
    $content = is_singular() ? (string) get_post_field('post_content', get_queried_object_id()) : '';
    if ((commerce_base_is_showcase() && ('local' === wp_get_environment_type() || current_user_can('manage_options'))) || str_contains($content, 'cb-hero') || str_contains($content, 'commerce-base/editorial-hero')) {
        $classes[] = 'has-hero';
    }
    return $classes;
});
