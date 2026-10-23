<?php
/** Lab only: prevent restore over any new users, posts, orders or legal requests. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || get_option('commerce_h11_installation') !== 'h11-production-simulation' || wp_parse_url(home_url(),PHP_URL_HOST)!=='localhost' || !in_array((int)wp_parse_url(home_url(),PHP_URL_PORT),[8184,8185,8188],true)) { exit(1); }
global $wpdb;
$mode=$args[0]??'verify';
if (!in_array($mode,['snapshot','verify'],true)) { WP_CLI::error('Invalid lab guard mode.'); }
$state=[];
foreach (['users','usermeta','posts','postmeta','wc_orders','wc_order_addresses','wc_orders_meta','wc_order_operational_data','woocommerce_order_items','woocommerce_order_itemmeta','commerce_legal_requests','commerce_legal_events','commerce_legal_limits'] as $suffix) {
    $table=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) { continue; }
    $rows=array_map('wp_json_encode',$wpdb->get_results('SELECT * FROM `'.str_replace('`','``',$table).'`',ARRAY_A));
    sort($rows,SORT_STRING); $state[$suffix]=hash('sha256',implode("\n",$rows));
}
foreach (['commerce_core_legal','woocommerce_currency','woocommerce_bacs_settings','blogname','blog_public','commerce_distribution_initialized','commerce_h11_installation'] as $key) { $state['option:'.$key]=get_option($key); }
$digest=hash('sha256',wp_json_encode($state));
$path='/h11-backups/pristine.json';
if ($mode==='snapshot') {
    if (file_exists($path)) { WP_CLI::error('Pristine guard exists; refusing to replace it.'); }
    file_put_contents($path,wp_json_encode(['digest'=>$digest]));
} else {
    $stored=is_file($path)?json_decode(file_get_contents($path),true):[];
    if (empty($stored['digest']) || !hash_equals($stored['digest'],$digest)) { WP_CLI::error('Destination differs from its pristine install. Restore refused; preserve all data.'); }
}
WP_CLI::success('Pristine lab guard '.$mode.' passed.');
