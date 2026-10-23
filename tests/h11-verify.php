<?php
/** Sanitized H11 source/restore evidence. No credentials, order keys, nonces or mail bodies. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || get_option('commerce_h11_installation') !== 'h11-production-simulation' || wp_parse_url(home_url(),PHP_URL_HOST) !== 'localhost' || !in_array((int)wp_parse_url(home_url(),PHP_URL_PORT),[8184,8185],true)) { exit(1); }
global $wpdb;
$mode=$args[0]??'snapshot';
if (!in_array($mode,['inspect','snapshot','snapshot-code','compare','compare-code'],true)) { WP_CLI::error('Use inspect|snapshot|snapshot-code|compare|compare-code.'); }
$source=(int)wp_parse_url(home_url(),PHP_URL_PORT)===8184;
if ((in_array($mode,['snapshot','snapshot-code','compare-code'],true)&&!$source)||($mode==='compare'&&$source)) { WP_CLI::error('Snapshots/compare-code require source :8184; compare requires restored :8185.'); }
$checks=[];
$check=static function($name,$value)use(&$checks){$checks[$name]=(bool)$value;};
$state=(array)get_option('commerce_h11_fixture');
$check('fixture_exercised',($state['phase']??'')==='exercised');
$check('commerce_base_active',get_stylesheet()==='commerce-base');
require_once ABSPATH.'wp-admin/includes/plugin.php';
$plugins=[];
foreach (['woocommerce/woocommerce.php','commerce-core/commerce-core.php','commerce-communications/commerce-communications.php','commerce-order-integrity/commerce-order-integrity.php','commerce-account-navigation/commerce-account-navigation.php','commerce-lookbook/commerce-lookbook.php'] as $plugin) {
    $check('active_'.strtok($plugin,'/'),is_plugin_active($plugin));
    $plugins[strtok($plugin,'/')]=get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin,false,false)['Version']??'';
}
$check('mercadopago_inactive',!is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php'));
$check('noindex',(string)get_option('blog_public')==='0');
$check('debug_not_displayed',!defined('WP_DEBUG_DISPLAY')||WP_DEBUG_DISPLAY===false);
$check('hpos_native',class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')&&\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled());
$check('legacy_order_sync_disabled',get_option('woocommerce_custom_orders_table_data_sync_enabled')==='no');
$check('bacs_enabled',(get_option('woocommerce_bacs_settings')['enabled']??'no')==='yes');
$check('no_bank_accounts',get_option('woocommerce_bacs_accounts')===[]);
$check('currency_ars',get_option('woocommerce_currency')==='ARS');
$check('permalinks',get_option('permalink_structure')==='/%postname%/');
$legal=\CommerceCore\Legal::config();
$check('legal_demo_only',($legal['trade_name']??'')==='H11 QA DEMO'&&empty($legal['cuit'])&&empty($legal['fiscal_url'])&&empty($legal['fiscal_image']));
$check('native_blocks',has_block('woocommerce/cart',get_post_field('post_content',$state['pages']['cart']??0))&&has_block('woocommerce/checkout',get_post_field('post_content',$state['pages']['checkout']??0)));
$check('home_native_page',(int)get_option('page_on_front')===(int)($state['pages']['home']??0));
$products=wc_get_products(['limit'=>-1,'status'=>'publish','return'=>'ids']); sort($products,SORT_NUMERIC);
$variations=[]; foreach($products as $id) { $p=wc_get_product($id); if($p->is_type('variable')) { $variations=array_merge($variations,$p->get_children()); } }
sort($variations,SORT_NUMERIC);
$check('two_fixture_products',count($products)===2 && in_array((int)($state['products']['simple']??0),$products,true)&&in_array((int)($state['products']['variable']??0),$products,true));
$check('two_color_variations',count($variations)===2 && array_keys(wc_get_product($state['products']['variable'])->get_variation_attributes())===['pa_color']);
$user_ids=[]; foreach(get_users() as $u) { $user_ids[]=(int)$u->ID; $check('synthetic_user_'.$u->ID,(bool)preg_match('/@[^@]+\.test$/D',$u->user_email)); }
sort($user_ids,SORT_NUMERIC);
$orders=[]; foreach($state['orders']??[] as $kind=>$id) { $o=wc_get_order($id); $check('order_exists_'.$kind,(bool)$o); if($o) { $orders[$kind]=['id'=>(int)$id,'status'=>$o->get_status(),'type'=>$o->get_type()]; } }
if(isset($state['post_backup_order_id'])) { $o=wc_get_order($state['post_backup_order_id']); $check('post_backup_order_exists',(bool)$o); if($o) { $orders['post_backup']=['id'=>$o->get_id(),'status'=>$o->get_status(),'type'=>$o->get_type()]; } }
if(in_array($mode,['snapshot-code','compare-code'],true)) { $check('post_backup_order_proves_recent_data',isset($orders['post_backup'])); }
foreach(['bacs'=>'on-hold','completed'=>'completed','cancelled'=>'cancelled','refunded'=>'refunded'] as $kind=>$status) { $check('native_status_'.$kind,($orders[$kind]['status']??'')===$status); }
$request_ids=array_map('intval',$wpdb->get_col('SELECT id FROM '.\CommerceCore\Legal::table().' ORDER BY id'));
$event_ids=array_map('intval',$wpdb->get_col('SELECT id FROM '.\CommerceCore\Legal::table('events').' ORDER BY id'));
$check('browser_and_api_legal_requests',count($request_ids)>=2&&in_array((int)($state['legal_api_id']??0),$request_ids,true));
$check('legal_event_audit',count($event_ids)>=7);
$check('legal_mails_sent',(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\CommerceCore\Legal::table()." WHERE consumer_mail<>'sent' OR admin_mail<>'sent'")===0);
$cron=_get_cron_array();
$check('wordpress_cron_tasks_present',is_array($cron)&&count($cron)>0);
$scheduled=class_exists('ActionScheduler')?as_get_scheduled_actions(['status'=>'pending','per_page'=>1],'ids'):[];
$check('action_scheduler_tasks_present',count($scheduled)>0);
$check('wp_cron_execution_verified',($state['operations']['wp_cron_executed']??false)===true);
$check('action_scheduler_execution_verified',($state['operations']['action_scheduler_executed']??false)===true);
// Logical digests cover complete business tables. Runtime sessions/jobs/transients are intentionally excluded.
// URLs are normalized so a :8184 -> :8185 restore can be compared without weakening other fields.
$normalize=static function($value) { return str_replace(['http://localhost:8184','http://localhost:8185'],'http://h11-lab.invalid',$value); };
$tables=[];
foreach(['users','usermeta','posts','postmeta','terms','termmeta','term_taxonomy','term_relationships','woocommerce_order_items','woocommerce_order_itemmeta','wc_orders','wc_order_addresses','wc_order_operational_data','wc_orders_meta','commerce_legal_requests','commerce_legal_events','commerce_legal_limits'] as $name) {
    $table=$wpdb->prefix.$name;
    if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) { $check('table_exists_'.$name,false); continue; }
    $rows=$wpdb->get_results('SELECT * FROM `'.str_replace('`','``',$table).'`',ARRAY_A);
    $serialized=array_map(static fn($row)=>$normalize(wp_json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),$rows); sort($serialized,SORT_STRING);
    $columns=$wpdb->get_results('SHOW COLUMNS FROM `'.str_replace('`','``',$table).'`',ARRAY_A);
    $tables[$name]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$serialized)),'schema_sha256'=>hash('sha256',wp_json_encode($columns))];
}
$options=[]; foreach(['blogname','blog_public','show_on_front','page_on_front','permalink_structure','woocommerce_currency','woocommerce_default_country','woocommerce_custom_orders_table_enabled','woocommerce_custom_orders_table_data_sync_enabled','woocommerce_bacs_settings','woocommerce_bacs_accounts','commerce_core_legal','commerce_h11_fixture','woocommerce_shop_page_id','woocommerce_cart_page_id','woocommerce_checkout_page_id','woocommerce_myaccount_page_id','woocommerce_terms_page_id','wp_page_for_privacy_policy','active_plugins','theme_mods_commerce-base'] as $key) { $options[$key]=get_option($key); }
$tables['business_options']=['rows'=>count($options),'sha256'=>hash('sha256',$normalize(wp_json_encode($options,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)))];
$media=[]; $id=(int)($state['attachment']??0); $file=get_attached_file($id); $metadata=wp_get_attachment_metadata($id); $paths=$file?[$file]:[];
foreach($metadata['sizes']??[] as $size) { $paths[]=dirname($file).'/'.$size['file']; }
foreach(array_unique($paths) as $path) { $exists=is_file($path); $check('media_file_'.basename($path),$exists); $media[basename($path)]=$exists?hash_file('sha256',$path):null; }
ksort($media); $check('attachment_original_and_sizes',count($media)>=2);
$data=['product_ids'=>$products,'variation_ids'=>$variations,'user_ids'=>$user_ids,'orders'=>$orders,'legal_request_ids'=>$request_ids,'legal_event_ids'=>$event_ids,'tables'=>$tables,'attachment'=>['id'=>$id,'files'=>$media]];
$digest=hash('sha256',wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
$baseline_file=in_array($mode,['snapshot-code','compare-code'],true)?'/h11-private/h11-code-snapshot.json':'/h11-private/h11-snapshot.json';
if(in_array($mode,['snapshot','snapshot-code'],true)) {
    if(in_array(false,$checks,true)) { WP_CLI::error('Source verification failed; no restoration baseline was written.'); }
    if(file_exists($baseline_file)) { WP_CLI::error('Baseline already exists; preserve the reviewed backup evidence.'); }
    file_put_contents($baseline_file,wp_json_encode(['digest'=>$digest,'data'=>$data],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); chmod($baseline_file,0600);
}
if(in_array($mode,['compare','compare-code'],true)) {
    if(!is_file($baseline_file)) { WP_CLI::error('Source snapshot missing.'); }
    $baseline=json_decode(file_get_contents($baseline_file),true,512,JSON_THROW_ON_ERROR);
    $check('restore_same_business_digest',hash_equals($baseline['digest'],$digest));
    foreach($tables as $key=>$row) { $check('restore_table_'.$key,($baseline['data']['tables'][$key]??null)===$row); }
    $check('restore_same_ids_and_statuses',$baseline['data']['product_ids']===$products&&$baseline['data']['variation_ids']===$variations&&$baseline['data']['user_ids']===$user_ids&&$baseline['data']['orders']===$orders&&$baseline['data']['legal_request_ids']===$request_ids&&$baseline['data']['legal_event_ids']===$event_ids);
    $check('restore_attachment_sha256',$baseline['data']['attachment']===$data['attachment']);
}
$report=['result'=>in_array(false,$checks,true)?'failed':'passed','mode'=>$mode,'origin'=>home_url(),'synthetic'=>true,'versions'=>['wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'database'=>$wpdb->db_version(),'theme'=>wp_get_theme()->get('Version'),'plugins'=>$plugins],'checks'=>$checks,'business_digest_sha256'=>$digest,'business'=>$data,'operational_execution'=>$state['operations']??[],'scheduled_tasks_present'=>['wp_cron'=>is_array($cron)&&count($cron)>0,'action_scheduler'=>count($scheduled)>0],'excluded_runtime_tables'=>['sessions','actionscheduler','logs','transients'],'real_mail'=>false,'real_payments'=>false];
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
if(in_array(false,$checks,true)) { WP_CLI::halt(1); }
