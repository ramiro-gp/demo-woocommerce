<?php
/** Read-only safety/provenance audit. Never bootstraps an original shop or a public D1 demo. */
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'
    || get_option('commerce_seller_lab')!=='d1-1-seller-audit'
    || !in_array(home_url(),['http://localhost:8192','http://localhost:8193'],true)){exit(1);}
$checks=[];$check=static function($key,$value)use(&$checks){$checks[$key]=(bool)$value;};
$check('public_guard_explicitly_off',defined('COMMERCE_DEMO_PUBLIC')&&COMMERCE_DEMO_PUBLIC===false&&!\CommerceDemoPublic\enabled());
$check('approved_wordpress',get_bloginfo('version')==='7.1.3');$check('approved_woocommerce',defined('WC_VERSION')&&WC_VERSION==='11.2.1');
$check('commerce_base_active',get_stylesheet()==='commerce-base');$check('mercadopago_absent',!is_dir(WP_PLUGIN_DIR.'/woocommerce-mercadopago'));
$check('native_bacs_only',array_keys(WC()->payment_gateways()->get_available_payment_gateways())===['bacs']);
$check('no_bank_accounts',empty(get_option('woocommerce_bacs_accounts')));$check('noindex',(string)get_option('blog_public')==='0');
$users=get_users();$check('synthetic_users_only',!array_filter($users,static fn($u)=>!str_ends_with($u->user_email,'.test')));
$transportReached=false;$probe=static function()use(&$transportReached){$transportReached=true;};add_action('phpmailer_init',$probe,PHP_INT_MIN);
$check('external_recipient_rejected_before_transport',wp_mail('blocked@example.invalid','QA transport boundary','Synthetic transport test.')===false&&!$transportReached);
$check('mixed_external_recipient_list_rejected',wp_mail('blocked@example.invalid, allowed@seller.example.test','QA transport boundary','Synthetic transport test.')===false&&!$transportReached);
$check('cc_recipient_rejected_before_transport',wp_mail('allowed@seller.example.test','QA transport boundary','Synthetic transport test.',['Cc: blocked@example.invalid'])===false&&!$transportReached);
remove_action('phpmailer_init',$probe,PHP_INT_MIN);
$http=wp_remote_get('https://example.invalid/seller-qa');$check('external_wordpress_http_rejected',is_wp_error($http)&&$http->get_error_code()==='seller_lab_offline');
$roles=[];foreach(['administrator','shop_manager','customer'] as $role){$caps=get_role($role)->capabilities;ksort($caps);$roles[$role]=['capability_count'=>count($caps),'capabilities_sha256'=>hash('sha256',wp_json_encode($caps))];}
$report=['result'=>in_array(false,$checks,true)?'failed':'passed','origin'=>home_url(),'checks'=>$checks,'stored_roles'=>$roles,'qa_user_count'=>count($users),'external_email_delivered'=>false,'real_payment'=>false];
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";if(in_array(false,$checks,true)){WP_CLI::halt(1);}
