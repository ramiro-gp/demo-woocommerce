<?php
/** D1 reversible enforcement proof. Only the disposable local labs; no external services. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || get_option('commerce_d1_installation') !== 'd1-public-demo-lab' || wp_parse_url(home_url(),PHP_URL_HOST)!=='localhost' || !in_array((int)wp_parse_url(home_url(),PHP_URL_PORT),[8190,8191],true)) { exit(1); }
if (!class_exists('WooCommerce') || !function_exists('CommerceDemoPublic\\enabled')) { WP_CLI::error('D1 module and WooCommerce must be installed.'); }
$mode=$args[0]??'enabled';
if(!in_array($mode,['enabled','disabled'],true)){WP_CLI::error('Use enabled|disabled, matching the explicit lab configuration.');}
$checks=[];$check=static function($name,$value)use(&$checks){$checks[$name]=(bool)$value;};
$enabled=\CommerceDemoPublic\enabled();
$check('expected_activation_state',$enabled===($mode==='enabled'));
if(in_array(false,$checks,true)){WP_CLI::error('Unexpected mode; no order operation attempted.');}
$check('empty_order_baseline',count(wc_get_orders(['limit'=>-1,'return'=>'ids']))===0);
if(in_array(false,$checks,true)){WP_CLI::error('Requires empty disposable lab, preserving existing records.');}
// Independent final transport stop remains present when the demo module is OFF.
$mailSafety=static fn()=>false;add_filter('pre_wp_mail',$mailSafety,PHP_INT_MAX);
$mailTransportReached=false;$transportProbe=static function()use(&$mailTransportReached){$mailTransportReached=true;throw new RuntimeException('D1 prohibits native mail transport.');};
add_action('phpmailer_init',$transportProbe,PHP_INT_MIN);
$order=null;$gateway=null;$oldEnabled=null;$productId=0;$stockBefore=null;$stockStatusBefore=null;
try{
    if($enabled){
        $blocked=false;
        try{$order=wc_create_order();}catch(Throwable $error){$blocked=$error instanceof WC_Data_Exception || strpos($error->getMessage(),'demostraci')!==false;}
        // WooCommerce catches save exceptions internally and can return an unsaved object (ID 0).
        $check('native_wc_create_order_is_blocked',$blocked || is_wp_error($order) || ($order instanceof WC_Order && !$order->get_id()));
        $check('gateways_empty',WC()->payment_gateways()->get_available_payment_gateways()===[]);
    }else{
        $gateways=WC()->payment_gateways()->payment_gateways();$gateway=$gateways['bacs']??null;
        $check('native_bacs_class_still_available',$gateway instanceof WC_Gateway_BACS);
        if($gateway){
            $oldEnabled=$gateway->enabled;$gateway->enabled='yes';
            $available=WC()->payment_gateways()->get_available_payment_gateways();
            $check('native_gateway_can_be_enabled_in_controlled_lab',isset($available['bacs']));
            $order=wc_create_order();
            $check('native_order_creation_restored',$order instanceof WC_Order && $order->get_id()>0);
            if($order instanceof WC_Order){
                $product=wc_get_products(['limit'=>1,'type'=>'simple','status'=>'publish'])[0]??null;
                $check('synthetic_product_available',$product instanceof WC_Product);
                if($product){
                    $productId=$product->get_id();$stockBefore=$product->get_stock_quantity();$stockStatusBefore=$product->get_stock_status();
                    $order->add_product($product,1);
                }
                $order->set_billing_email('reversal@d1.example.test');
                $order->set_payment_method($gateway);$order->calculate_totals();$order->save();
                $order->update_status('on-hold','D1 controlled synthetic reversal proof; no real transfer.');
                $reloaded=wc_get_order($order->get_id());
                $check('native_bacs_order_state_restored',$reloaded && $reloaded->get_status()==='on-hold' && $reloaded->get_payment_method()==='bacs');
            }
        }
    }
    $check('mail_transport_stopped',wp_mail('reversal@d1.example.test','D1 synthetic probe','No real delivery.')===false && !$mailTransportReached);
}finally{
    // This ID is created above in this invocation only; no historical data is accepted.
    if(!$enabled && $order instanceof WC_Order && $order->get_id()){
        // Native on-hold transitions can reduce inventory; deletion alone does not undo that.
        wc_increase_stock_levels($order->get_id());
        if($productId){
            $restored=wc_get_product($productId);
            $check('product_stock_restored',$restored && $restored->get_stock_quantity()===$stockBefore && $restored->get_stock_status()===$stockStatusBefore);
        }
        $order->delete(true);
    }
    if($gateway && $oldEnabled!==null){$gateway->enabled=$oldEnabled;}
    remove_filter('pre_wp_mail',$mailSafety,PHP_INT_MAX);remove_action('phpmailer_init',$transportProbe,PHP_INT_MIN);
}
$check('no_order_left_after_probe',count(wc_get_orders(['limit'=>-1,'return'=>'ids']))===0);
$report=['result'=>in_array(false,$checks,true)?'failed':'passed','origin'=>home_url(),'mode'=>$mode,'checks'=>$checks,'external_mail'=>false,'real_payment'=>false,'native_checkout_browser_tested'=>false];
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if(in_array(false,$checks,true)){WP_CLI::halt(1);}
