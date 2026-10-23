<?php
/** Claim only a fresh, local WordPress installation; never clone or sanitize commercial data. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { exit(1); }
$host = wp_parse_url(home_url(), PHP_URL_HOST);
if (!in_array($host, ['localhost','127.0.0.1'], true) || wp_parse_url(home_url(), PHP_URL_PORT) === 8080) { WP_CLI::error('Independent loopback installation required.'); }
if (!str_ends_with(get_option('admin_email'), '.test')) { WP_CLI::error('DEMO admin email must end in .test.'); }
$marker = get_option('commerce_h10_installation');
if ($marker === 'origen-casa') { WP_CLI::success('Existing H10 installation preserved.'); return; }
if ($marker) { WP_CLI::error('Another client owns this installation.'); }
global $wpdb;
$commercial = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation','shop_order','shop_order_refund')");
$users = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
foreach (['wc_orders','commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $table = $wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) { $commercial += (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`"); }
}
if ($commercial || $users !== 1) { WP_CLI::error('Fresh installation required; existing commercial data was not changed.'); }
update_option('commerce_h10_installation','origen-casa',false);
WP_CLI::success('Fresh isolated H10 installation claimed.');
