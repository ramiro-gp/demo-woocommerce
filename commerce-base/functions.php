<?php
/** Commerce Base: presentation only. */
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/inc/components.php';

add_action('after_setup_theme', static function () {
    add_theme_support('woocommerce');
    add_theme_support('custom-logo', ['height' => 80, 'width' => 280, 'flex-width' => true, 'flex-height' => true]);
    add_theme_support('editor-styles');
    add_editor_style('assets/css/system.css');
    register_nav_menus(['primary' => __('Navegación principal', 'commerce-base')]);
});

add_filter('woocommerce_placeholder_img_src', static function ($source) {
    return get_option('woocommerce_placeholder_image', 0) ? $source : get_theme_file_uri('assets/images/product-placeholder.svg');
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
    wp_enqueue_script('commerce-base', get_theme_file_uri('assets/js/interaction.js'), [], $version, ['in_footer' => true, 'strategy' => 'defer']);
});

// Before styles: resolve the visitor's preference without a network request.
add_action('wp_head', static function () {
    echo '<script id="commerce-base-color-init">' . file_get_contents(get_theme_file_path('assets/js/theme-init.js')) . '</script>' . "\n";
}, 0);

add_action('init', static function () {
    register_block_pattern_category('commerce-base', ['label' => __('Commerce Base', 'commerce-base')]);
    wp_register_script('commerce-base-editor', get_theme_file_uri('assets/js/editor.js'), ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render'], wp_get_theme()->get('Version'), true);
    foreach (['header', 'footer', 'product-grid'] as $component) {
        register_block_type('commerce-base/' . $component, [
            'api_version' => 3,
            'editor_script' => 'commerce-base-editor',
            'attributes' => $component === 'product-grid' ? ['limit' => ['type' => 'number', 'default' => 4]] : [],
            'supports' => ['html' => false, 'inserter' => $component === 'product-grid'],
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
