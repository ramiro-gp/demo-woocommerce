<?php
/** D1 sanitized lab assertions. Never displays credential, address, email or nonce values. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || get_option('commerce_d1_installation') !== 'd1-public-demo-lab' || wp_parse_url(home_url(), PHP_URL_HOST) !== 'localhost' || !in_array((int) wp_parse_url(home_url(), PHP_URL_PORT), [8190,8191], true)) { exit(1); }
global $wpdb;
$checks=[];
$check=static function($name,$value)use(&$checks){$checks[$name]=(bool)$value;};
$check('public_module_enabled',function_exists('CommerceDemoPublic\\enabled') && \CommerceDemoPublic\enabled());
$check('public_module_configuration_valid',function_exists('CommerceDemoPublic\\configuration_valid') && \CommerceDemoPublic\configuration_valid());
$check('commerce_base_active',get_stylesheet()==='commerce-base');
$check('wordpress_7_1_3',get_bloginfo('version')==='7.1.3');
$check('woocommerce_11_2_1',defined('WC_VERSION')&&WC_VERSION==='11.2.1');
$check('hpos_enabled',get_option('woocommerce_custom_orders_table_enabled')==='yes');
$check('noindex',(string)get_option('blog_public')==='0');
$check('public_registration_disabled',!get_option('users_can_register') && get_option('woocommerce_enable_myaccount_registration')==='no' && get_option('woocommerce_enable_signup_and_login_from_checkout')==='no');
$check('no_payment_gateways',class_exists('WooCommerce') && WC()->payment_gateways()->get_available_payment_gateways()===[]);
$check('native_gateways_persistently_disabled',!array_filter(['bacs','cod','cheque'],static fn($id)=>(get_option('woocommerce_'.$id.'_settings',[])['enabled']??'no')==='yes'));
$users=get_users();
$check('only_initial_administrator',count($users)===1 && in_array('administrator',$users[0]->roles,true));
$check('only_synthetic_user_emails',!array_filter($users,static fn($u)=>!preg_match('/@[^@]+\.test$/D',$u->user_email)));
$check('no_visitor_order_in_any_hpos_status',count(wc_get_orders(['limit'=>-1,'return'=>'ids','status'=>array_keys(wc_get_order_statuses())]))===0);
$counts=[];
foreach(['wc_orders','woocommerce_order_items','commerce_legal_requests','commerce_legal_events'] as $suffix){
    $table=$wpdb->prefix.$suffix;
    $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;
    $counts[$suffix]=$exists?(int)$wpdb->get_var('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`'):0;
    $check('no_records_'.$suffix,$counts[$suffix]===0);
}
$legacy=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund','shop_order_placehold')");
$check('no_legacy_orders_or_placeholders',$legacy===0);
$check('mercadopago_absent',!is_dir(WP_PLUGIN_DIR.'/woocommerce-mercadopago'));
$check('no_bank_accounts',empty(get_option('woocommerce_bacs_accounts')));
$legal=(array)get_option('commerce_core_legal',[]);
$check('no_real_fiscal_identity',empty($legal['cuit'])&&empty($legal['fiscal_url'])&&empty($legal['fiscal_image']));
$check('synthetic_admin_email',(bool)preg_match('/@[^@]+\.test$/D',get_option('admin_email')));
$products=wc_get_products(['limit'=>-1,'status'=>'publish']);
$check('products_preserved',count($products)>=2);
$check('simple_and_variable_preserved',count(array_filter($products,static fn($p)=>$p->is_type('variable')))>0&&count(array_filter($products,static fn($p)=>$p->is_type('simple')))>0);
$uploads=wp_upload_dir();
// Fingerprints prove environment separation without publishing config, salts or credentials.
$isolation=['database_sha256'=>hash('sha256',DB_HOST.'|'.DB_NAME.'|'.DB_USER),'salts_sha256'=>hash('sha256',wp_salt('auth').'|'.wp_salt('nonce')),'admin_password_hash_sha256'=>hash('sha256',$users[0]->user_pass),'uploads_realpath'=>realpath($uploads['basedir']),'upload_origin'=>wp_parse_url($uploads['baseurl'],PHP_URL_HOST).':'.wp_parse_url($uploads['baseurl'],PHP_URL_PORT)];
$report=['result'=>in_array(false,$checks,true)?'failed':'passed','origin'=>home_url(),'checks'=>$checks,'counts'=>$counts,'product_count'=>count($products),'user_count'=>count($users),'isolation'=>$isolation,'real_mail'=>false,'real_payments'=>false];
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
if(in_array(false,$checks,true)){WP_CLI::halt(1);}
