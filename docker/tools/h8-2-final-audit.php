<?php
/** Final audit tools: isolated synthetic records; never print personal information. */
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || untrailingslashit(home_url())!=='http://localhost:8080') { exit(1); }
use CommerceCore\Legal;
global $wpdb;
$mode=$args[0] ?? 'verify';$path='/backups/h8-2-final-baseline.json';
$capture=static function($max) use($wpdb) { $out=[];foreach(['requests','events'] as $kind) { $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Legal::table($kind).' WHERE id<=%d ORDER BY id',$max[$kind]),ARRAY_A);$out[$kind]=hash('sha256',wp_json_encode($rows)); }return $out; };
if($mode==='snapshot') {
    if(file_exists($path)) { WP_CLI::error('Never overwrite final audit baseline.'); }
    $max=[];foreach(['requests','events'] as $kind) { $max[$kind]=(int)$wpdb->get_var('SELECT MAX(id) FROM '.Legal::table($kind)); }
    file_put_contents($path,wp_json_encode(['max'=>$max,'hashes'=>$capture($max)]));echo "Historical legal requests/events baseline saved privately.\n";return;
}
if($mode==='verify') { $b=json_decode(file_get_contents($path),true);$now=$capture($b['max']);$checks=[];foreach($now as $key=>$value) { $checks[$key.'_preserved']=hash_equals($b['hashes'][$key],$value); }echo wp_json_encode($checks,JSON_PRETTY_PRINT)."\n";if(in_array(false,$checks,true)) { exit(1); }return; }
if($mode==='privacy-minimize') {
    $id=(int)get_option('wp_page_for_privacy_policy');if(get_post_meta($id,'_commerce_legal_demo',true)!=='1'){WP_CLI::error('Only marked DEMO content');}
    update_option('woocommerce_feature_order_attribution_enabled','no');
    $content=get_post_field('post_content',$id);
    if(!str_contains($content,'mp_st_variant')) {
        $paragraph='<!-- wp:paragraph --><p>Auditoría de almacenamiento de la DEMO: se deshabilitó la atribución de campañas de WooCommerce mediante su ajuste nativo; en sesiones nuevas ya no se generan sus cookies sbjs_*. Mercado Pago todavía puede guardar la cookie mp_st_variant (variante A/B, aproximadamente dos horas) y claves de sesión como _mp_flow_id, aun con Checkout Pro deshabilitado. Este tratamiento requiere revisión y una solución antes de publicar. Las cookies anteriores pueden permanecer hasta su vencimiento; desactivar una función no borra datos históricos de pedidos.</p><!-- /wp:paragraph -->';
        $content.=$paragraph;
        wp_update_post(['ID'=>$id,'post_content'=>wp_slash($content)]);
    }
    $needle='<!-- wp:heading {"level":2,"anchor":"conservacion-y-seguridad"} -->';
    if(preg_match('~<!-- wp:paragraph --><p>Auditoría de almacenamiento de la DEMO:.*?</p><!-- /wp:paragraph -->~s',$content,$match) && str_contains($content,$needle)) {
        $content=str_replace($match[0],'',$content);$content=str_replace($needle,$match[0].$needle,$content);wp_update_post(['ID'=>$id,'post_content'=>wp_slash($content)]);
    }
    echo wp_json_encode(['order_attribution'=>'no','native_option_used'=>true,'historical_order_metadata_removed'=>false,'mp_cookie_disclosed'=>true])."\n";return;
}
if($mode==='recover-probe') {
    $baseline=json_decode(file_get_contents($path),true);$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Legal::table()." WHERE id>%d AND name='QA H82 renderer' AND email LIKE 'h82.renderer.%%@example.test' AND consumer_mail='sending'",$baseline['max']['requests']));
    foreach($rows as $row) {
        // The controlled probe threw before transport; no mail event can exist.
        if($wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Legal::table('events')." WHERE request_id=%d AND kind LIKE 'mail_%%'",$row->id))!=='0') { WP_CLI::error('Unexpected mail history; do not reset uncertain deliveries.'); }
        $wpdb->update(Legal::table(),['consumer_mail'=>'failed'],['id'=>$row->id]);Legal::event($row->id,'qa_recovery','Caso sintético del probe anterior: fallo comprobado de plantilla antes del transporte.',0);Legal::notify($row->id);
    }
    echo wp_json_encode(['controlled_pre_fix_cases_recovered'=>count($rows)])."\n";return;
}
if($mode==='evidence') {
    $report=json_decode(file_get_contents('/backups/h8-2-final-browser.json'),true);$checks=[];$states=[];
    foreach($report['codes'] as $code) {
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Legal::table().' WHERE code=%s',$code));
        $checks[$code]=count($rows)===1 && $rows[0]->consumer_mail==='sent' && $rows[0]->admin_mail==='sent' && (int)$rows[0]->order_id===0;
        $states[$code]=['rows'=>count($rows),'status'=>$rows[0]->status,'consumer_mail'=>$rows[0]->consumer_mail,'admin_mail'=>$rows[0]->admin_mail,'linked_order'=>false];
    }
    $checks['hpos']='yes'===get_option('woocommerce_custom_orders_table_enabled');
    $checks['bacs']='yes'===(get_option('woocommerce_bacs_settings')['enabled'] ?? '');
    $checks['pro_disabled']='yes'!==(get_option('woocommerce_woo-mercado-pago-basic_settings')['enabled'] ?? '');
    $hashes=[];foreach(['requests','events','limits'] as $kind){$rows=$wpdb->get_results('SELECT * FROM '.Legal::table($kind).' ORDER BY 1',ARRAY_A);$hashes[$kind]=['count'=>count($rows),'sha256'=>hash('sha256',wp_json_encode($rows))];}
    file_put_contents('/backups/h8-2-restore-expected.json',wp_json_encode($hashes));
    echo wp_json_encode(['result'=>in_array(false,$checks,true)?'failed':'passed','checks'=>$checks,'requests'=>$states,'backup_tables'=>$hashes],JSON_PRETTY_PRINT)."\n";if(in_array(false,$checks,true)){exit(1);}return;
}
if($mode==='probe-renderer') {
    $run=bin2hex(random_bytes(6));$throw=static function(){ throw new RuntimeException('Synthetic mail template failure'); };
    add_action('woocommerce_email_header',$throw,-1000);$level=ob_get_level();$threw=false;$row=null;
    try { $row=Legal::submit(['cc_name'=>'QA H82 renderer','cc_email'=>'h82.renderer.'.$run.'@example.test','cc_token'=>$run],'qa-renderer-'.$run); }catch(Throwable $e) { $threw=true; }
    finally { remove_action('woocommerce_email_header',$throw,-1000);while(ob_get_level()>$level){ob_end_clean();} }
    $stored=$wpdb->get_row($wpdb->prepare('SELECT code,consumer_mail,admin_mail FROM '.Legal::table().' WHERE email=%s','h82.renderer.'.$run.'@example.test'));
    echo wp_json_encode(['exception_escaped'=>$threw,'request_persisted'=>(bool)$stored,'receipt_returned'=>is_object($row)&&!is_wp_error($row)&&isset($row->code),'consumer_state'=>$stored->consumer_mail ?? null,'admin_state'=>$stored->admin_mail ?? null],JSON_PRETTY_PRINT)."\n";
}
