<?php
/**
 * Plugin Name: Commerce Core
 * Description: Small, optional account conveniences over native WooCommerce data and forms.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 11.2
 * WC tested up to: 11.2.1
 * Text Domain: commerce-core
 * License: GPL-2.0-or-later
 */

namespace CommerceCore;

defined('ABSPATH') || exit;

const VERSION = '1.0.0';

add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce') || !class_exists('WC_Customer')) { return; }
    // A site-specific extension can disable this module without changing data.
    if (apply_filters('commerce_core_address_copy_enabled', true)) {
        require_once __DIR__ . '/includes/class-address-copy.php';
        Address_Copy::register();
    }
});

// No activation, deactivation or uninstall writes. WooCommerce owns all data.
