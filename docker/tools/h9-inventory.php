<?php
/** Read-only inventory and preservation fingerprints. Never emits customer or secret values. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { exit(1); }
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugins=[];
foreach (get_plugins() as $file=>$data) {
    $plugins[]=['file'=>$file,'version'=>$data['Version'],'active'=>is_plugin_active($file),'requires_php'=>$data['RequiresPHP'] ?? '', 'requires_wp'=>$data['RequiresWP'] ?? ''];
}
$options=[];
foreach (['blog_public','woocommerce_custom_orders_table_enabled','woocommerce_feature_order_attribution_enabled','woocommerce_allow_tracking','woocommerce_enable_guest_checkout','woocommerce_enable_checkout_login_reminder','woocommerce_enable_signup_and_login_from_checkout','woocommerce_registration_generate_password'] as $key) { $options[$key]=get_option($key); }
$gateways=[]; foreach(WC()->payment_gateways()->payment_gateways() as $id=>$gateway) { $gateways[$id]=['enabled'=>$gateway->enabled]; }
$cron=[]; foreach(_get_cron_array() as $events) { foreach($events as $hook=>$entries) { $cron[$hook]=($cron[$hook] ?? 0)+count($entries); } } ksort($cron);
$salts=[]; foreach(['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key) { $salts[$key]=defined($key) && strlen(constant($key))>=32 && constant($key)!=='put your unique phrase here'; }
$constants=[];foreach(['WP_DEBUG','WP_DEBUG_DISPLAY','WP_DEBUG_LOG','DISALLOW_FILE_EDIT','WP_AUTO_UPDATE_CORE','AUTOMATIC_UPDATER_DISABLED','FORCE_SSL_ADMIN'] as $key) { $constants[$key]=defined($key)?constant($key):null; }
$tables=[];foreach(['posts','postmeta','users','usermeta','wc_orders','wc_order_addresses','wc_orders_meta','woocommerce_order_items','woocommerce_order_itemmeta','commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $table=$wpdb->prefix.$suffix;
    $columns=$wpdb->get_col("SHOW COLUMNS FROM `$table`");
    if (!$columns) { continue; }
    // Stable order for hashes; no database rows leave this process.
    $rows=$wpdb->get_results("SELECT * FROM `$table` ORDER BY `".$columns[0].'`',ARRAY_A);
    $tables[$suffix]=['rows'=>count($rows),'sha256'=>hash('sha256',wp_json_encode($rows))];
}
$autoload=$wpdb->get_row("SELECT COUNT(*) count,COALESCE(SUM(LENGTH(option_value)),0) bytes FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')",ARRAY_A);
$legal=[];foreach(['requests','events','limits'] as $suffix) {$t=$wpdb->prefix.'commerce_legal_'.$suffix;$legal[$suffix]=$wpdb->get_results("SHOW INDEX FROM `$t`",ARRAY_A);}
$roles=[];foreach(wp_roles()->roles as $name=>$role) { $roles[$name]=array_values(array_intersect(['manage_options','manage_woocommerce','edit_shop_orders','edit_products','edit_pages','list_users'],array_keys(array_filter($role['capabilities'])))); }
$theme=wp_get_theme();
echo wp_json_encode(['generated_utc'=>gmdate('c'),'site'=>home_url(),'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'database'=>$wpdb->get_var('SELECT VERSION()'),'theme'=>['name'=>$theme->get('Name'),'version'=>$theme->get('Version'),'parent'=>$theme->get('Template')],'plugins'=>$plugins,'mu_plugins'=>array_keys(get_mu_plugins()),'options'=>$options,'gateways'=>$gateways,'constants'=>$constants,'salts_valid'=>$salts,'roles'=>$roles,'cron_hooks'=>$cron,'autoload'=>$autoload,'legal_indexes'=>$legal,'preservation'=>$tables],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
