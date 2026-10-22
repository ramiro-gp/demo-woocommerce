<?php
/** Local read-only inventory and strict preservation baseline. No PII in stdout. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8080') { exit(1); }
$mode = $args[0] ?? 'inspect';
$file = '/backups/h8-2-before.json';
$hash = static fn($v) => hash('sha256', wp_json_encode($v));
$capture = static function () use ($hash) {
    global $wpdb;
    $out = [];
    foreach (['wc_orders', 'wc_order_addresses', 'wc_order_operational_data', 'wc_orders_meta', 'woocommerce_order_items', 'woocommerce_order_itemmeta', 'woocommerce_shipping_zones', 'woocommerce_shipping_zone_locations', 'woocommerce_shipping_zone_methods'] as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $out[$suffix] = $hash($wpdb->get_results("SELECT * FROM `$table` ORDER BY 1", ARRAY_A));
    }
    $out['catalog'] = $hash($wpdb->get_results("SELECT p.*, m.meta_key,m.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE p.post_type IN ('product','product_variation','shop_coupon') ORDER BY p.ID,m.meta_id", ARRAY_A));
    $out['addresses'] = $hash($wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key LIKE 'billing_%' OR meta_key LIKE 'shipping_%' ORDER BY umeta_id", ARRAY_A));
    $out['order_notes'] = $hash($wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE comment_type='order_note' ORDER BY comment_ID", ARRAY_A));
    $options = [];
    foreach (['active_plugins','woocommerce_bacs_settings','woocommerce_bacs_accounts','woocommerce_woo-mercado-pago-basic_settings','trama_h5_catalog','trama_h6_1','trama_h6_2','woocommerce_store_address','woocommerce_store_city','woocommerce_store_postcode'] as $key) { $options[$key] = get_option($key); }
    foreach ($wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_flat_rate_%' OR option_name LIKE 'woocommerce_free_shipping_%' OR option_name LIKE 'woocommerce_pickup_location_%' ORDER BY option_name", ARRAY_A) as $row) { $options[$row['option_name']] = $row['option_value']; }
    $out['commerce_settings'] = $hash($options);
    return $out;
};
if ($mode === 'snapshot') {
    if (file_exists($file)) { WP_CLI::error('Baseline exists; never overwrite.'); }
    $pages = get_posts(['post_type'=>'page','post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);
    file_put_contents($file, wp_json_encode(['hashes'=>$capture(),'pages'=>$pages,'options'=>wp_load_alloptions()], JSON_PRETTY_PRINT));
    echo "Preservation baseline saved privately.\n";
} elseif ($mode === 'verify') {
    $before = json_decode(file_get_contents($file),true)['hashes']; $now=$capture(); $checks=[];
    foreach ($before as $key=>$value) { $checks[$key] = hash_equals($value,$now[$key]); }
    echo wp_json_encode($checks,JSON_PRETTY_PRINT)."\n";
    if (in_array(false,$checks,true)) { exit(1); }
} else {
    $pages = [];
    foreach (get_posts(['post_type'=>'page','post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']) as $p) {
        $pages[] = ['id'=>$p->ID,'slug'=>$p->post_name,'title'=>$p->post_title,'status'=>$p->post_status,'content'=>$p->post_content,'template'=>get_page_template_slug($p)];
    }
    $options=[];
    foreach (['wp_page_for_privacy_policy','woocommerce_terms_page_id','woocommerce_registration_privacy_policy_text','woocommerce_checkout_privacy_policy_text','woocommerce_enable_guest_checkout','woocommerce_enable_myaccount_registration','woocommerce_enable_signup_and_login_from_checkout','woocommerce_erasure_request_removes_order_data','woocommerce_erasure_request_removes_download_data','woocommerce_allow_bulk_remove_personal_data','woocommerce_hold_stock_minutes','woocommerce_trash_pending_orders','woocommerce_trash_failed_orders','woocommerce_trash_cancelled_orders','woocommerce_anonymize_completed_orders','woocommerce_anonymize_refunded_orders','woocommerce_anonymize_inactive_accounts','woocommerce_store_address','woocommerce_store_address_2','woocommerce_store_city','woocommerce_store_postcode','woocommerce_default_country'] as $key) { $options[$key]=get_option($key); }
    echo wp_json_encode(['pages'=>$pages,'options'=>$options,'plugins'=>get_option('active_plugins'),'exporters'=>array_keys(apply_filters('wp_privacy_personal_data_exporters',[])),'erasers'=>array_keys(apply_filters('wp_privacy_personal_data_erasers',[]))],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}
