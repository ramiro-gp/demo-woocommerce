<?php
/** Inspect/trigger existing synthetic seller lab only; no historic or public demo orders. */
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'||!in_array(home_url(),['http://localhost:8192','http://localhost:8193'],true)||get_option('commerce_seller_lab')!=='d1-1-seller-audit'){exit(1);}
$mode=$args[0]??'inspect';$key='woocommerce_customer_completed_order_settings';
if($mode==='trigger'){
    $orders=wc_get_orders(['limit'=>1,'type'=>'shop_order','status'=>'completed']);$order=$orders[0]??null;
    // A new local synthetic order is used for TRAMA only if none exists. It has no items, stock or payment.
    if(!$order){$order=wc_create_order(['status'=>'pending']);$order->set_billing_email('d2-email@demo.example.test');$order->update_meta_data('_d2_email_probe','1');$order->save();}
    if(!str_ends_with($order->get_billing_email(),'.test')){WP_CLI::error('Synthetic recipient required.');}
    WC()->mailer()->get_emails()['WC_Email_Customer_Completed_Order']->trigger($order->get_id());
    echo wp_json_encode(['order_id'=>$order->get_id(),'subject'=>WC()->mailer()->get_emails()['WC_Email_Customer_Completed_Order']->get_subject(),'external_mail'=>false]);
    if($order->get_meta('_d2_email_probe')==='1'){$order->delete(true);}return;
}
echo wp_json_encode(['settings'=>get_option($key,[]),'brand'=>get_bloginfo('name'),'origin'=>home_url(),'transport'=>'Mailpit only']);
