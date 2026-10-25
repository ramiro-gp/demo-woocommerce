<?php
/** Configure only a new, synthetic D1-content clone. No historical shop accepts this. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local'
    || !in_array(home_url(),['http://localhost:8192','http://localhost:8193'],true)
    || !defined('COMMERCE_DEMO_PUBLIC') || COMMERCE_DEMO_PUBLIC!==false
    || get_option('commerce_d1_installation')!=='d1-public-demo-lab'
    || get_option('commerce_seller_lab')) { exit(1); }
global $wpdb;
$users=get_users();
if(count($users)!==1 || !str_ends_with($users[0]->user_email,'.test')) { WP_CLI::error('Only a clean synthetic D1 source is permitted.'); }
foreach(['wc_orders','woocommerce_order_items','commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $t=$wpdb->prefix.$suffix;
    if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t)))===$t && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$t`")) { WP_CLI::error('History not permitted in seller clone.'); }
}
if((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund','shop_order_placehold')") || is_dir(WP_PLUGIN_DIR.'/woocommerce-mercadopago')) { WP_CLI::error('No order history or Mercado Pago is permitted.'); }
$brand=wp_parse_url(home_url(),PHP_URL_PORT)===8192?'trama':'origen';
$access=['installation'=>'d1-1-seller-audit','base'=>home_url(),'mail'=>'http://localhost:'.($brand==='trama'?8194:8195),'users'=>[],'products'=>[]];
foreach(['admin'=>'administrator','manager'=>'shop_manager','customer'=>'customer'] as $key=>$role) {
    $password=trim(file_get_contents('/run/secrets/'.$key));$username='qa_'.$key.'_'.bin2hex(random_bytes(5));
    $email=$key.'@'.$brand.'.seller.test';
    $id=wp_insert_user(['user_login'=>$username,'user_pass'=>$password,'user_email'=>$email,'role'=>$role,'display_name'=>['admin'=>'Administrador QA','manager'=>'Vendedor QA','customer'=>'Cliente QA'][$key]]);
    if(is_wp_error($id)){WP_CLI::error('Cannot create independent synthetic operator.');}
    $access['users'][$key]=['id'=>$id,'username'=>$username,'password'=>$password,'email'=>$email];
}
require_once ABSPATH.'wp-admin/includes/user.php';
// Only the one fictitious admin imported above; original installations are never targeted.
if(!wp_delete_user($users[0]->ID,$access['users']['admin']['id'])){WP_CLI::error('Could not replace imported synthetic operator.');}
$settings=[
    'commerce_seller_lab'=>'d1-1-seller-audit','users_can_register'=>'0','blog_public'=>'0','show_avatars'=>'0',
    'admin_email'=>$access['users']['admin']['email'],'woocommerce_currency'=>'ARS',
    'woocommerce_price_thousand_sep'=>'.','woocommerce_price_decimal_sep'=>',','woocommerce_price_num_decimals'=>'0',
    'woocommerce_enable_guest_checkout'=>'yes','woocommerce_enable_myaccount_registration'=>'yes','woocommerce_enable_signup_and_login_from_checkout'=>'yes',
    'woocommerce_registration_generate_password'=>'yes','woocommerce_registration_generate_username'=>'yes',
    'woocommerce_default_country'=>'AR:C','woocommerce_store_address'=>'Calle ficticia QA 123','woocommerce_store_city'=>'Ciudad Autónoma de Buenos Aires','woocommerce_store_postcode'=>'1414',
    'woocommerce_allowed_countries'=>'specific','woocommerce_specific_allowed_countries'=>['AR'],'woocommerce_ship_to_countries'=>'specific','woocommerce_specific_ship_to_countries'=>['AR'],
    'woocommerce_bacs_settings'=>['enabled'=>'yes','title'=>'Transferencia bancaria · QA','description'=>'Laboratorio ficticio. No realices transferencias.','instructions'=>'Pedido de prueba. No existe cuenta bancaria ni se recibe dinero.'],
    'woocommerce_bacs_accounts'=>[],'woocommerce_cod_settings'=>['enabled'=>'no'],'woocommerce_cheque_settings'=>['enabled'=>'no'],
    'woocommerce_manage_stock'=>'yes','woocommerce_notify_low_stock'=>'yes','woocommerce_notify_no_stock'=>'yes','woocommerce_notify_low_stock_amount'=>'2',
    'woocommerce_stock_email_recipient'=>'seller@'.$brand.'.seller.test','woocommerce_hold_stock_minutes'=>'60',
    'woocommerce_email_from_name'=>get_bloginfo('name').' · QA','woocommerce_email_from_address'=>'store@'.$brand.'.seller.test',
    'woocommerce_custom_orders_table_enabled'=>'yes','woocommerce_custom_orders_table_data_sync_enabled'=>'no',
    'woocommerce_feature_order_attribution_enabled'=>'no','woocommerce_coming_soon'=>'no','woocommerce_store_pages_only'=>'no',
    'timezone_string'=>'America/Argentina/Buenos_Aires','woocommerce_onboarding_profile'=>['completed'=>true],
];
foreach($settings as $k=>$v){update_option($k,$v);}
foreach(WC()->mailer()->get_emails() as $email){$option=get_option('woocommerce_'.$email->id.'_settings',[]);$option['enabled']='yes';$option['recipient']='seller@'.$brand.'.seller.test';update_option('woocommerce_'.$email->id.'_settings',$option);}
$legal=\CommerceCore\Legal::config();$legal['notification_email']='seller@'.$brand.'.seller.test';$legal['email']='contacto@'.$brand.'.seller.test';
update_option('commerce_core_legal',$legal);
$withdrawal=(int)($legal['pages']['withdrawal']??0);
if(!$withdrawal){WP_CLI::error('Existing synthetic legal page required.');}
wp_update_post(wp_slash(['ID'=>$withdrawal,'post_content'=>'<!-- wp:shortcode --><div class="cb-container cc-legal"><h1>Solicitud QA · laboratorio del vendedor</h1><p>Datos exclusivamente ficticios .test. Sin trámites reales ni correos externos.</p>[commerce_legal_form]</div><!-- /wp:shortcode -->']));
// Source D1 zones contain only fictitious settings. Replace only inside this new clone.
foreach(WC_Shipping_Zones::get_zones() as $row){$z=new WC_Shipping_Zone($row['zone_id']);$z->delete();}
$zone=new WC_Shipping_Zone();$zone->set_zone_name('QA Argentina');$zone->add_location('AR','country');$zone->save();
foreach(['flat_rate'=>['title'=>'Envío QA','cost'=>'1500','tax_status'=>'none'],'free_shipping'=>['title'=>'Envío gratis QA','requires'=>'min_amount','min_amount'=>'60000','ignore_discounts'=>'no'],'local_pickup'=>['title'=>'Retiro QA','cost'=>'0','tax_status'=>'none']] as $type=>$option){$method=$zone->add_shipping_method($type);update_option('woocommerce_'.$type.'_'.$method.'_settings',$option);}
foreach(['shop','cart','checkout','myaccount'] as $key){$access['pages'][$key]=wc_get_page_id($key);}
$access['pages']['withdrawal']=$withdrawal;$access['shipping_zone']=$zone->get_id();
if(file_exists('/seller-private/access.json')){WP_CLI::error('Private state already exists.');}
file_put_contents('/seller-private/access.json',wp_json_encode($access,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));chmod('/seller-private/access.json',0600);
flush_rewrite_rules(false);
WP_CLI::success('Independent seller lab configured. Access remains private; Mailpit only; no real payment.');
