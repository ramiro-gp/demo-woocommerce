<?php
/** Fresh-install lifecycle tests; hard stop outside disposable project. */
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || untrailingslashit(home_url())!=='http://localhost:8182') { exit(1); }
use CommerceCore\Legal;
global $wpdb;$mode=$args[0] ?? 'verify';$file='/backups/isolated-proof.json';
$check=static function($ok,$label){if(!$ok){WP_CLI::error($label);}};
add_filter('pre_http_request',static fn()=>new WP_Error('audit_offline','No external requests in disposable audit.'),PHP_INT_MAX);
if($mode==='restore-verify') {
    $expected=json_decode(file_get_contents('/backups/h8-2-restore-expected.json'),true);$checks=[];
    foreach($expected as $kind=>$proof){$rows=$wpdb->get_results('SELECT * FROM '.Legal::table($kind).' ORDER BY 1',ARRAY_A);$checks[$kind]=count($rows)===$proof['count'] && hash_equals($proof['sha256'],hash('sha256',wp_json_encode($rows)));}
    echo wp_json_encode(['result'=>in_array(false,$checks,true)?'failed':'passed','legal_tables_restored_exactly'=>$checks,'main_installation_restored'=>false],JSON_PRETTY_PRINT)."\n";if(in_array(false,$checks,true)){exit(1);}return;
}
if($mode==='prepare') {
    $check(!file_exists($file),'Never overwrite lifecycle proof');
    $check($wpdb->get_var('SELECT COUNT(*) FROM '.Legal::table())==='0','Fresh legal tables empty');
    $check(get_option('commerce_legal_schema')==='1','Schema initialized');
    $tables=[];foreach(['requests','events','limits'] as $kind){$t=Legal::table($kind);$tables[$kind]=['create'=>$wpdb->get_row("SHOW CREATE TABLE $t",ARRAY_N)[1],'indexes'=>$wpdb->get_results("SHOW INDEX FROM $t",ARRAY_A)];}
    $row=Legal::submit(['cc_name'=>'QA aislado','cc_email'=>'isolated@example.test','cc_token'=>'isolated-initial-proof'],'isolated');
    $check(!is_wp_error($row),'Fresh request stored');
    $proof=['id'=>$row->id,'code'=>$row->code,'row_hash'=>hash('sha256',wp_json_encode($wpdb->get_row($wpdb->prepare('SELECT * FROM '.Legal::table().' WHERE id=%d',$row->id),ARRAY_A))),'tables'=>$tables];
    file_put_contents($file,wp_json_encode($proof));
    // Force the public versioned migration path; dbDelta must preserve the request.
    update_option('commerce_legal_schema','0',false);Legal::install();
    echo wp_json_encode(['fresh_install'=>true,'tables'=>array_keys($tables),'schema_version'=>get_option('commerce_legal_schema'),'seeded_demo_pages'=>false],JSON_PRETTY_PRINT)."\n";return;
}
if($mode==='verify') {
    $proof=json_decode(file_get_contents($file),true);$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Legal::table().' WHERE id=%d',$proof['id']),ARRAY_A);
    $checks=['versioned_dbDelta_preserves_request'=>hash_equals($proof['row_hash'],hash('sha256',wp_json_encode($row))),'schema_version_is_1'=>get_option('commerce_legal_schema')==='1'];
    foreach(['requests','events','limits'] as $kind){$t=Legal::table($kind);$create=$wpdb->get_row("SHOW CREATE TABLE $t",ARRAY_N)[1];$checks['innodb_'.$kind]=str_contains($create,'ENGINE=InnoDB');$checks['indexes_'.$kind]=count($wpdb->get_results("SHOW INDEX FROM $t"))>=2;}
    $checks['unique_request_code']=str_contains($proof['tables']['requests']['create'],'UNIQUE KEY `code`');$checks['unique_idempotency_key']=str_contains($proof['tables']['requests']['create'],'UNIQUE KEY `token_hash`');
    echo wp_json_encode(['checks'=>$checks,'result'=>in_array(false,$checks,true)?'failed':'passed'],JSON_PRETTY_PRINT)."\n";if(in_array(false,$checks,true)){exit(1);}return;
}
if($mode==='browser-fixture') {
    $check(!file_exists('/backups/isolated-browser.json'),'Do not overwrite isolated fixture');
    $pass=wp_generate_password(32,true,true);$id=wp_insert_user(['user_login'=>'isolated_customer','user_pass'=>$pass,'user_email'=>'isolated.customer@example.test','role'=>'customer']);$check(!is_wp_error($id),'Isolated customer');
    $order=new WC_Order();$order->set_customer_id($id);$order->set_billing_email('isolated.customer@example.test');$order->set_created_via('h82-isolated-audit');$order->set_status('pending');$order->save();
    $page=wp_insert_post(['post_type'=>'page','post_title'=>'Arrepentimiento QA aislado','post_status'=>'publish','post_content'=>'[commerce_legal_form]']);
    update_option(Legal::OPTION,['pages'=>['withdrawal'=>$page],'notification_email'=>'isolated.admin@example.test']);
    file_put_contents('/backups/isolated-browser.json',wp_json_encode(['page'=>$page,'username'=>'isolated_customer','password'=>$pass,'order'=>$order->get_id(),'customer_id'=>$id]));echo "Isolated synthetic user/order prepared privately.\n";
}
