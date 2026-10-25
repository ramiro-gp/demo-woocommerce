<?php
/** D1.1 own synthetic order fixtures/facts. Never accepts the historical shops or public D1 labs. */
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'||home_url()!=='http://localhost:8193'||get_option('commerce_seller_lab')!=='d1-1-seller-audit'){exit(1);}
if(!class_exists('WooCommerce')||!class_exists('CommerceCore\\Legal')){WP_CLI::error('Approved seller lab required.');}
$mode=$args[0]??'snapshot';
if(!in_array($mode,['seed','snapshot','sync-analytics','reservation-probe'],true)){WP_CLI::error('Use seed|snapshot|sync-analytics|reservation-probe.');}
function seller_orders_fail($condition,$message){if(!$condition){WP_CLI::error($message);}}
require_once ABSPATH.'wp-admin/includes/plugin.php';
seller_orders_fail(!is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php'),'Mercado Pago must stay absent/inactive.');
seller_orders_fail(!defined('COMMERCE_DEMO_PUBLIC')||COMMERCE_DEMO_PUBLIC===false,'Public DEMO guard must remain unchanged and OFF in this separate lab.');
$fixture=(array)get_option('commerce_seller_orders_fixture',[]);
if($mode==='seed'&&!$fixture){
    seller_orders_fail(!wc_get_product_id_by_sku('D11O-SIMPLE')&&!wc_get_product_id_by_sku('D11O-VARIABLE'),'Own QA SKU collision; preserving lab.');
    foreach(get_users() as $user){seller_orders_fail((bool)preg_match('/@[^@]+\.test$/D',$user->user_email),'Synthetic lab users only.');}
    $imageIds=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'fields'=>'ids']);$imageId=(int)($imageIds[0]??0);
    $term=wp_insert_term('QA vendedor D1.1','product_cat',['slug'=>'d11o-qa']);
    if(is_wp_error($term)&&$term->get_error_code()==='term_exists'){$category=(int)$term->get_error_data();}else{seller_orders_fail(!is_wp_error($term),'Cannot create own QA category.');$category=(int)$term['term_id'];}
    $simple=new WC_Product_Simple();$simple->set_props(['name'=>'QA vendedor · Objeto D11O','slug'=>'qa-vendedor-objeto-d11o','sku'=>'D11O-SIMPLE','status'=>'publish','regular_price'=>'10000','manage_stock'=>true,'stock_quantity'=>8,'low_stock_amount'=>6,'stock_status'=>'instock','weight'=>'0.5','image_id'=>$imageId,'category_ids'=>[$category],'description'=>'<p>Producto ficticio, exclusivo del laboratorio local del vendedor. No hay pagos ni entregas reales.</p>','short_description'=>'QA D1.1 · objeto de decoración ficticio.']);$simpleId=$simple->save();
    $attributeId=wc_create_attribute(['name'=>'Terminación QA','slug'=>'d11o-terminacion','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);
    seller_orders_fail(!is_wp_error($attributeId),'Cannot create own QA attribute.');
    $taxonomy='pa_d11o-terminacion';register_taxonomy($taxonomy,['product'],['hierarchical'=>false,'label'=>'Terminación QA']);
    $options=[];foreach(['Roble QA'=>'roble-qa','Nogal QA'=>'nogal-qa'] as $name=>$slug){$t=wp_insert_term($name,$taxonomy,['slug'=>$slug]);seller_orders_fail(!is_wp_error($t),'Cannot create QA terms.');$options[]=(int)$t['term_id'];}
    delete_transient('wc_attribute_taxonomies');
    $attribute=new WC_Product_Attribute();$attribute->set_id($attributeId);$attribute->set_name($taxonomy);$attribute->set_options($options);$attribute->set_visible(true);$attribute->set_variation(true);
    $variable=new WC_Product_Variable();$variable->set_props(['name'=>'QA vendedor · Lámpara D11O','slug'=>'qa-vendedor-lampara-d11o','sku'=>'D11O-VARIABLE','status'=>'publish','attributes'=>[$attribute],'image_id'=>$imageId,'category_ids'=>[$category],'description'=>'<p>Variaciones ficticias de terminación para auditoría de pedidos.</p>','short_description'=>'QA D1.1 · lámpara ficticia.']);$variableId=$variable->save();$variations=[];
    foreach(['roble-qa','nogal-qa'] as $i=>$value){$variation=new WC_Product_Variation();$variation->set_props(['parent_id'=>$variableId,'status'=>'publish','sku'=>$i?'D11O-NOGAL':'D11O-ROBLE','attributes'=>[$taxonomy=>$value],'regular_price'=>$i?'19000':'18000','manage_stock'=>true,'stock_quantity'=>$i?4:6,'low_stock_amount'=>2,'stock_status'=>'instock','weight'=>'1','image_id'=>$imageId]);$variations[$i?'nogal':'roble']=$variation->save();}
    WC_Product_Variable::sync($variableId);
    $coupon=new WC_Coupon();$coupon->set_code('d11o-qa10');$coupon->set_discount_type('percent');$coupon->set_amount(10);$coupon->set_description('Fixture QA local de pedidos/Analytics; no promoción comercial.');$coupon->set_usage_limit(10);$couponId=$coupon->save();
    foreach(['woocommerce_notify_low_stock'=>'yes','woocommerce_notify_no_stock'=>'yes','woocommerce_stock_email_recipient'=>get_option('admin_email'),'woocommerce_hold_stock_minutes'=>'60'] as $key=>$value){update_option($key,$value);}
    $fixture=['installation'=>'d1-1-seller-audit','origin'=>home_url(),'products'=>['simple'=>$simpleId,'variable'=>$variableId,'variations'=>$variations],'coupon'=>['id'=>$couponId,'code'=>'d11o-qa10'],'initial_stock'=>['simple'=>8,'roble'=>6,'nogal'=>4]];
    update_option('commerce_seller_orders_fixture',$fixture,false);
}
seller_orders_fail(!empty($fixture['products']['simple']),'Seed own QA fixture first.');
$ownProducts=array_merge([(int)$fixture['products']['simple'],(int)$fixture['products']['variable']],array_map('intval',$fixture['products']['variations']));
$reservationProbe=null;
if($mode==='reservation-probe'){
    global $wpdb;$product=wc_get_product($fixture['products']['simple']);$physicalBefore=$product->get_stock_quantity();$probe=null;
    try{
        $probe=wc_create_order(['status'=>'pending']);seller_orders_fail($probe instanceof WC_Order&&$probe->get_id(),'Cannot create own temporary pending QA probe.');
        $probe->add_product($product,1);$probe->set_billing_email('reservation@seller.example.test');$probe->add_meta_data('_seller_reservation_probe','D11O',true);$probe->calculate_totals();$probe->save();
        wc_reserve_stock_for_order($probe);
        $rows=$wpdb->get_results($wpdb->prepare('SELECT order_id,product_id,stock_quantity FROM '.$wpdb->prefix.'wc_reserved_stock WHERE order_id=%d',$probe->get_id()),ARRAY_A);
        $held=wc_get_held_stock_quantity($product);wc_release_stock_for_order($probe);
        $remaining=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'wc_reserved_stock WHERE order_id=%d',$probe->get_id()));
        $physicalAfter=wc_get_product($product->get_id())->get_stock_quantity();
        $reservationProbe=['native_api_only'=>true,'reserved_rows'=>$rows,'held_quantity'=>(int)$held,'released'=>$remaining===0,'physical_before'=>$physicalBefore,'physical_after'=>$physicalAfter,'physical_stock_unchanged'=>$physicalBefore===$physicalAfter];
    }finally{if($probe instanceof WC_Order&&$probe->get_id()){wc_release_stock_for_order($probe);$probe->delete(true);}}
}
$orders=[];
foreach(wc_get_orders(['limit'=>-1,'type'=>'shop_order','return'=>'objects','status'=>array_keys(wc_get_order_statuses())]) as $order){
    if($order->get_type()!=='shop_order'){continue;}
    $own=false;foreach($order->get_items() as $item){if(in_array($item->get_variation_id()?:$item->get_product_id(),$ownProducts,true)){$own=true;break;}}
    if(!$own){continue;}
    $orders[]=$order;
}
if($mode==='sync-analytics'){
    // Native datastore synchronization compensates only for this lab's disabled background cron.
    foreach($orders as $order){
        $ids=[$order->get_id()];foreach($order->get_refunds() as $refund){$ids[]=$refund->get_id();}
        foreach($ids as $id){\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order($id);\Automattic\WooCommerce\Admin\API\Reports\Products\DataStore::sync_order_products($id);\Automattic\WooCommerce\Admin\API\Reports\Coupons\DataStore::sync_order_coupons($id);\Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore::sync_order_customer($id);}
    }
    if(class_exists('Automattic\\WooCommerce\\Admin\\API\\Reports\\Cache')){\Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();}
}
$stock=[];foreach(['simple'=>$fixture['products']['simple']]+$fixture['products']['variations'] as $key=>$id){$p=wc_get_product($id);$stock[$key]=['id'=>(int)$id,'quantity'=>$p->get_stock_quantity(),'status'=>$p->get_stock_status(),'low_stock_amount'=>$p->get_low_stock_amount()];}
$facts=[];foreach($orders as $order){
    $items=[];foreach($order->get_items() as $id=>$item){$items[]=['item_id'=>(int)$id,'product_id'=>$item->get_product_id(),'variation_id'=>$item->get_variation_id(),'name'=>$item->get_name(),'quantity'=>$item->get_quantity(),'subtotal'=>(float)$item->get_subtotal(),'total'=>(float)$item->get_total(),'refunded_quantity'=>$order->get_qty_refunded_for_item($id),'refunded_total'=>(float)$order->get_total_refunded_for_item($id)];}
    $notes=wc_get_order_notes(['order_id'=>$order->get_id(),'limit'=>100]);$privateNote=false;$customerNote=false;
    foreach($notes as $note){if(strpos($note->content,'D11O · preparación QA')!==false&&!$note->customer_note){$privateNote=true;}if(strpos($note->content,'D11O · aviso ficticio')!==false&&$note->customer_note){$customerNote=true;}}
    $facts[]=['id'=>$order->get_id(),'status'=>$order->get_status(),'payment_method'=>$order->get_payment_method(),'payment_method_title'=>$order->get_payment_method_title(),'total'=>(float)$order->get_total(),'shipping_total'=>(float)$order->get_shipping_total(),'discount_total'=>(float)$order->get_discount_total(),'coupons'=>$order->get_coupon_codes(),'shipping_methods'=>array_values(array_map(static fn($item)=>['name'=>$item->get_name(),'method_id'=>$item->get_method_id(),'total'=>(float)$item->get_total()],$order->get_shipping_methods())),'items'=>$items,'total_refunded'=>(float)$order->get_total_refunded(),'remaining_refund'=>(float)$order->get_remaining_refund_amount(),'refund_count'=>count($order->get_refunds()),'buyer_is_synthetic'=>(bool)preg_match('/@[^@]+\.test$/D',$order->get_billing_email()),'has_shipping_address'=>(bool)$order->get_shipping_address_1(),'has_private_note'=>$privateNote,'has_customer_note'=>$customerNote,'date_paid_set'=>(bool)$order->get_date_paid(),'date_completed_set'=>(bool)$order->get_date_completed()];
}
global $wpdb;$reserved=$wpdb->get_results('SELECT order_id,product_id,stock_quantity,expires FROM '.$wpdb->prefix.'wc_reserved_stock ORDER BY order_id,product_id',ARRAY_A);
$legalTable=\CommerceCore\Legal::table();$legal=$wpdb->get_results("SELECT id,code,status,order_id,consumer_mail,admin_mail FROM `$legalTable` ORDER BY id",ARRAY_A);
$stats=$wpdb->get_results('SELECT order_id,parent_id,num_items_sold,total_sales,net_total,status,date_created FROM '.$wpdb->prefix.'wc_order_stats ORDER BY order_id',ARRAY_A);
echo wp_json_encode(['result'=>'passed','mode'=>$mode,'business_date'=>wp_date('Y-m-d'),'timezone'=>get_option('timezone_string'),'fixture'=>$fixture,'stock'=>$stock,'orders'=>$facts,'reservations'=>$reserved,'reservation_probe'=>$reservationProbe,'legal'=>$legal,'native_analytics_rows'=>$stats,'analytics_native_sync_invoked'=>$mode==='sync-analytics','real_transfer'=>false,'real_refund'=>false,'external_mail'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
