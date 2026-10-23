<?php
/** Opt-in safe baseline for an EMPTY shop. No client identity, catalog or payment configuration. */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI) { exit; }
if (!class_exists('WooCommerce')) { WP_CLI::error('Install and activate the approved WooCommerce version first.'); }
if (get_option('commerce_distribution_initialized')) { WP_CLI::success('Baseline already initialized; no changes.'); return; }
global $wpdb;
if (wc_get_orders(['limit'=>1]) || (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')") > 0) { WP_CLI::error('Refusing an existing commercial installation.'); }
if (is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php')) { WP_CLI::error('Deactivate Mercado Pago before baseline setup.'); }
update_option('blog_public', '0');
update_option('woocommerce_custom_orders_table_enabled','yes');
update_option('woocommerce_custom_orders_table_data_sync_enabled','no');
update_option('woocommerce_store_pages_only','yes');
update_option('woocommerce_coming_soon','yes');
foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
    $settings = get_option('woocommerce_' . $gateway->id . '_settings', []);
    $settings['enabled'] = 'no';
    update_option('woocommerce_' . $gateway->id . '_settings', $settings);
}
WC_Install::create_pages();
update_option('commerce_base_exit_intent',['enabled'=>false]);
update_option('commerce_distribution_initialized','1.0.0');
flush_rewrite_rules(false);
WP_CLI::success('Safe baseline created: noindex, Coming Soon, all gateways disabled. Client configuration and release approval remain required.');
