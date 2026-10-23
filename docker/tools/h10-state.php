<?php
/** Read-only fingerprints: no private rows, credentials or email addresses leave WordPress. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { exit(1); }
global $wpdb;
$tables = [];
foreach (['posts','postmeta','users','usermeta','wc_orders','wc_order_addresses','wc_orders_meta','woocommerce_order_items','woocommerce_order_itemmeta','commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $table=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) !== $table) { continue; }
    $columns=$wpdb->get_col("SHOW COLUMNS FROM `$table`");
    $rows=$wpdb->get_results("SELECT * FROM `$table` ORDER BY `{$columns[0]}`",ARRAY_A);
    $tables[$suffix]=['count'=>count($rows),'sha256'=>hash('sha256',wp_json_encode($rows))];
}
$configuration=[];
foreach (['blogname','blogdescription','show_on_front','page_on_front','stylesheet','template','theme_mods_commerce-base','commerce_core_legal','commerce_base_options','woocommerce_currency','woocommerce_email_from_address','woocommerce_email_from_name','woocommerce_email_base_color','woocommerce_bacs_settings','woocommerce_bacs_accounts','woocommerce_terms_page_id','wp_page_for_privacy_policy'] as $key) { $configuration[$key]=get_option($key); }
$upload=wp_upload_dir();$files=[];
if (is_dir($upload['basedir'])) {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload['basedir'],FilesystemIterator::SKIP_DOTS)) as $file) {
        if($file->isFile()){$files[substr($file->getPathname(),strlen($upload['basedir']))]=hash_file('sha256',$file->getPathname());}
    }
}
ksort($files);
$salts=[];foreach(['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key){$salts[$key]=defined($key)?hash('sha256',constant($key)):null;}
$identities=[];foreach(get_users(['role'=>'customer']) as $customer){$identities[]=hash('sha256',strtolower($customer->user_email));}sort($identities);
$code=[];foreach(['themes/commerce-base','plugins/commerce-core','plugins/commerce-lookbook','plugins/commerce-communications','plugins/commerce-account-navigation','plugins/commerce-order-integrity'] as $module){
    $base=WP_CONTENT_DIR.'/'.$module;$module_files=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS)) as $file){if($file->isFile()){$module_files[substr($file->getPathname(),strlen($base))]=hash_file('sha256',$file->getPathname());}}
    ksort($module_files);$code[$module]=hash('sha256',wp_json_encode($module_files));
}
$result=['site'=>home_url(),'database'=>DB_NAME,'tables'=>$tables,'configuration_sha256'=>hash('sha256',wp_json_encode($configuration)),'uploads'=>['count'=>count($files),'sha256'=>hash('sha256',wp_json_encode($files))],'salts_sha256'=>$salts,'products'=>count(wc_get_products(['limit'=>-1,'return'=>'ids'])),'customer_count'=>count($identities),'shared_code_sha256'=>$code];
// Only a private coordinator comparison needs per-customer fingerprints.
if(($args[0]??'')==='private'){$result['customer_identities_sha256']=$identities;}
echo wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
