<?php
/** Synthetic H10 data in the new installation only. Credentials stay in ignored /backups. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || get_option('commerce_h10_installation') !== 'origen-casa') { exit(1); }
if ((int)wp_parse_url(home_url(),PHP_URL_PORT) === 8080) { exit(1); }
global $wpdb;
$mode=$args[0]??'verify';$private='/backups/h10-private.json';
if($mode==='resend-receipt'){
    $checkout=json_decode(file_get_contents('/backups/h10-checkout.json'),true);$qa=json_decode(file_get_contents($private),true);$order=wc_get_order($checkout['order_id']);
    if(!$order||$order->get_payment_method()!=='bacs'||!$order->has_status('on-hold')||(int)$order->get_customer_id()!==(int)$qa['users']['customer']['id']){WP_CLI::error('Only this synthetic BACS order may be used for email QA.');}
    WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order']->trigger($order->get_id(),$order);
    WP_CLI::success('Native customer receipt retriggered to the isolated Mailpit; no status or stock operation.');return;
}
if ($mode==='prepare') {
    if(file_exists($private)){WP_CLI::success('QA credentials preserved privately.');return;}
    $run=bin2hex(random_bytes(6));$users=[];
    foreach(['customer'=>'customer','manager'=>'shop_manager'] as $key=>$role){
        $username='h10_'.$key.'_'.$run;$password=wp_generate_password(36,true,true);
        $id=wp_insert_user(['user_login'=>$username,'user_pass'=>$password,'user_email'=>$username.'@origen.example.test','role'=>$role,'display_name'=>'QA ORIGEN DEMO']);
        if(is_wp_error($id)){WP_CLI::error('Unable to create synthetic QA user.');}
        $users[$key]=['id'=>$id,'username'=>$username,'password'=>$password,'email'=>$username.'@origen.example.test'];
    }
    $products=wc_get_products(['limit'=>-1,'status'=>'publish']);$simple=0;$variable=0;
    foreach($products as $product){if($product->is_type('simple')&&$product->is_in_stock()&&!$simple){$simple=$product->get_id();}if($product->is_type('variable')&&!$variable){$variable=$product->get_id();}}
    $coupon=new WC_Coupon();$coupon->set_code('origen-qa-'.$run);$coupon->set_discount_type('fixed_cart');$coupon->set_amount('1500');$coupon->set_usage_limit(5);$coupon->save();
    $pages=\CommerceCore\Legal::config()['pages'];foreach(['shop','cart','checkout','myaccount'] as $kind){$pages[$kind]=wc_get_page_id($kind);}
    file_put_contents($private,wp_json_encode(['run'=>$run,'users'=>$users,'simple'=>$simple,'variable'=>$variable,'coupon'=>$coupon->get_code(),'pages'=>$pages],JSON_PRETTY_PRINT));
    WP_CLI::success('Synthetic customer/manager/coupon prepared. Passwords remain private.');return;
}
if($mode==='isolation-mutations'){
    $file=json_decode(file_get_contents($private),true);$product=wc_get_product($file['simple']);$front=(int)get_option('page_on_front');$legal=(int)\CommerceCore\Legal::config()['pages']['privacy'];
    $price=$product->get_regular_price();$home=get_post($front);$policy=get_post($legal);
    $proof=[];
    if(file_exists('/backups/h10-mutations-private.json')){WP_CLI::error('Restore the previous isolated mutation before starting another.');}
    if(!file_put_contents('/backups/h10-mutations-private.json',wp_json_encode(['product'=>$product->get_id(),'price'=>$price,'home'=>$home,'legal'=>$policy]))){WP_CLI::error('Unable to save private recovery data; no mutation performed.');}
    try{
        $product->set_regular_price((string)((float)$price+321));$product->save();
        wp_update_post(wp_slash(['ID'=>$front,'post_content'=>$home->post_content."\n<!-- H10 isolated Home edit DEMO -->"]));
        wp_update_post(wp_slash(['ID'=>$legal,'post_content'=>$policy->post_content."\n<!-- H10 isolated legal edit DEMO -->"]));
        $proof=['price_changed'=>wc_get_product($product->get_id())->get_regular_price()!==$price,'home_changed'=>get_post_field('post_content',$front)!==$home->post_content,'legal_changed'=>get_post_field('post_content',$legal)!==$policy->post_content];
        // Root captures TRAMA fingerprints while this stage is persisted.
        if(in_array(false,$proof,true)){throw new RuntimeException('An isolation mutation did not persist.');}
        echo wp_json_encode(['result'=>'passed','mutations'=>$proof],JSON_PRETTY_PRINT)."\n";
    }catch(Throwable $e){$product->set_regular_price($price);$product->save();wp_update_post(wp_slash((array)$home));wp_update_post(wp_slash((array)$policy));throw $e;}
    return;
}
if($mode==='restore-mutations'){
    $restore=json_decode(file_get_contents('/backups/h10-mutations-private.json'),true);$product=wc_get_product($restore['product']);$product->set_regular_price($restore['price']);$product->save();wp_update_post(wp_slash($restore['home']));wp_update_post(wp_slash($restore['legal']));unlink('/backups/h10-mutations-private.json');WP_CLI::success('Only the three synthetic Origen changes restored.');return;
}
if($mode==='verify'){
    $checks=[];$record=static function($name,$ok)use(&$checks){$checks[$name]=(bool)$ok;};
    $record('theme_shared',get_stylesheet()==='commerce-base');
    $record('core_active',class_exists('CommerceCore\\Legal'));
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    $record('mercadopago_inactive',!is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php'));
    $record('noindex',(int)get_option('blog_public')===0);
    $record('ars',get_option('woocommerce_currency')==='ARS');
    $config=\CommerceCore\Legal::config();$record('no_fake_fiscal',empty($config['cuit'])&&empty($config['fiscal_url'])&&empty($config['fiscal_image']));
    $record('own_legal_identity',str_contains($config['trade_name']??'','ORIGEN'));
    $products=wc_get_products(['limit'=>-1]);$variables=0;$clothing=false;$trama=false;$soldout=false;$sale=false;
    foreach($products as $p){$trama=$trama||str_contains(strtolower($p->get_sku()),'trama');$soldout=$soldout||!$p->is_in_stock();$sale=$sale||$p->is_on_sale();if($p->is_type('variable')){$variables++;foreach($p->get_variation_attributes() as $key=>$value){$clothing=$clothing||str_contains($key,'talle');}}}
    $record('independent_catalog',count($products)>=8&&count($products)<=12&&!$trama);$record('two_non_apparel_variables',$variables>=2&&!$clothing);$record('soldout',$soldout);$record('sale',$sale);
    $record('demo_sender',str_ends_with(get_option('woocommerce_email_from_address'),'.test'));
    $orders=wc_get_orders(['limit'=>-1,'return'=>'objects']);$bacs=array_values(array_filter($orders,static fn($o)=>$o->get_payment_method()==='bacs'&&$o->has_status('on-hold')));
    $record('bacs_order',count($bacs)>0);
    $qa=json_decode(file_get_contents($private),true);
    $record('bacs_customer_owns_order',count(array_filter($bacs,static fn($o)=>(int)$o->get_customer_id()===(int)$qa['users']['customer']['id']))>0);
    $record('native_hpos',get_option('woocommerce_custom_orders_table_enabled')==='yes');
    $record('legal_request',(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\CommerceCore\Legal::table())>0);
    echo wp_json_encode(['result'=>in_array(false,$checks,true)?'failed':'passed','checks'=>$checks,'products'=>count($products),'variables'=>$variables,'orders'=>count($orders),'bacs_on_hold'=>count($bacs),'real_payment'=>false],JSON_PRETTY_PRINT)."\n";
    if(in_array(false,$checks,true)){exit(1);}
}
