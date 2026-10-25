<?php
/** CLI-only public DEMO configuration for a proven clean DEMO installation. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
if (!in_array(wp_get_environment_type(), ['local','staging'], true)
    || get_option('commerce_demo_public_installation') !== 'public-demo-v1'
    || !class_exists('WooCommerce') || !class_exists('CommerceCore\\Legal')) {
    WP_CLI::error('An explicitly marked, isolated, clean DEMO is required.');
}
global $wpdb;
if (wc_get_orders(['limit'=>1]) || count(get_users()) !== 1) { WP_CLI::error('Orders/additional users forbidden.'); }
foreach (get_users() as $u) {
    if (!str_ends_with($u->user_email, '.test') || !in_array('administrator', $u->roles, true)) { WP_CLI::error('Only a synthetic DEMO admin is allowed.'); }
}
// A storage switch or excluded draft status must not hide historical orders.
if ((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund','shop_order_placehold')")) {
    WP_CLI::error('Legacy order history forbidden.');
}
foreach (['wc_orders','woocommerce_order_items'] as $suffix) {
    $t=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t)))===$t
        && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$t`")) { WP_CLI::error('Order storage must be empty.'); }
}
foreach (['commerce_legal_requests','commerce_legal_events'] as $suffix) {
    $t=$wpdb->prefix.$suffix;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t)))===$t
        && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$t`")) { WP_CLI::error('Legal history forbidden.'); }
}
require_once ABSPATH.'wp-admin/includes/plugin.php';
if (file_exists(WP_PLUGIN_DIR.'/woocommerce-mercadopago')) { WP_CLI::error('Remove Mercado Pago from this DEMO before preparation.'); }
$brand=get_bloginfo('name');
foreach (['shop'=>'Tienda','cart'=>'Carrito','checkout'=>'Finalizar compra · DEMO','myaccount'=>'Mi cuenta · DEMO'] as $key=>$title) {
    $id=wc_get_page_id($key);
    if ($id>0) { wp_update_post(['ID'=>$id,'post_title'=>$title]); }
}
$options=['blog_public'=>'0','users_can_register'=>'0','show_avatars'=>'0',
    'woocommerce_enable_myaccount_registration'=>'no','woocommerce_enable_signup_and_login_from_checkout'=>'no',
    'woocommerce_enable_guest_checkout'=>'no','woocommerce_feature_order_attribution_enabled'=>'no',
    'woocommerce_coming_soon'=>'no','woocommerce_store_pages_only'=>'no',
    'woocommerce_store_address'=>'','woocommerce_store_address_2'=>'','woocommerce_store_city'=>'','woocommerce_store_postcode'=>'',
    'woocommerce_email_from_address'=>'store@demo.example.test','commerce_base_exit_intent'=>['enabled'=>false],
    'woocommerce_bacs_accounts'=>[]];
foreach ($options as $k=>$v) { update_option($k,$v); }
foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
    $settings=get_option('woocommerce_'.$gateway->id.'_settings',[]);$settings['enabled']='no';
    update_option('woocommerce_'.$gateway->id.'_settings',$settings);
}
foreach (WC()->mailer()->get_emails() as $email) {
    $settings=get_option('woocommerce_'.$email->id.'_settings',[]);$settings['enabled']='no';
    $settings['recipient']='operations@demo.example.test';update_option('woocommerce_'.$email->id.'_settings',$settings);
}
$intro='<p><strong>Tienda de demostración.</strong> Los productos, precios, promociones y envíos son ilustrativos. Podés explorar el catálogo y el carrito. No se realizan compras, pagos, entregas ni registros de cuenta.</p>';
$pages=[];
foreach (['privacy'=>['privacidad-demo','Privacidad de la demo'],'terms'=>['terminos-demo','Condiciones de la demo'],
    'shipping'=>['envios-demo','Envíos ilustrativos'],'returns'=>['cambios-demo','Cambios y devoluciones · DEMO'],
    'withdrawal'=>['arrepentimiento-demo','Botón de arrepentimiento · DEMO']] as $key=>$definition) {
    $config=\CommerceCore\Legal::config();$id=(int)($config['pages'][$key]??0);
    if (!$id) { $p=get_page_by_path($definition[0]);$id=$p?$p->ID:0; }
    $body=$intro;
    if ($key==='privacy') { $body.='<p>No ingreses datos personales. Se utiliza una sesión de WooCommerce para conservar tu carrito y la preferencia de apariencia. El servidor de alojamiento puede procesar datos técnicos de conexión según su configuración y retención. No hay analítica, publicidad, cuentas de clientes ni correos desde esta demo.</p>'; }
    elseif ($key==='withdrawal') { $body.='<p>No se reciben solicitudes reales por esta demostración. Al no existir compras en esta demo, no se genera un trámite. La funcionalidad legal completa está disponible para instalaciones comerciales correctamente configuradas.</p>[commerce_legal_form]'; }
    elseif ($key==='shipping') { $body.='<p>Los valores y promociones de envío que se muestran son ejemplos. No hay entregas ni transportistas contratados para esta demo; no ingreses una dirección.</p>'; }
    else { $body.='<p>Este contenido presenta la estructura de una tienda. No constituye una oferta comercial ni recibe compras o gestiones reales.</p>'; }
    $body.='[commerce_legal_navigation]';
    $result=wp_insert_post(wp_slash(['ID'=>$id,'post_type'=>'page','post_status'=>'publish','post_title'=>$definition[1],
        'post_name'=>$id?get_post_field('post_name',$id):$definition[0],
        'post_content'=>'<!-- wp:shortcode --><div class="cb-container cc-legal"><h1>'.esc_html($definition[1]).'</h1>'.$body.'</div><!-- /wp:shortcode -->']),true);
    if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
    $pages[$key]=(int)$result;update_post_meta($result,'_commerce_legal_demo','1');update_post_meta($result,'_commerce_public_demo_reviewed','1');update_post_meta($result,'_wp_page_template','page-institutional');
}
update_option('commerce_core_legal',['pages'=>$pages,'trade_name'=>$brand.' · demostración','notification_email'=>'legal@demo.example.test']);
update_option('wp_page_for_privacy_policy',$pages['privacy']);update_option('woocommerce_terms_page_id',$pages['terms']);
// The historical fixtures describe QA checkout/accounts. Correct those copied pages only.
foreach (get_posts(['post_type'=>'page','post_status'=>'publish','numberposts'=>-1]) as $page) {
    if ($page->post_name==='contacto') {
        wp_update_post(wp_slash(['ID'=>$page->ID,'post_content'=>'<!-- wp:html --><div class="cb-container cc-legal"><h1>Contacto · DEMO</h1>'.$intro.'<p>Esta tienda de demostración no recibe consultas ni operaciones comerciales. No ingreses datos personales. Los canales de contacto del comercio son ilustrativos y no están habilitados.</p></div><!-- /wp:html -->']));
    } elseif (in_array($page->post_name,['preguntas-frecuentes','faq'],true)) {
        wp_update_post(wp_slash(['ID'=>$page->ID,'post_content'=>'<!-- wp:html --><div class="cb-container cc-legal"><h1>Preguntas frecuentes · DEMO</h1>'.$intro.'<h2>¿Puedo probar el carrito?</h2><p>Sí. Elegí variantes, agregá productos y cambiá cantidades. El checkout está bloqueado.</p><h2>¿Puedo comprar, crear una cuenta o solicitar una devolución?</h2><p>Esta demo no recibe compras, pagos, registros ni solicitudes reales.</p></div><!-- /wp:html -->']));
    } else {
        $content=str_replace([
            'El carrito reúne tus productos y el checkout muestra las opciones disponibles para tu pedido.',
            'Tienda ficticia · precios y compras de prueba',
            'Elegí tus variantes y revisá el total antes de confirmar. BACS de prueba, sin transferencias reales.',
            'Comprá con calma',
        ],[
            'El carrito reúne tu selección ilustrativa. Esta demo no permite finalizar compras.',
            'Tienda de demostración · productos y precios ilustrativos',
            'Elegí variantes y revisá tu selección en el carrito. El checkout y los pedidos están bloqueados.',
            'Explorá con calma',
        ],$page->post_content);
        if ($content!==$page->post_content) { wp_update_post(wp_slash(['ID'=>$page->ID,'post_content'=>$content])); }
    }
}
update_option('commerce_d1_public_prepared','1');
WP_CLI::success('Public DEMO prepared: synthetic content, no transactions, no registration or mail. Enable environment guard separately.');
