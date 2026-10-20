<?php
/** Native account presentation only. Authentication and permissions stay in WooCommerce. */
defined('ABSPATH') || exit;

add_filter('body_class', static function ($classes) {
    if (!function_exists('is_account_page') || !is_account_page()) { return $classes; }
    $classes[] = is_user_logged_in() ? 'cb-account-authenticated' : 'cb-account-guest';
    if (is_wc_endpoint_url('lost-password')) { $classes[] = 'cb-account-recovery'; }
    return $classes;
});

// With plain permalinks, page_id makes WC's dashboard query nonempty. Keep
// the root destination visually current without changing query vars or routes.
add_filter('woocommerce_account_menu_item_classes', static function ($classes, $endpoint) {
    if ('dashboard' === $endpoint && is_account_page() && !is_wc_endpoint_url() && !in_array('is-active', $classes, true)) { $classes[] = 'is-active'; }
    return $classes;
}, 10, 2);

// WC links the placeholder even to a draft policy. Keep the stored placeholder
// and visible copy, but use its translated label until the policy is published.
add_filter('woocommerce_get_privacy_policy_text', static function ($text, $type) {
    if ('registration' === $type && is_account_page() && 'publish' !== get_post_status(wc_privacy_policy_page_id())) {
        return str_replace('[privacy_policy]', __('privacy policy', 'woocommerce'), $text);
    }
    return $text;
}, 10, 2);

// WC_Query generates endpoint titles even when the shortcode renders login.
// Keep the public password-recovery title and every authenticated title native.
foreach (['orders', 'view-order', 'downloads', 'edit-account', 'edit-address', 'payment-methods', 'add-payment-method'] as $endpoint) {
    add_filter('woocommerce_endpoint_' . $endpoint . '_title', static function ($title) {
        if (is_admin() || !is_account_page() || is_user_logged_in()) { return $title; }
        // Reading the stored title avoids recursively invoking the_title.
        return get_post_field('post_title', wc_get_page_id('myaccount')) ?: __('My account', 'woocommerce');
    });
}
