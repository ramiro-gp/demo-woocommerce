<?php
/** Read-only persistence proof for products/coupons/shipping created through seller UI. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local'
    || get_option('commerce_seller_lab') !== 'd1-1-seller-audit'
    || home_url() !== 'http://localhost:8192' || \CommerceDemoPublic\enabled()) { exit(1); }
$checks=[];$check=static function($name,$ok)use(&$checks){$checks[$name]=(bool)$ok;};
$simple=wc_get_product(wc_get_product_id_by_sku('D11P-SIMPLE'));
$check('ui_simple_product_published',$simple&&$simple->get_status()==='publish');
$check('ui_prices_and_sale_persist',$simple&&(float)$simple->get_regular_price()===24000.0&&(float)$simple->get_sale_price()===21000.0);
$check('ui_stock_restock_persists',$simple&&$simple->get_manage_stock()&&(int)$simple->get_stock_quantity()===9);
$check('ui_uploaded_featured_image_persists',$simple&&$simple->get_image_id()>0);
$variables=[];
foreach (['clothing'=>'D11P-VARIABLE','decoration'=>'D11P-DECOR-PARENT'] as $kind=>$sku) {
    $product=wc_get_product(wc_get_product_id_by_sku($sku));$children=$product?$product->get_children():[];
    $check('ui_'.$kind.'_four_variations_persist',$product&&$product->is_type('variable')&&count($children)===4);
    $values=[];
    foreach ($children as $id) { $variation=wc_get_product($id);$values[]=['price'=>(float)$variation->get_regular_price(),'stock'=>(int)$variation->get_stock_quantity(),'managed'=>$variation->get_manage_stock()]; }
    $prices=array_column($values,'price');sort($prices);$stocks=array_column($values,'stock');sort($stocks);
    $check('ui_'.$kind.'_per_variation_prices_stock_persist',$prices===[30000.0,31000.0,32000.0,33000.0]&&$stocks===[3,4,5,6]&&!in_array(false,array_column($values,'managed'),true));
    $variables[$kind]=['attribute_names'=>$product?array_keys($product->get_attributes()):[],'variation_count'=>count($children),'prices'=>$prices,'stocks'=>$stocks];
}
$imported=wc_get_product(wc_get_product_id_by_sku('D11P-CSV'));
$check('native_csv_product_persists',$imported&&(float)$imported->get_regular_price()===15000.0&&(int)$imported->get_stock_quantity()===5);
$trashed=get_posts(['post_type'=>'product','post_status'=>'trash','meta_key'=>'_sku','meta_value'=>'D11P-DELETE','numberposts'=>1]);
$check('own_qa_product_in_trash',count($trashed)===1);
$attribute=wc_attribute_taxonomy_id_by_name('d11p-acabado');
$check('ui_global_attribute_and_terms_persist',$attribute>0&&term_exists('Mate QA','pa_d11p-acabado')&&term_exists('Brillante QA','pa_d11p-acabado'));
$coupons=[];
foreach (['d11p-percent'=>['percent',15.0,50000.0],'d11p-fixed'=>['fixed_cart',2500.0,10000.0],'d11p-expired'=>['percent',5.0,10000.0]] as $code=>$expected) {
    $coupon=new WC_Coupon($code);$expiry=$coupon->get_date_expires();
    $check('ui_coupon_'.$code.'_settings_persist',$coupon->get_id()>0&&$coupon->get_discount_type()===$expected[0]&&(float)$coupon->get_amount()===$expected[1]&&(float)$coupon->get_minimum_amount()===$expected[2]&&$coupon->get_individual_use()&&$coupon->get_free_shipping()&&$coupon->get_usage_limit()===20&&$coupon->get_usage_limit_per_user()===1&&$expiry);
    $coupons[$code]=['type'=>$coupon->get_discount_type(),'amount'=>(float)$coupon->get_amount(),'minimum'=>(float)$coupon->get_minimum_amount(),'expires'=>$expiry?$expiry->date('Y-m-d'):null,'individual_use'=>$coupon->get_individual_use()];
}
$zone=null;
foreach (WC_Shipping_Zones::get_zones() as $candidate) { if ($candidate['zone_name']==='D11P CP9999 QA') { $zone=new WC_Shipping_Zone($candidate['zone_id']);break; } }
$check('ui_shipping_zone_persists',$zone instanceof WC_Shipping_Zone);
$shipping=[];
if ($zone) {
    $locations=$zone->get_zone_locations();
    $check('qa_zone_only_postcode_9999',(bool)array_filter($locations,static fn($l)=>$l->type==='postcode'&&$l->code==='9999'));
    foreach ($zone->get_shipping_methods() as $method) { $shipping[$method->id]=['title'=>$method->get_title(),'cost'=>$method->get_option('cost'),'min_amount'=>$method->get_option('min_amount'),'requires'=>$method->get_option('requires')]; }
}
$check('ui_flat_rate_1800_persists',isset($shipping['flat_rate'])&&(float)$shipping['flat_rate']['cost']===1800.0);
$check('ui_free_shipping_threshold_50000_persists',isset($shipping['free_shipping'])&&(float)$shipping['free_shipping']['min_amount']===50000.0&&$shipping['free_shipping']['requires']==='min_amount');
$check('ui_pickup_zero_cost_persists',isset($shipping['local_pickup'])&&(float)$shipping['local_pickup']['cost']===0.0);
$check('original_country_eligibility_preserved',array_keys(WC()->countries->get_shipping_countries())===['AR']);
echo wp_json_encode(['result'=>in_array(false,$checks,true)?'failed':'passed','scope'=>'Persistence after actual native seller UI actions; disposable clone only','origin'=>home_url(),'checks'=>$checks,'variables'=>$variables,'coupons'=>$coupons,'shipping'=>$shipping,'no_functional_source_changes'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
if(in_array(false,$checks,true)){WP_CLI::halt(1);}
