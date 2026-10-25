<?php
/** Claim an empty D1 loopback lab, with no copied DB/users/uploads. CLI only. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local'
    || !in_array(home_url(), ['http://localhost:8190','http://localhost:8191'], true)) { exit(1); }
global $wpdb;
if (get_option('commerce_d1_installation') || count(get_users()) !== 1
    || wc_get_products(['limit'=>1]) || wc_get_orders(['limit'=>1])) {
    WP_CLI::error('D1 requires a new empty laboratory. Preserve existing data.');
}
foreach (get_users() as $user) {
    if (!str_ends_with($user->user_email, '.test') || !in_array('administrator', $user->roles, true)) { WP_CLI::error('Synthetic admin required.'); }
}
// Check storage directly as well: WooCommerce's normal query can omit drafts,
// legacy history or orphaned rows when another order storage is selected.
if ((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund','shop_order_placehold')")) {
    WP_CLI::error('Legacy order history forbidden.');
}
foreach (['wc_orders','woocommerce_order_items'] as $suffix) {
    $table=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table
        && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")) { WP_CLI::error('Order storage must be empty.'); }
}
foreach (['commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $table=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table
        && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")) { WP_CLI::error('Legal history forbidden.'); }
}
foreach (['blog_public'=>'0','show_avatars'=>'0','woocommerce_currency'=>'ARS',
    'timezone_string'=>'America/Argentina/Buenos_Aires','woocommerce_default_country'=>'AR:C',
    'woocommerce_price_thousand_sep'=>'.','woocommerce_price_decimal_sep'=>',','woocommerce_price_num_decimals'=>'0',
    'woocommerce_custom_orders_table_enabled'=>'yes','woocommerce_custom_orders_table_data_sync_enabled'=>'no',
    'woocommerce_coming_soon'=>'no','woocommerce_store_pages_only'=>'no',
    'woocommerce_attribute_lookup_enabled'=>'yes','commerce_d1_installation'=>'d1-public-demo-lab',
    'commerce_demo_public_installation'=>'public-demo-v1'] as $key=>$value) { update_option($key,$value); }
WC_Install::create_pages();
WP_CLI::success('Empty D1 lab claimed.');
