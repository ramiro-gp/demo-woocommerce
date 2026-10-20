<?php
/**
 * Plugin Name: Commerce Account Navigation
 * Description: A merchant-controlled Downloads navigation item over native WooCommerce accounts.
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: commerce-account-navigation
 */
defined('ABSPATH') || exit;

add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Explicit store configuration, defaulting to native behavior in new stores.
// No catalog scans, endpoint changes, permission filters or download-file writes.
add_filter('woocommerce_account_menu_items', static function ($items) {
    if ('no' === get_option('commerce_account_show_downloads', 'yes')) { unset($items['downloads']); }
    return $items;
});

// WooCommerce owns rendering, nonce/capability checks and persistence of this field.
add_filter('woocommerce_account_settings', static function ($settings) {
    $settings[] = ['title' => __('Navegación de Mi cuenta', 'commerce-account-navigation'), 'type' => 'title', 'id' => 'commerce_account_navigation'];
    $settings[] = [
        'title' => __('Descargas', 'commerce-account-navigation'),
        'desc' => __('Mostrar Descargas en el menú de Mi cuenta', 'commerce-account-navigation'),
        'desc_tip' => __('Desmarcá esta opción en tiendas de productos físicos. El acceso y los permisos de descarga de WooCommerce se conservan.', 'commerce-account-navigation'),
        'id' => 'commerce_account_show_downloads',
        'type' => 'checkbox',
        'default' => 'yes',
        'autoload' => false,
    ];
    $settings[] = ['type' => 'sectionend', 'id' => 'commerce_account_navigation'];
    return $settings;
});
