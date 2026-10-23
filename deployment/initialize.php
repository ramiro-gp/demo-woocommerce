<?php
/** Opt-in safe baseline for an EMPTY shop. No client identity, catalog or payment configuration. */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI) { exit; }
if (!class_exists('WooCommerce')) { WP_CLI::error('Install and activate the approved WooCommerce version first.'); }
if (get_option('commerce_distribution_initialized')) { WP_CLI::success('Baseline already initialized; no changes.'); return; }
global $wpdb;
if (wc_get_orders(['limit'=>1]) || (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')") > 0) { WP_CLI::error('Refusing an existing commercial installation.'); }
$users=get_users();
if (count($users)!==1 || !in_array('administrator',$users[0]->roles,true)) { WP_CLI::error('Expected only the initial administrator.'); }
foreach (['wc_orders','commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $table=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table && (int)$wpdb->get_var('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`')>0) { WP_CLI::error('Existing orders or legal history; refusing baseline.'); }
}
if ((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund','wp_template','wp_template_part','wp_navigation','nav_menu_item') OR (post_type='attachment' AND post_name<>'woocommerce-placeholder')")>0) { WP_CLI::error('Existing client content or legacy orders; refusing baseline.'); }
$allowed=array_filter(array_map('wc_get_page_id',['shop','cart','checkout','myaccount']));
foreach (get_posts(['post_type'=>['post','page'],'post_status'=>'any','numberposts'=>-1]) as $post) {
    if (!in_array((int)$post->ID,$allowed,true) && !in_array($post->post_name,['hello-world','sample-page','privacy-policy'],true) && !($post->post_name==='refund_returns' && $post->post_status==='draft')) { WP_CLI::error('Existing non-default content; refusing baseline.'); }
}
$legal=get_option('commerce_core_legal',[]);
if (!empty($legal['trade_name']) || get_option('woocommerce_store_address') || get_option('woocommerce_store_address_2')) { WP_CLI::error('Existing commercial configuration; refusing baseline.'); }
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
