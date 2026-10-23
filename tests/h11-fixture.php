<?php
/** Synthetic H11 lab only. Never a customer installer or production fixture. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
if (wp_get_environment_type() !== 'local' || get_option('commerce_h11_installation') !== 'h11-production-simulation' || wp_parse_url(home_url(), PHP_URL_HOST) !== 'localhost' || !in_array((int) wp_parse_url(home_url(), PHP_URL_PORT), [8184,8185], true)) {
    WP_CLI::error('H11 requires the explicitly marked isolated localhost:8184/8185 lab.');
}
if (!class_exists('WooCommerce') || !class_exists('CommerceCore\\Legal') || get_stylesheet() !== 'commerce-base') { WP_CLI::error('Install and activate the release artifacts first.'); }
$private = '/h11-private/h11-fixture.json';
$mode = $args[0] ?? 'prepare';
function h11_fail($condition, $message) { if (!$condition) { WP_CLI::error($message); } }
function h11_json_private($file, $value) {
    h11_fail(is_dir(dirname($file)) && is_writable(dirname($file)), 'Private lab directory must be writable.');
    h11_fail(file_put_contents($file, wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false, 'Cannot write private lab state.');
    chmod($file, 0600);
}
function h11_page($title, $slug, $content, $legal = false) {
    h11_fail(!get_page_by_path($slug), 'H11 page collision; preserve and inspect this lab.');
    $id = wp_insert_post(wp_slash(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content]), true);
    h11_fail(!is_wp_error($id), 'Unable to create H11 page.');
    update_post_meta($id, '_commerce_h11_fixture', '1');
    if ($legal) { update_post_meta($id, '_commerce_legal_demo', '1'); update_post_meta($id, '_wp_page_template', 'page-institutional'); }
    return (int) $id;
}
if ($mode === 'prepare') {
    global $wpdb;
    h11_fail((int)wp_parse_url(home_url(), PHP_URL_PORT) === 8184, 'Seed only the source H11 lab.');
    h11_fail(!file_exists($private) && !get_option('commerce_h11_fixture'), 'H11 fixture already exists; no reseeding or data replacement.');
    h11_fail(!wc_get_products(['limit'=>1,'return'=>'ids']) && !wc_get_orders(['limit'=>1,'return'=>'ids']) && !get_users(['role'=>'customer','number'=>1]), 'H11 needs a new empty shop.');
    \CommerceCore\Legal::install();
    h11_fail((int)$wpdb->get_var('SELECT COUNT(*) FROM '.\CommerceCore\Legal::table()) === 0, 'Legal records already exist.');
    foreach (get_users() as $u) { h11_fail((bool)preg_match('/@[^@]+\.test$/D', $u->user_email), 'H11 allows synthetic .test users only.'); }
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    h11_fail(!is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php'), 'Mercado Pago must remain inactive.');
    $settings = [
        'blogname'=>'H11 QA DEMO','blogdescription'=>'Laboratorio local de instalación y recuperación. Sin ventas reales.',
        'blog_public'=>'0','show_avatars'=>'0','timezone_string'=>'America/Argentina/Buenos_Aires','woocommerce_currency'=>'ARS',
        'woocommerce_default_country'=>'AR:C','woocommerce_store_address'=>'Calle ficticia 123','woocommerce_store_city'=>'Ciudad Autónoma de Buenos Aires','woocommerce_store_postcode'=>'1414',
        'woocommerce_allowed_countries'=>'specific','woocommerce_specific_allowed_countries'=>['AR'],'woocommerce_ship_to_countries'=>'specific','woocommerce_specific_ship_to_countries'=>['AR'],
        'woocommerce_calc_taxes'=>'no','woocommerce_custom_orders_table_enabled'=>'yes','woocommerce_custom_orders_table_data_sync_enabled'=>'no',
        'woocommerce_enable_guest_checkout'=>'yes','woocommerce_enable_myaccount_registration'=>'yes','woocommerce_registration_generate_password'=>'yes','woocommerce_registration_generate_username'=>'yes',
        'woocommerce_coming_soon'=>'no','woocommerce_store_pages_only'=>'no','woocommerce_feature_order_attribution_enabled'=>'no',
        'woocommerce_email_from_name'=>'H11 QA DEMO','woocommerce_email_from_address'=>'store@h11.example.test',
        'woocommerce_email_footer_text'=>'H11 QA DEMO · mensajes ficticios capturados sólo en laboratorio.',
        'woocommerce_bacs_settings'=>['enabled'=>'yes','title'=>'Transferencia bancaria · DEMO','description'=>'Prueba local. No realices transferencias.','instructions'=>'DEMO: no hay una cuenta bancaria real. No realices pagos.'],
        'woocommerce_bacs_accounts'=>[],'woocommerce_cod_settings'=>['enabled'=>'no'],'woocommerce_cheque_settings'=>['enabled'=>'no'],
        'commerce_base_exit_intent'=>['enabled'=>false],'commerce_base_color_swatches'=>['azul'=>'#3067a1','arena'=>'#d7c3a2'],
    ];
    foreach (['new_order','cancelled_order','failed_order','customer_on_hold_order','customer_processing_order','customer_completed_order','customer_refunded_order','customer_cancelled_order','customer_new_account','customer_reset_password'] as $kind) {
        $settings['woocommerce_'.$kind.'_settings'] = ['enabled'=>'yes','email_type'=>'multipart','recipient'=>'operations@h11.example.test'];
    }
    foreach ($settings as $key=>$value) { update_option($key, $value, false); }
    WC_Install::create_pages();
    // A procedural, original technical PNG: no copied assets, metadata, identity or copyright material.
    $chunk = static fn($type,$data) => pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
    $pixels = ''; for ($y=0;$y<480;$y++) { $pixels .= "\0"; for($x=0;$x<640;$x++) { $pixels .= (($x > 180 && $x < 460 && $y > 100 && $y < 380) ? "\x30\x67\xa1" : "\xe7\xe2\xd8"); } }
    $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',640,480,8,2,0,0,0)).$chunk('IDAT',gzcompress($pixels)).$chunk('IEND','');
    $upload = wp_upload_bits('h11-technical-demo.png', null, $png); h11_fail(!$upload['error'], 'Technical fixture image upload failed.');
    require_once ABSPATH.'wp-admin/includes/image.php';
    $image = wp_insert_attachment(['post_title'=>'H11 QA · imagen técnica original','post_status'=>'inherit','post_mime_type'=>'image/png'], $upload['file']);
    wp_update_attachment_metadata($image, wp_generate_attachment_metadata($image, $upload['file']));
    update_post_meta($image, '_wp_attachment_image_alt', 'Bloque azul sobre fondo arena, imagen técnica original de QA H11.');
    $category = wp_insert_term('H11 QA DEMO','product_cat',['slug'=>'h11-qa-demo']); h11_fail(!is_wp_error($category), 'Category creation failed.');
    $simple = new WC_Product_Simple();
    $simple->set_props(['name'=>'Objeto simple H11 DEMO','slug'=>'objeto-simple-h11','sku'=>'H11-QA-SIMPLE','status'=>'publish','regular_price'=>'12000','manage_stock'=>true,'stock_quantity'=>40,'stock_status'=>'instock','image_id'=>$image,'category_ids'=>[(int)$category['term_id']],'description'=>'<p>Producto ficticio para QA local. Sin valor comercial.</p>','short_description'=>'DEMO · producto de prueba.','weight'=>'1']);
    $simple_id = $simple->save();
    $attribute_id = wc_create_attribute(['name'=>'Color','slug'=>'color','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);
    h11_fail(!is_wp_error($attribute_id), 'Color attribute creation failed.');
    if (!taxonomy_exists('pa_color')) { register_taxonomy('pa_color',['product'],['hierarchical'=>false,'label'=>'Color']); }
    $colors=[]; foreach(['Azul','Arena'] as $name) { $term=wp_insert_term($name,'pa_color',['slug'=>sanitize_title($name)]); h11_fail(!is_wp_error($term),'Color term creation failed.'); $colors[]=(int)$term['term_id']; }
    delete_transient('wc_attribute_taxonomies');
    $attribute = new WC_Product_Attribute(); $attribute->set_id($attribute_id); $attribute->set_name('pa_color'); $attribute->set_options($colors); $attribute->set_visible(true); $attribute->set_variation(true);
    $variable = new WC_Product_Variable();
    $variable->set_props(['name'=>'Objeto variable H11 DEMO','slug'=>'objeto-variable-h11','sku'=>'H11-QA-VARIABLE','status'=>'publish','image_id'=>$image,'category_ids'=>[(int)$category['term_id']],'attributes'=>[$attribute],'description'=>'<p>Variaciones ficticias para comprobar selectores y swatches.</p>','short_description'=>'DEMO · selección Color.','weight'=>'1']);
    $variable_id=$variable->save(); $variations=[];
    foreach (['azul','arena'] as $i=>$color) { $v = new WC_Product_Variation(); $v->set_props(['parent_id'=>$variable_id,'status'=>'publish','sku'=>'H11-QA-'.$color,'attributes'=>['pa_color'=>$color],'regular_price'=>(string)(18000+$i*1000),'manage_stock'=>true,'stock_quantity'=>30,'stock_status'=>'instock','image_id'=>$image,'weight'=>'1']); $variations[]=$v->save(); }
    WC_Product_Variable::sync($variable_id);
    $zone=new WC_Shipping_Zone(); $zone->set_zone_name('H11 QA · Argentina'); $zone->add_location('AR','country'); $zone->save(); $rate=$zone->add_shipping_method('flat_rate'); update_option('woocommerce_flat_rate_'.$rate.'_settings',['title'=>'Envío ficticio · DEMO','tax_status'=>'none','cost'=>'1500']);
    $pages=[]; foreach(['shop'=>'Catálogo DEMO','cart'=>'Carrito','checkout'=>'Finalizar compra','myaccount'=>'Mi cuenta'] as $key=>$title) { $pages[$key]=wc_get_page_id($key); h11_fail($pages[$key]>0,'Native WooCommerce page missing.'); wp_update_post(['ID'=>$pages[$key],'post_title'=>$title]); }
    h11_fail(has_block('woocommerce/cart',get_post_field('post_content',$pages['cart'])) && has_block('woocommerce/checkout',get_post_field('post_content',$pages['checkout'])), 'Native Cart and Checkout Blocks required.');
    foreach (['privacy'=>'Privacidad DEMO','terms'=>'Términos DEMO','shipping'=>'Envíos DEMO','returns'=>'Cambios DEMO','withdrawal'=>'Arrepentimiento DEMO'] as $key=>$title) {
        $content='<div class="cb-container cc-legal"><h1>'.esc_html($title).'</h1><p class="cc-demo">DEMO · texto técnico ficticio. Pendiente de datos y revisión del titular. No habilita ventas reales.</p>[commerce_legal_navigation]'.($key==='withdrawal'?'[commerce_legal_form]':'<p>Contenido de laboratorio para verificar publicación e instalación de páginas.</p>').'[commerce_legal_identity]</div>';
        $pages[$key]=h11_page($title,'h11-'.$key,'<!-- wp:shortcode -->'.$content.'<!-- /wp:shortcode -->',true);
    }
    $legal=['trade_name'=>'H11 QA DEMO','legal_name'=>'Pendiente de titular real · DEMO','email'=>'legal@h11.example.test','notification_email'=>'operations@h11.example.test','website'=>home_url('/'),'pages'=>array_intersect_key($pages,array_flip(['privacy','terms','shipping','returns','withdrawal']))];
    update_option(\CommerceCore\Legal::OPTION,$legal,false); update_option('wp_page_for_privacy_policy',$pages['privacy']); update_option('woocommerce_terms_page_id',$pages['terms']);
    $lookbook=['imageId'=>(int)$image,'imageUrl'=>wp_get_attachment_url($image),'imageAlt'=>'Imagen técnica original H11 QA','imageWidth'=>640,'imageHeight'=>480,'hotspots'=>[['productId'=>$simple_id,'x'=>40,'y'=>50],['productId'=>$variable_id,'x'=>60,'y'=>50]]];
    $pages['lookbook']=h11_page('Lookbook H11 DEMO','h11-lookbook','<!-- wp:paragraph --><p>DEMO · imagen técnica sin contenido comercial.</p><!-- /wp:paragraph --><!-- wp:commerce-lookbook/shoppable-lookbook '.wp_json_encode($lookbook).' /-->');
    $home='<!-- wp:html --><section class="cb-container cb-home-hero"><h1>H11 QA DEMO</h1><p>Instalación técnica desechable. Sin ventas, identidad fiscal ni pagos reales.</p><a class="cb-button" href="'.esc_url(wc_get_page_permalink('shop')).'">Ver catálogo DEMO</a></section><!-- /wp:html --><!-- wp:commerce-base/product-grid {"limit":2} /--><!-- wp:commerce-lookbook/shoppable-lookbook '.wp_json_encode($lookbook).' /-->';
    $pages['home']=h11_page('H11 QA DEMO','h11-inicio',$home); update_option('show_on_front','page'); update_option('page_on_front',$pages['home']);
    $menu=wp_create_nav_menu('H11 QA DEMO'); h11_fail(!is_wp_error($menu),'Menu creation failed.');
    foreach(['shop','lookbook','myaccount'] as $key) { wp_update_nav_menu_item($menu,0,['menu-item-title'=>get_the_title($pages[$key]),'menu-item-type'=>'post_type','menu-item-object'=>'page','menu-item-object-id'=>$pages[$key],'menu-item-status'=>'publish']); }
    set_theme_mod('nav_menu_locations',['primary'=>$menu]);
    $users=[]; $run=bin2hex(random_bytes(5));
    foreach(['customer'=>'customer','manager'=>'shop_manager'] as $key=>$role) { $login='h11_'.$key.'_'.$run; $pass=wp_generate_password(40,true,true); $email=$login.'@h11.example.test'; $id=wp_insert_user(['user_login'=>$login,'user_pass'=>$pass,'user_email'=>$email,'role'=>$role,'display_name'=>'Persona QA DEMO H11']); h11_fail(!is_wp_error($id),'Synthetic user creation failed.'); $users[$key]=['id'=>(int)$id,'username'=>$login,'password'=>$pass,'email'=>$email]; }
    $state=['installation'=>'h11-production-simulation','users'=>$users,'products'=>['simple'=>$simple_id,'variable'=>$variable_id,'variations'=>$variations],'pages'=>$pages,'attachment'=>(int)$image];
    h11_json_private($private,$state);
    update_option('commerce_h11_fixture',['phase'=>'prepared','products'=>$state['products'],'pages'=>$pages,'attachment'=>(int)$image,'users'=>array_map(static fn($u)=>$u['id'],$users)],false);
    update_option('permalink_structure','/%postname%/'); flush_rewrite_rules();
    echo wp_json_encode(['result'=>'prepared','synthetic'=>true,'products'=>2,'variations'=>2,'pages'=>count($pages),'users_created'=>2,'real_payments'=>false,'real_mail'=>false],JSON_PRETTY_PRINT)."\n";
    return;
}
if ($mode === 'exercise-email') {
    h11_fail((int)wp_parse_url(home_url(),PHP_URL_PORT)===8184,'Mail lifecycle runs only in source H11 lab.');
    $fixture=json_decode(file_get_contents($private),true,512,JSON_THROW_ON_ERROR); $state=get_option('commerce_h11_fixture');
    h11_fail(($state['phase']??'')==='prepared' && is_file('/h11-private/h11-checkout.json'), 'Complete native browser checkout before exercising mail.');
    $checkout=json_decode(file_get_contents('/h11-private/h11-checkout.json'),true,512,JSON_THROW_ON_ERROR); $order=wc_get_order($checkout['order_id']);
    h11_fail($order && $order->get_payment_method()==='bacs' && $order->has_status('on-hold') && $order->get_customer_id()===$fixture['users']['customer']['id'], 'Expected own synthetic BACS checkout order.');
    WC()->mailer();
    $create=static function() use($fixture) { $o=wc_create_order(['customer_id'=>$fixture['users']['customer']['id'],'created_via'=>'h11-isolated-qa']); h11_fail(!is_wp_error($o),'Order API failed.'); $o->add_product(wc_get_product($fixture['products']['simple']),1); $o->set_address(['first_name'=>'Persona QA','last_name'=>'H11 DEMO','address_1'=>'Calle ficticia 123','city'=>'Ciudad Autónoma de Buenos Aires','state'=>'C','postcode'=>'1414','country'=>'AR','email'=>$fixture['users']['customer']['email'],'phone'=>'1100000000'],'billing'); $o->set_payment_method('bacs'); $o->set_payment_method_title('Transferencia bancaria · DEMO'); $o->calculate_totals(); $o->save(); $o->update_status('on-hold','H11 QA local: sin pago ni movimiento de dinero.'); return $o; };
    $completed=$create(); $completed->update_status('processing','H11 QA local.'); $completed->update_status('completed','H11 QA local.');
    $cancelled=$create(); $cancelled->update_status('cancelled','H11 QA local.');
    $refunded=$create(); $refunded->update_status('processing','H11 QA local.'); $refunded->update_status('completed','H11 QA local.');
    $refund=wc_create_refund(['order_id'=>$refunded->get_id(),'amount'=>$refunded->get_total(),'reason'=>'H11 QA · registro ficticio sin devolución externa.','refund_payment'=>false,'restock_items'=>false]); h11_fail(!is_wp_error($refund),'Native synthetic refund API failed.');
    $reset=retrieve_password($fixture['users']['customer']['username']); h11_fail($reset===true,'Native password recovery failed.');
    $request=\CommerceCore\Legal::submit(['cc_name'=>'Persona QA H11 DEMO','cc_email'=>'receipt@h11.example.test','cc_reference'=>'H11 QA','cc_message'=>'Ensayo API local de recepción. Sin compra real.','cc_token'=>bin2hex(random_bytes(32)),'cc_website'=>''],'127.0.0.11'); h11_fail(!is_wp_error($request),'Supported local legal API failed.');
    $state['phase']='exercised'; $state['orders']=['bacs'=>(int)$order->get_id(),'completed'=>$completed->get_id(),'cancelled'=>$cancelled->get_id(),'refunded'=>$refunded->get_id(),'refund'=>$refund->get_id()]; $state['legal_api_id']=(int)$request->id; update_option('commerce_h11_fixture',$state,false);
    echo wp_json_encode(['result'=>'exercised','order_ids'=>$state['orders'],'legal_request_id'=>(int)$request->id,'native_password_recovery'=>true,'financial_refund_requested'=>false,'real_mail'=>false],JSON_PRETTY_PRINT)."\n";
    return;
}
if ($mode === 'exercise-operations') {
    global $wpdb;
    h11_fail((int)wp_parse_url(home_url(),PHP_URL_PORT)===8184,'Operation probes run only in source H11 lab.');
    $state=(array)get_option('commerce_h11_fixture'); h11_fail(($state['phase']??'')==='exercised'&&!isset($state['operations']), 'Run operations once, after native order and email QA.');
    // A fresh WooCommerce install may still have the native HybridStore migration pending.
    // Finish it with the supported CLI, before a group-scoped runner can claim DBStore jobs.
    $migration=WP_CLI::runcommand('action-scheduler migrate --batch-size=100 --quiet',['return'=>'all','exit_error'=>false]);
    h11_fail($migration->return_code===0,'Native Action Scheduler migration failed; preserve this isolated installation.');
    // The native WP-Cron hook removes an expired, synthetic transient. Never run unrelated cron hooks.
    $key='h11_operational_probe_'.bin2hex(random_bytes(5)); set_transient($key,'synthetic-h11',300); update_option('_transient_timeout_'.$key,time()-120);
    h11_fail($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'_transient_'.$key))==='synthetic-h11','Transient sentinel was not persisted.');
    wp_schedule_single_event(time()-1,'delete_expired_transients');
    $cron=WP_CLI::runcommand('cron event run delete_expired_transients --quiet',['return'=>'all','exit_error'=>false]);
    $cron_ok=$cron->return_code===0 && $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'_transient_'.$key))===null;
    h11_fail($cron_ok,'Native WP-Cron did not clean the synthetic expired transient.');
    // A native WooCommerce Action Scheduler callback cleans only expired sessions in this isolated lab.
    $session_key='h11-probe-'.bin2hex(random_bytes(8));
    h11_fail($wpdb->insert($wpdb->prefix.'woocommerce_sessions',['session_key'=>$session_key,'session_value'=>serialize(['h11'=>'synthetic']),'session_expiry'=>time()-120])===1,'Expired synthetic session could not be created.');
    $existing=as_get_scheduled_actions(['hook'=>'woocommerce_cleanup_sessions','group'=>'h11-qa-operational','status'=>'pending','per_page'=>1],'ids');
    $action=$existing?(int)$existing[0]:as_schedule_single_action(time()-1,'woocommerce_cleanup_sessions',[],'h11-qa-operational',true); h11_fail($action>0,'Native Action Scheduler could not enqueue the isolated probe.');
    $runner=WP_CLI::runcommand('action-scheduler run --group=h11-qa-operational --batch-size=1 --batches=1 --quiet',['return'=>'all','exit_error'=>false]);
    $action_ok=$runner->return_code===0 && ActionScheduler::store()->get_status($action)==='complete' && !$wpdb->get_var($wpdb->prepare('SELECT session_id FROM '.$wpdb->prefix.'woocommerce_sessions WHERE session_key=%s',$session_key));
    h11_fail($action_ok,'Native Action Scheduler did not complete the WooCommerce session cleanup probe.');
    $state['operations']=['wp_cron_executed'=>true,'action_scheduler_executed'=>true,'native_scheduler_migration_completed'=>true,'action_id'=>(int)$action]; update_option('commerce_h11_fixture',$state,false);
    echo wp_json_encode(['result'=>'passed','wp_cron_executed'=>true,'native_expired_transient_removed'=>true,'action_scheduler_executed'=>true,'native_scheduler_migration_completed'=>true,'native_expired_session_removed'=>true,'action_id'=>(int)$action],JSON_PRETTY_PRINT)."\n";
    return;
}
if ($mode === 'exercise-code-update') {
    h11_fail((int)wp_parse_url(home_url(),PHP_URL_PORT)===8184 && is_file('/h11-private/h11-snapshot.json'), 'Create post-backup data only in the source H11 lab after its restoration baseline.');
    $state=(array)get_option('commerce_h11_fixture'); h11_fail(!isset($state['post_backup_order_id']), 'Post-backup probe already exists.');
    $fixture=json_decode(file_get_contents($private),true,512,JSON_THROW_ON_ERROR);
    $order=wc_create_order(['customer_id'=>$fixture['users']['customer']['id'],'created_via'=>'h11-post-backup-probe']); h11_fail(!is_wp_error($order),'Native order API failed.');
    $order->add_product(wc_get_product($fixture['products']['simple']),1); $order->set_billing_email($fixture['users']['customer']['email']); $order->set_payment_method('bacs'); $order->set_payment_method_title('Transferencia bancaria · DEMO'); $order->calculate_totals(); $order->save();
    $state['post_backup_order_id']=$order->get_id(); update_option('commerce_h11_fixture',$state,false);
    echo wp_json_encode(['result'=>'passed','post_backup_order_id'=>$order->get_id(),'native_order_created_after_backup'=>true,'payment_attempted'=>false,'purpose'=>'Code update must preserve newer business data. Original restore baseline remains separate.'],JSON_PRETTY_PRINT)."\n";
    return;
}
WP_CLI::error('Use prepare|exercise-email|exercise-operations|exercise-code-update.');
