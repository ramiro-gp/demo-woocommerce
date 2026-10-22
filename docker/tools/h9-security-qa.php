<?php
/** Disposable clone only. Creates synthetic fixtures, never touches main or real payments. */
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || untrailingslashit(home_url())!=='http://localhost:8199'){exit(1);}
use CommerceCore\Legal;
global $wpdb;
$mode=$args[0] ?? 'fixtures';
if($mode==='restricted') {
    $fx=json_decode(file_get_contents('/backups/h9-fixtures.json'),true);
    if(!isset($fx['users']['restricted'])) {
        add_role('h9_legal_agent','H9 QA legal only',['read'=>true,'manage_woocommerce'=>true]);
        $password=wp_generate_password(32,true);$login='h9_legal_'.bin2hex(random_bytes(4));$id=wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'user_email'=>'h9.legal@example.test','role'=>'h9_legal_agent']);
        if(is_wp_error($id)){WP_CLI::error('Synthetic role creation failed');}
        $fx['users']['restricted']=['id'=>$id,'username'=>$login,'password'=>$password,'email'=>'h9.legal@example.test'];file_put_contents('/backups/h9-fixtures.json',wp_json_encode($fx));
    }
    wp_set_current_user($fx['users']['restricted']['id']);
    $token=WP_Session_Tokens::get_instance(get_current_user_id())->create(time()+HOUR_IN_SECONDS);
    $_COOKIE[LOGGED_IN_COOKIE]=wp_generate_auth_cookie(get_current_user_id(),time()+HOUR_IN_SECONDS,'logged_in',$token);
    $row=Legal::submit(['cc_name'=>'QA H9 restricted role','cc_email'=>'h9.restricted@example.test','cc_token'=>bin2hex(random_bytes(24))],'h9-restricted-'.bin2hex(random_bytes(4)));
    if(is_wp_error($row)){WP_CLI::error('Restricted synthetic case failed');}
    $fx['restricted_request']=['cookie_name'=>LOGGED_IN_COOKIE,'cookie'=>$_COOKIE[LOGGED_IN_COOKIE],'case'=>$row->id,'nonce'=>wp_create_nonce('cc_legal_manage_'.$row->id)];file_put_contents('/backups/h9-fixtures.json',wp_json_encode($fx));
    echo "Restricted synthetic legal role and session ready.\n";return;
}
if($mode==='verify-checkout') {
    $fx=json_decode(file_get_contents('/backups/h9-fixtures.json'),true);$data=json_decode(file_get_contents('/backups/h9-checkout.json'),true);$order=wc_get_order($data['order_id']);
    $expected='PRIVATE-customer_b-ONLY';$foreign=wc_get_order($fx['orders']['customer_b']['id']);
    $checks=['hpos'=>'yes'===get_option('woocommerce_custom_orders_table_enabled'),'bacs_on_hold'=>$order->has_status('on-hold')&&$order->get_payment_method()==='bacs','synthetic_buyer'=>$order->get_billing_email()==='h9.checkout@example.test','coupon_preserved'=>in_array($fx['coupon'],$order->get_coupon_codes(),true),'shipping_preserved'=>count($order->get_items('shipping'))===1,'native_stock_reduced'=>$order->get_data_store()->get_stock_reduced($order->get_id()),'foreign_order_unchanged'=>$foreign->get_billing_address_1()===$expected,'pro_disabled'=>'yes'!==(get_option('woocommerce_woo-mercado-pago-basic_settings')['enabled']??'')];
    echo wp_json_encode(['result'=>in_array(false,$checks,true)?'failed':'passed','checks'=>$checks],JSON_PRETTY_PRINT)."\n";if(in_array(false,$checks,true)){exit(1);}return;
}
if($mode==='log-probe') {
    $marker='H9-PRIVATE-PROBE-'.bin2hex(random_bytes(8));$log=WP_DEBUG_LOG;$offset=is_file($log)?filesize($log):0;
    $fail=static function($sql)use($wpdb){return str_starts_with($sql,'INSERT INTO '.Legal::table().' ')?str_replace(Legal::table(),$wpdb->prefix.'h9_missing_table',$sql):$sql;};
    add_filter('query',$fail);$result=Legal::submit(['cc_name'=>$marker,'cc_email'=>'h9.storage@example.test','cc_token'=>bin2hex(random_bytes(24))],'h9-storage');remove_filter('query',$fail);
    clearstatcache();$handle=is_file($log)?fopen($log,'r'):false;$new='';if($handle){fseek($handle,$offset);$new=stream_get_contents($handle);fclose($handle);}
    echo wp_json_encode(['storage_error_returned'=>is_wp_error($result),'private_marker_logged'=>str_contains($new,$marker),'scope'=>'isolated clone, query fault injection; log contents never exported'])."\n";return;
}
if($mode==='fixtures') {
    if(file_exists('/backups/h9-fixtures.json')){WP_CLI::error('Fixtures already exist; never overwrite');}
    $fx=['users'=>[],'orders'=>[]];
    foreach(['customer_a'=>'customer','customer_b'=>'customer','subscriber'=>'subscriber','manager'=>'shop_manager','admin'=>'administrator'] as $name=>$role){
        $password=wp_generate_password(32,true);$id=wp_insert_user(['user_login'=>'h9_'.$name.'_'.bin2hex(random_bytes(4)),'user_pass'=>$password,'user_email'=>'h9.'.$name.'@example.test','role'=>$role]);
        if(is_wp_error($id)){WP_CLI::error('Synthetic user creation failed');}
        $user=get_userdata($id);$fx['users'][$name]=['id'=>$id,'username'=>$user->user_login,'password'=>$password,'email'=>$user->user_email];
        if(str_starts_with($name,'customer')){$customer=new WC_Customer($id);$customer->set_billing_first_name('QA '.$name);$customer->set_billing_last_name('Aislado');$customer->set_billing_country('AR');$customer->set_billing_state('C');$customer->set_billing_city('Ciudad Autónoma de Buenos Aires');$customer->set_billing_postcode('1414');$customer->set_billing_address_1('QA '.$name.' 123');$customer->set_billing_phone('1155551234');$customer->save();
            $order=wc_create_order(['customer_id'=>$id]);$order->set_billing_email($user->user_email);$order->set_billing_first_name('QA '.$name);$order->set_billing_address_1('PRIVATE-'.$name.'-ONLY');$order->set_currency('ARS');$order->set_payment_method('bacs');$order->set_status('on-hold');$order->set_total(123);$order->update_meta_data('_h9_synthetic',true);$order->save();$fx['orders'][$name]=['id'=>$order->get_id(),'key'=>$order->get_order_key()];}
    }
    $product=new WC_Product_Simple();$product->set_name('H9 QA aislado');$product->set_sku('H9-QA-'.bin2hex(random_bytes(4)));$product->set_status('publish');$product->set_catalog_visibility('hidden');$product->set_regular_price('1000');$product->set_manage_stock(true);$product->set_stock_quantity(50);$product->set_weight('0.2');$product->set_length('10');$product->set_width('10');$product->set_height('1');$product->save();$fx['product']=$product->get_id();
    $coupon=new WC_Coupon();$coupon->set_code('h9qa'.bin2hex(random_bytes(3)));$coupon->set_discount_type('fixed_cart');$coupon->set_amount(100);$coupon->set_usage_limit(10);$coupon->save();$fx['coupon']=$coupon->get_code();
    // No outbound HTTP from WordPress during browser/security QA. Mail stays in clone Mailpit.
    if(!copy(__DIR__.'/h9-offline.php',WPMU_PLUGIN_DIR.'/h9-offline.php')){WP_CLI::error('Clone offline guard unavailable');}
    file_put_contents('/backups/h9-fixtures.json',wp_json_encode($fx));chmod('/backups/h9-fixtures.json',0600);
    echo wp_json_encode(['synthetic_users'=>count($fx['users']),'synthetic_orders'=>count($fx['orders']),'isolated_product'=>true,'isolated_coupon'=>true,'credentials_exported'=>false])."\n";return;
}
if($mode==='session-check') {
    $fx=json_decode(file_get_contents('/backups/h9-fixtures.json'),true);$id=$fx['users']['customer_a']['id'];wp_set_current_user($id);
    $token=WP_Session_Tokens::get_instance($id)->create(time()+300);$cookie=wp_generate_auth_cookie($id,time()+300,'logged_in',$token);
    $valid=wp_validate_auth_cookie($cookie,'logged_in')===$id;WP_Session_Tokens::get_instance($id)->destroy($token);
    $revoked=wp_validate_auth_cookie($cookie,'logged_in')===false;
    $expired=wp_validate_auth_cookie(wp_generate_auth_cookie($id,time()-HOUR_IN_SECONDS,'logged_in',$token),'logged_in')===false;
    echo wp_json_encode(['valid_cookie'=>$valid,'revoked_cookie_rejected'=>$revoked,'expired_cookie_rejected'=>$expired])."\n";return;
}
if($mode==='mp-source') {
    $base=WP_PLUGIN_DIR.'/woocommerce-mercadopago/';$files=[];
    foreach(['woocommerce-mercadopago.php','src/Notification/AbstractNotification.php','src/Notification/CoreNotification.php','src/Notification/IpnNotification.php','src/Notification/WebhookNotification.php','src/Hooks/Scripts.php','src/Helpers/PaymentMetadata.php','src/Order/OrderStatus.php'] as $file){$files[$file]=hash_file('sha256',$base.$file);}
    echo wp_json_encode(['version'=>'8.9.6','files'=>$files],JSON_PRETTY_PRINT)."\n";
}
