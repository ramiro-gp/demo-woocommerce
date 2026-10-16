<?php
/**
 * Plugin Name: Commerce Lookbook
 * Description: Reusable Shoppable Lookbook block for WooCommerce. Editorial data independent of the theme.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: commerce-lookbook
 */
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/render.php';

add_action('init', static function () {
    register_block_type(__DIR__ . '/block', ['render_callback' => 'commerce_lookbook_render']);
});

add_action('rest_api_init', static function () {
    register_rest_route('commerce-lookbook/v1', '/products', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => static function () {
            return current_user_can('edit_products') && (current_user_can('edit_posts') || current_user_can('edit_pages'));
        },
        'args' => [
            'search' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            'include' => ['type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'maxItems' => 12],
        ],
        'callback' => static function ($request) {
            if (!function_exists('wc_get_product')) {
                return new WP_Error('woocommerce_unavailable', __('WooCommerce no está disponible.', 'commerce-lookbook'), ['status' => 503]);
            }
            $ids = array_slice(array_filter(array_map('absint', $request['include'])), 0, 12);
            $query = ['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 20,
                'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false];
            if ($ids) { $query['post__in'] = $ids; }
            else { $query['s'] = $request['search']; }
            $posts = get_posts($query); // Prime post/meta/term caches together, also for the editor.
            $products = [];
            foreach ($posts as $post) {
                $product = commerce_lookbook_product($post->ID);
                if (!$product) { continue; }
                $products[] = ['id' => $product->get_id(), 'name' => $product->get_name(),
                    'priceHtml' => wp_kses_post($product->get_price_html()), 'inStock' => $product->is_in_stock()];
            }
            return rest_ensure_response(['products' => $products,
                'missing' => array_values(array_diff($ids, array_column($products, 'id')))]);
        },
    ]);
});
