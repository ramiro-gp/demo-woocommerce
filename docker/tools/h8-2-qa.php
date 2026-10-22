<?php
/** Focused integration QA. Synthetic requests only; historical commerce read-only. */
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || untrailingslashit(home_url())!=='http://localhost:8080') { exit(1); }
use CommerceCore\Legal;
$mode=$args[0] ?? 'verify';global $wpdb;
$check=static function($ok,$label) { if(!$ok) { WP_CLI::error($label); } };
if($mode==='prepare') {
    $path='/backups/h8-2-qa-private.json';if(file_exists($path)) { echo "QA fixture already prepared.\n";return; }
    $run=bin2hex(random_bytes(8));$users=[];
    foreach(['manager'=>'shop_manager','subscriber'=>'subscriber'] as $key=>$role) {
        $username='h82_'.$key.'_'.$run;$password=wp_generate_password(36,true,true);
        $id=wp_insert_user(['user_login'=>$username,'user_pass'=>$password,'user_email'=>$username.'@example.test','role'=>$role,'display_name'=>'QA H8.2']);
        $check(!is_wp_error($id),'Create isolated QA user');update_user_meta($id,'_commerce_h82_qa',$run);$users[$key]=['id'=>$id,'username'=>$username,'password'=>$password];
    }
    $qa_order=wc_get_order(454);$check($qa_order && $qa_order->get_meta('_commerce_h72_manual_review'),'QA order must have known mark; read only.');
    file_put_contents($path,wp_json_encode(['run'=>$run,'users'=>$users,'pages'=>Legal::config()['pages'],'qa_order'=>454],JSON_PRETTY_PRINT));echo "Private fixtures saved; no credentials printed.\n";return;
}
add_filter('pre_http_request',static fn()=>new WP_Error('offline','External HTTP blocked by local QA.'),PHP_INT_MAX);
$checks=[];$record=static function($name,$ok) use (&$checks,$check) { $checks[$name]=(bool)$ok;$check($ok,$name); };
if($mode==='verify') {
    $run=bin2hex(random_bytes(8));$base=['cc_name'=>'QA H82 Integration','cc_email'=>'h82.integration.'.$run.'@example.test','cc_reference'=>'No existe / referencia informada','cc_message'=>'Solicitud sin justificación obligatoria.','cc_token'=>$run.'-one'];
    $failure=static fn()=>false;add_filter('pre_wp_mail',$failure,PHP_INT_MAX);
    $row=Legal::submit($base,'qa-'.$run);remove_filter('pre_wp_mail',$failure,PHP_INT_MAX);
    $record('persisted_when_mail_fails',!is_wp_error($row) && $row->consumer_mail==='failed' && $row->admin_mail==='failed');
    $record('unique_code_random',preg_match('/^ARR-\d{8}-[A-F0-9]{20}$/D',$row->code));
    $record('received_not_approved',$row->status==='received' && (int)$row->order_id===0);
    $again=Legal::submit($base,'qa-'.$run);$record('idempotency_same_code',$again->id===$row->id && $again->code===$row->code);
    $changed=$base;$changed['cc_reference']='changed';$record('idempotency_payload_conflict',is_wp_error(Legal::submit($changed,'qa-'.$run)));
    Legal::notify($row->id);$after=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Legal::table().' WHERE id=%d',$row->id));
    $record('retry_both_mail_sent',$after->consumer_mail==='sent' && $after->admin_mail==='sent');
    $events=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Legal::table('events').' WHERE request_id=%d',$row->id));Legal::notify($row->id);
    $record('no_retry_sent_mails',$events===(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Legal::table('events').' WHERE request_id=%d',$row->id)));
    $export=Legal::export($base['cc_email']);$record('native_privacy_export',count($export['data'])===1 && $export['data'][0]['data'][0]['value']===$row->code);
    $record('privacy_export_no_internal_tokens',!str_contains(wp_json_encode($export),'token_hash') && !str_contains(wp_json_encode($export),'payload_hash'));
    $erase=Legal::erase($base['cc_email']);$record('erase_retention_requires_review',$erase['items_retained'] && !$erase['items_removed']);
    $empty=Legal::submit(['cc_name'=>'','cc_email'=>'invalid','cc_token'=>$run.'bad'],'qa-'.$run);$record('server_validation_empty_bad_email',is_wp_error($empty));
    $bad=$base;$bad['cc_email']='foo@example.test<script>';$bad['cc_token']=$run.'bad2';$record('email_not_silently_sanitized',is_wp_error(Legal::submit($bad,'qa-'.$run)));
    $array=$base;$array['cc_name']=['attack'];$record('array_input_rejected',is_wp_error(Legal::submit($array,'qa-'.$run)));
    $spam=$base;$spam['cc_website']='bot';$record('honeypot_rejected',is_wp_error(Legal::submit($spam,'qa-'.$run)));
    $long=$base;$long['cc_message']=str_repeat('x',2001);$record('length_server_limit',is_wp_error(Legal::submit($long,'qa-'.$run)));
    // Rate limits exercised without sending more mail. Different synthetic email.
    $limit=$base;$limit['cc_email']='h82.limit.'.$run.'@example.test';add_filter('pre_wp_mail',$failure,PHP_INT_MAX);
    for($i=0;$i<7;$i++) { $limit['cc_token']=$run.'limit'.$i;$last=Legal::submit($limit,'qa-limit-'.$run);if($i<6) { $check(!is_wp_error($last),'First six within limit'); } }
    remove_filter('pre_wp_mail',$failure,PHP_INT_MAX);$record('seventh_email_request_rate_limited',is_wp_error($last) && $last->get_error_code()==='rate');
    $record('fiscal_javascript_denied',Legal::fiscal_url('javascript:alert(1)')==='');
    $record('fiscal_lookalike_denied',Legal::fiscal_url('https://serviciosweb.afip.gob.ar.evil.test/clavefiscal/qr/response.aspx?t=x')==='');
    $record('fiscal_official_link_allowed',Legal::fiscal_url('https://serviciosweb.afip.gob.ar/clavefiscal/qr/response.aspx?t=QA')!=='');
    $record('fiscal_official_logo_allowed',Legal::fiscal_url('https://www.afip.gob.ar/images/f960/DATAWEB.jpg',true)!=='');
    // URL validator tests never save or fetch those example URLs.
    $record('checkout_pro_disabled','yes'!==(get_option('woocommerce_woo-mercado-pago-basic_settings')['enabled'] ?? ''));
    $record('hpos_enabled','yes'===get_option('woocommerce_custom_orders_table_enabled'));
    $record('integrity_communications_core_active',is_plugin_active('commerce-order-integrity/commerce-order-integrity.php') && is_plugin_active('commerce-communications/commerce-communications.php') && is_plugin_active('commerce-core/commerce-core.php'));
    foreach(Legal::config()['pages'] as $key=>$id) { $record('published_demo_'.$key,get_post_status($id)==='publish' && get_post_meta($id,'_commerce_legal_demo',true)==='1'); }
    $record('native_privacy_id',(int)get_option('wp_page_for_privacy_policy')===3);
    $record('native_terms_id',(int)get_option('woocommerce_terms_page_id')===(int)Legal::config()['pages']['terms']);
    $record('spanish_checkout_privacy',str_contains(get_option('woocommerce_checkout_privacy_policy_text'),'Usamos'));
    $record('no_service_cancellation_button',!str_contains(get_post_field('post_content',Legal::config()['pages']['withdrawal']),'BOTÓN DE BAJA'));
    $config=Legal::config();$record('no_fake_identity_or_qr',empty($config['cuit']) && empty($config['legal_name']) && empty($config['fiscal_url']) && empty($config['fiscal_image']));
    $summary=['result'=>'passed','checks'=>$checks,'synthetic_requests_created'=>7,'historical_orders_modified'=>false,'test_code'=>$row->code];
    file_put_contents('/backups/h8-2-integration-report.json',wp_json_encode($summary,JSON_PRETTY_PRINT));echo wp_json_encode($summary,JSON_PRETTY_PRINT)."\n";
} elseif($mode==='inventory') {
    echo wp_json_encode(['schema'=>get_option('commerce_legal_schema'),'requests'=>(int)$wpdb->get_var('SELECT COUNT(*) FROM '.Legal::table()),'public_rest_request_route'=>false,'exporter'=>isset(apply_filters('wp_privacy_personal_data_exporters',[])['commerce-legal']),'eraser'=>isset(apply_filters('wp_privacy_personal_data_erasers',[])['commerce-legal'])],JSON_PRETTY_PRINT)."\n";
}
