<?php
/** Clean, client-owned DEMO importer. Runtime theme/plugins never read these fixtures. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['seed','inspect','verify','certify','recover-early','repair-style','open-demo','finalize-demo'], true)) { WP_CLI::error('Use seed|inspect|verify; migrations require an inspected installation.'); }
$url = untrailingslashit(home_url());
if (wp_get_environment_type() !== 'local' || wp_parse_url($url, PHP_URL_HOST) !== 'localhost' || wp_parse_url($url, PHP_URL_PORT) === 8080 || get_option('commerce_h10_installation') !== 'origen-casa') { WP_CLI::error('An independently provisioned local ORIGEN CASA installation is required; never run on TRAMA.'); }
if (!class_exists('WooCommerce') || !class_exists('CommerceCore\\Legal') || get_stylesheet() !== 'commerce-base') { WP_CLI::error('Activate WooCommerce, Commerce Core and Commerce Base first.'); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php')) { WP_CLI::error('Deactivate the official Mercado Pago plugin in this independent DEMO.'); }
foreach (get_users(['fields'=>['user_email']]) as $user) { if (!preg_match('/@[^@\s]+\.test$/iD', $user->user_email)) { WP_CLI::error('Only fictitious .test accounts are allowed in this DEMO importer.'); } }
$root = '/opt/client-fixture/';
$client = json_decode(file_get_contents($root.'client.json'), true, 512, JSON_THROW_ON_ERROR);
$content = json_decode(file_get_contents($root.'content.json'), true, 512, JSON_THROW_ON_ERROR);
if (($client['fixture'] ?? '') !== 'origen-casa') { WP_CLI::error('Unexpected client fixture.'); }
$GLOBALS['h10_owned'] = (array) get_option('commerce_h10_fixture', []);
$GLOBALS['h10_root'] = $root;
$state =& $GLOBALS['h10_owned'];
function h10_hash($value) { return hash('sha256', wp_json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
function h10_base_options($client) {
    $brand=$client['brand'];$commerce=$client['commerce'];
        $options=['blogname'=>$brand['name'],'blogdescription'=>$brand['description'],'blog_public'=>'0','timezone_string'=>'America/Argentina/Buenos_Aires','date_format'=>'d/m/Y','time_format'=>'H:i','show_on_front'=>'page','woocommerce_currency'=>$commerce['currency'],'woocommerce_price_thousand_sep'=>'.','woocommerce_price_decimal_sep'=>',','woocommerce_price_num_decimals'=>'0','woocommerce_default_country'=>$commerce['country'],'woocommerce_allowed_countries'=>'specific','woocommerce_specific_allowed_countries'=>['AR'],'woocommerce_ship_to_countries'=>'specific','woocommerce_specific_ship_to_countries'=>['AR'],'woocommerce_weight_unit'=>'kg','woocommerce_dimension_unit'=>'cm','woocommerce_calc_taxes'=>'no','woocommerce_enable_guest_checkout'=>'yes','woocommerce_enable_signup_and_login_from_checkout'=>'yes','woocommerce_enable_myaccount_registration'=>'yes','woocommerce_registration_generate_password'=>'yes','woocommerce_registration_generate_username'=>'yes','woocommerce_enable_coupons'=>'yes','woocommerce_hide_out_of_stock_items'=>'no','woocommerce_custom_orders_table_enabled'=>'yes','woocommerce_custom_orders_table_data_sync_enabled'=>'no','woocommerce_shipping_hide_rates_when_free'=>'yes','woocommerce_bacs_settings'=>$commerce['bacs'],'woocommerce_bacs_accounts'=>[],'woocommerce_cod_settings'=>['enabled'=>'no'],'woocommerce_cheque_settings'=>['enabled'=>'no'],'woocommerce_feature_order_attribution_enabled'=>'no','woocommerce_email_from_name'=>$brand['name'],'woocommerce_email_from_address'=>$brand['contact'],'woocommerce_email_reply_to_enabled'=>'yes','woocommerce_email_reply_to_name'=>$brand['name'],'woocommerce_email_reply_to_address'=>$brand['contact'],'woocommerce_registration_privacy_policy_text'=>'Usamos tus datos para gestionar tu cuenta DEMO, compras y atención. Consultá nuestra [privacy_policy].','woocommerce_checkout_privacy_policy_text'=>'Usamos los datos de esta compra DEMO para gestionar pedido, entrega y atención. Consultá nuestra [privacy_policy].'];
        $options=array_replace($options,$client['email'],['woocommerce_coming_soon'=>'no','woocommerce_store_pages_only'=>'no','woocommerce_email_auto_sync_with_theme'=>'no']);
        foreach(['new_order','cancelled_order','failed_order','customer_on_hold_order','customer_processing_order','customer_completed_order','customer_new_account','customer_reset_password'] as $kind){$value=['enabled'=>'yes','email_type'=>'multipart'];if(in_array($kind,['new_order','cancelled_order','failed_order'],true)){$value['recipient']=$brand['operations'];}$options['woocommerce_'.$kind.'_settings']=$value;}
    return $options;
}
function h10_post($id) {
    $p=get_post($id); if (!$p) { return null; }
    return [$p->post_type,$p->post_status,$p->post_name,$p->post_title,$p->post_content,$p->post_excerpt,get_post_meta($id,'_wp_page_template',true),get_post_thumbnail_id($id),get_post_meta($id,'_commerce_legal_demo',true)];
}
function h10_product($id) {
    $p=wc_get_product($id);if(!$p){return null;}$data=$p->get_data();
    unset($data['date_created'],$data['date_modified'],$data['sales'],$data['total_sales'],$data['rating_counts'],$data['average_rating'],$data['review_count'],$data['meta_data']);
    $data['attributes']=array_map(static fn($a)=>$a instanceof WC_Product_Attribute?$a->get_data():$a,$data['attributes']);
    $data['children']=[];foreach($p->get_children() as $child){$data['children'][$child]=h10_product($child);}
    $data['care']=$p->get_meta('_cb_care');return $data;
}
function h10_term_snapshot($id,$tax) { $t=get_term($id,$tax);return $t&&!is_wp_error($t)?[$t->name,$t->slug,$t->description,get_term_meta($id,'thumbnail_id',true)]:null; }
function h10_menu($id) {
    $menu=wp_get_nav_menu_object($id);$out=['name'=>$menu?$menu->name:null,'items'=>[]];
    foreach(wp_get_nav_menu_items($id)?:[] as $item){$out['items'][]=[$item->ID,$item->title,$item->url,$item->object_id,$item->object,$item->type,$item->menu_order,$item->menu_item_parent];}return $out;
}
function h10_shipping() {
    $out=[];foreach(WC_Shipping_Zones::get_zones() as $row){$z=new WC_Shipping_Zone($row['zone_id']);$methods=[];foreach($z->get_shipping_methods() as $m){$methods[]=[$m->id,$m->instance_id,$m->enabled,get_option($m->get_instance_option_key())];}$out[]=['id'=>$z->get_id(),'name'=>$z->get_zone_name(),'locations'=>$z->get_zone_locations(),'methods'=>$methods];}return $out;
}
function h10_configuration($names,$normalize=true) { $out=[];foreach($names as $name){$out[$name]=get_option($name,null);}$mods=get_theme_mods();if($normalize&&!array_key_exists('custom_css_post_id',$mods)){$mods['custom_css_post_id']=-1;}return ['options'=>$out,'theme_mods'=>$mods,'shipping'=>h10_shipping()]; }
function h10_configuration_hashes($names) { $data=h10_configuration($names);$out=[];foreach($data['options'] as $name=>$value){$out['option:'.$name]=h10_hash($value);}foreach($data['theme_mods'] as $name=>$value){$out['theme_mod:'.$name]=h10_hash($value);}$out['shipping']=h10_hash($data['shipping']);return $out; }
function h10_conflicts($state,$source_hash) {
    $errors=[];if(($state['phase']??'')!=='complete'){$errors[]='An interrupted import must be inspected; recreate only the independently named disposable installation after review.';return $errors;}
    if(($state['source_hash']??'')!==$source_hash){$errors[]='Fixture sources changed; prepare an explicit migration instead of overwriting the client.';}
    foreach($state['products'] as $sku=>$row){if(h10_hash(h10_product($row['id']))!==$row['hash']){$errors[]='Product, price, stock or variation changed; preserved: '.$sku;}}
    foreach($state['pages'] as $key=>$row){if(h10_hash(h10_post($row['id']))!==$row['hash']){$errors[]='Native page edited; preserved: '.$key;}}
    foreach($state['terms'] as $key=>$row){if(h10_hash(h10_term_snapshot($row['id'],$row['taxonomy']))!==$row['hash']){$errors[]='Native taxonomy edited; preserved: '.$key;}}
    foreach($state['media'] as $file=>$row){$path=get_attached_file($row['id']);if(!$path||!is_file($path)||hash_file('sha256',$path)!==$row['hash']||get_post_meta($row['id'],'_wp_attachment_image_alt',true)!==$row['alt']){$errors[]='Native media edited; preserved: '.$file;}}
    if(h10_hash(h10_configuration($state['option_names']))!==$state['configuration_hash']){$changed=array_keys(array_diff_assoc(h10_configuration_hashes($state['option_names']),$state['configuration_hashes']??[]));$errors[]='Native commerce, identity, styles or shipping configuration edited; preserved.'.(!empty($state['configuration_hashes'])?' Changed fields: '.implode(', ',$changed):'');}
    if(h10_hash(h10_menu($state['menu_id']))!==$state['menu_hash']){$errors[]='Native navigation edited; preserved.';}
    if(h10_hash(get_post_field('post_content',$state['style_id']))!==$state['style_hash']){$errors[]='Native Global Styles edited; preserved.';}
    $coupon=new WC_Coupon($state['coupon_id']);if(h10_hash([$coupon->get_code(),$coupon->get_discount_type(),$coupon->get_amount(),$coupon->get_individual_use(),$coupon->get_minimum_amount()])!==$state['coupon_hash']){$errors[]='Native coupon edited; preserved.';}
    return $errors;
}
function h10_media($file,$alt) {
    $state=&$GLOBALS['h10_owned'];if(isset($state['media'][$file])){return $state['media'][$file]['id'];}
    require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
    $tmp=wp_tempnam($file);if(!copy($GLOBALS['h10_root'].'assets/'.$file,$tmp)){throw new RuntimeException('Cannot copy asset '.$file);}
    $id=media_handle_sideload(['name'=>$file,'tmp_name'=>$tmp],0,$alt);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}
    update_post_meta($id,'_wp_attachment_image_alt',$alt);update_post_meta($id,'_commerce_h10_owner','origen-casa');
    $state['media'][$file]=['id'=>$id,'hash'=>hash_file('sha256',get_attached_file($id)),'alt'=>$alt];return $id;
}
function h10_term($tax,$name,$slug,$description='') {
    $key=$tax.'/'.$slug;if(term_exists($slug,$tax)){throw new RuntimeException('Unmanaged term collision: '.$key);}
    $r=wp_insert_term($name,$tax,['slug'=>$slug,'description'=>$description]);if(is_wp_error($r)){throw new RuntimeException($r->get_error_message());}
    $id=(int)$r['term_id'];update_term_meta($id,'_commerce_h10_owner','origen-casa');$GLOBALS['h10_owned']['terms'][$key]=['id'=>$id,'taxonomy'=>$tax];return $id;
}
function h10_page($key,$title,$slug,$body,$legal=false) {
    if(get_page_by_path($slug)){throw new RuntimeException('Unmanaged page collision: '.$slug);}
    $id=wp_insert_post(wp_slash(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$body]),true);
    if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}update_post_meta($id,'_commerce_h10_owner','origen-casa');
    if($legal){update_post_meta($id,'_commerce_legal_demo','1');}update_post_meta($id,'_wp_page_template',$key==='home'?'default':'page-institutional');
    $GLOBALS['h10_owned']['pages'][$key]=['id'=>(int)$id];return (int)$id;
}
function h10_copy($title,$lead,$sections,$legal=false,$form=false) {
    $out='<div class="cb-container cc-legal"><div class="cc-legal-heading"><p class="cb-eyebrow">ORIGEN CASA / DEMO</p><h1>'.esc_html($title).'</h1><p class="cc-legal-lead">'.esc_html($lead).'</p><p class="cc-demo"><strong>DEMO · Contenido ficticio.</strong> Pendiente de datos del titular y revisión antes de publicar. Sin operaciones reales.</p></div>';
    if($legal){$out.='<div class="cc-legal-layout">[commerce_legal_navigation]<div class="cc-legal-copy">';}if($form){$out.='[commerce_legal_form]';}
    foreach($sections as $heading=>$paras){$out.='<h2>'.esc_html($heading).'</h2>';foreach($paras as $p){$out.='<p>'.esc_html($p).'</p>';}}
    $out.='[commerce_legal_identity]';if($legal){$out.='</div></div>';}$out.='</div>';return '<!-- wp:shortcode -->'.$out.'<!-- /wp:shortcode -->';
}
// The fingerprint includes every versioned asset; no remote image/download is needed.
$files=[$root.'client.json',$root.'content.json',get_theme_file_path('styles/'.$client['brand']['style'].'.json')];
$required_assets=[$client['brand']['logo'],$client['brand']['icon'],$content['hero']['image']];
foreach(array_merge($content['products'],$content['categories']) as $row){$required_assets[]=$row['image'];}
foreach(array_unique($required_assets) as $asset){if(!is_file($root.'assets/'.$asset)){WP_CLI::error('Missing required asset before import: '.$asset);}}
foreach(glob($root.'assets/*') as $file){if(is_file($file)){$files[]=$file;}}sort($files);$hashes=[];foreach($files as $file){if(!is_file($file)){WP_CLI::error('Missing fixture source: '.basename($file));}$hashes[basename($file)]=hash_file('sha256',$file);}$source_hash=h10_hash($hashes);
if($mode==='recover-early') {
    global $wpdb;
    // Narrow recovery for the observed early branding/media failure only. Never adopt a partly built catalog.
    if(($state['phase']??'')!=='preparing'||($state['source_hash']??'')!==$source_hash||!empty($state['products'])||!empty($state['pages'])||!empty($state['terms'])||!empty($state['media'])){WP_CLI::error('Recovery only accepts the inspected early-media failure, with its original sources.');}
    if(wc_get_products(['limit'=>1,'return'=>'ids'])||wc_get_orders(['limit'=>1,'return'=>'ids'])||get_users(['role'=>'customer','number'=>1])||(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\CommerceCore\Legal::table())||WC_Shipping_Zones::get_zones()||wc_get_attribute_taxonomies()||wp_get_nav_menu_object('ORIGEN CASA DEMO')){WP_CLI::error('Commercial resources exist; recovery refuses to delete or reset them.');}
    foreach(h10_base_options($client) as $name=>$value){if(h10_hash(get_option($name))!==h10_hash($value)){WP_CLI::error('Early native configuration differs; preserve and inspect option: '.$name);}}
    $owned=get_posts(['post_type'=>array_values(get_post_types()),'post_status'=>'any','posts_per_page'=>-1,'meta_key'=>'_commerce_h10_owner','meta_value'=>'origen-casa']);
    if(count($owned)!==2){WP_CLI::error('Recovery expects exactly the two original brand attachments.');}
    $ids=[];$assetnames=[];
    foreach($owned as $post){$file=get_attached_file($post->ID);$expected='';foreach([$client['brand']['logo'],$client['brand']['icon']] as $asset){if($post->post_type==='attachment'&&$file&&is_file($file)&&hash_file('sha256',$file)===hash_file('sha256',$root.'assets/'.$asset)){$expected=$asset;break;}}if(!$expected||in_array($expected,$assetnames,true)){WP_CLI::error('Owned attachment identity differs; preserve and inspect.');}$ids[]=$post->ID;$assetnames[]=$expected;}
    if(!in_array((int)get_theme_mod('custom_logo'),$ids,true)||!in_array((int)get_option('site_icon'),$ids,true)||get_option('woocommerce_email_header_image')!==wp_get_attachment_url((int)get_theme_mod('custom_logo'))||get_option('commerce_base_exit_intent')!==['enabled'=>false,'title'=>'Tu casa puede esperar','text'=>'Tu selección DEMO sigue en el carrito.']){WP_CLI::error('Early brand references changed; preserve and inspect.');}
    set_theme_mod('custom_logo',0);update_option('site_icon',0);update_option('woocommerce_email_header_image','');
    foreach($ids as $id){if(!wp_delete_attachment($id,true)){WP_CLI::error('Could not remove a verified generated attachment; inspect before retrying.');}}
    delete_option('commerce_h10_fixture');
    echo wp_json_encode(['result'=>'recovered','verified_generated_brand_attachments_removed'=>count($ids),'commercial_resources_deleted'=>0,'native_placeholder_preserved'=>true,'native_demo_options_retained'=>true,'next'=>'Run seed; the clean-shop guard still applies.'])."\n";return;
}
$conflicts=$state?h10_conflicts($state,$source_hash):[];
if($mode==='finalize-demo') {
    if(($state['phase']??'')!=='complete'||!empty($state['initial_demo_configuration_migration'])||get_option('blog_public')!=='0'){WP_CLI::error('This explicit migration requires the initial non-indexed local fixture.');}
    foreach($conflicts as $conflict){if($conflict!=='Fixture sources changed; prepare an explicit migration instead of overwriting the client.'&&!str_starts_with($conflict,'Native commerce, identity, styles or shipping configuration edited; preserved.')){WP_CLI::error('An unrelated owned resource changed; preserve and inspect: '.$conflict);}}
    $style_file=get_theme_file_path('styles/'.$client['brand']['style'].'.json');$new_raw=file_get_contents($style_file);
    $old_rule='calc(var(--cb-header-height) + 2.5rem) 4rem';$new_rule='calc(var(--cb-header-height) + 3.5rem) 4rem';
    if(substr_count($new_raw,$new_rule)!==1||str_contains($new_raw,$old_rule)){WP_CLI::error('Only the inspected 16px hero spacing delta is accepted.');}
    $old_raw=str_replace($new_rule,$old_rule,$new_raw);$old_hashes=$hashes;$old_hashes[basename($style_file)]=hash('sha256',$old_raw);
    if(h10_hash($old_hashes)!==$state['source_hash']){WP_CLI::error('Source changes extend beyond the exact inspected hero spacing rule.');}
    $native_style=json_decode(get_post_field('post_content',$state['style_id']),true);$old_preset=json_decode($old_raw,true);$new_preset=json_decode($new_raw,true);
    if(h10_hash(get_post_field('post_content',$state['style_id']))!==$state['style_hash']||h10_hash($native_style['settings']??[])!==h10_hash($old_preset['settings']??[])||h10_hash($native_style['styles']??[])!==h10_hash($old_preset['styles']??[])||wp_get_object_terms($state['style_id'],'wp_theme',['fields'=>'names'])!==['commerce-base']){WP_CLI::error('Native client style differs from the exact initial preset; preserve it.');}
    $palette=[];foreach($old_preset['settings']['color']['palette'] as $color){$palette[$color['slug']]=$color['color'];}
    $derived=['woocommerce_email_base_color'=>$palette['accent'],'woocommerce_email_background_color'=>$palette['background'],'woocommerce_email_body_background_color'=>$palette['background'],'woocommerce_email_text_color'=>$palette['text'],'woocommerce_email_footer_text_color'=>$palette['text-muted']];
    $prior=h10_configuration($state['option_names']);$email_changes=[];
    foreach($derived as $name=>$theme_value){$client_value=$client['email'][$name];$current=get_option($name);if(!in_array($current,[$client_value,$theme_value],true)){WP_CLI::error('Email color differs from both original fixture and diagnosed native theme sync: '.$name);}$saved=$state['configuration_hashes']['option:'.$name]??'';if($saved===h10_hash($client_value)){$prior['options'][$name]=$client_value;}elseif($saved===h10_hash($theme_value)){$prior['options'][$name]=$theme_value;}else{WP_CLI::error('Original email color hash is not one of the inspected values.');}if($current!==$client_value){$email_changes[]=$name;}}
    if(h10_hash($prior)!==$state['configuration_hash']){WP_CLI::error('Native configuration changed beyond the diagnosed five theme-synchronized email colors.');}
    update_option('woocommerce_email_auto_sync_with_theme','no',false);
    $native_style['styles']['css']=str_replace($old_rule,$new_rule,$native_style['styles']['css']);
    $result=wp_update_post(wp_slash(['ID'=>$state['style_id'],'post_content'=>wp_json_encode($native_style,JSON_UNESCAPED_SLASHES)]),true);if(is_wp_error($result)){WP_CLI::error($result->get_error_message());}
    foreach($derived as $name=>$ignored){update_option($name,$client['email'][$name],false);}
    foreach(['woocommerce_coming_soon','woocommerce_store_pages_only','woocommerce_email_auto_sync_with_theme'] as $name){update_option($name,'no',false);if(!in_array($name,$state['option_names'],true)){$state['option_names'][]=$name;}}
    $state['source_hash']=$source_hash;$state['style_hash']=h10_hash(get_post_field('post_content',$state['style_id']));$state['configuration_hash']=h10_hash(h10_configuration($state['option_names']));$state['configuration_hashes']=h10_configuration_hashes($state['option_names']);
    $state['initial_demo_configuration_migration']=['at_utc'=>gmdate('c'),'native_style_css_delta'=>'Hero top spacing +16px only','native_visibility'=>'Coming Soon disabled only for loopback non-indexed DEMO','native_email_sync'=>'Disabled automatic palette sync; explicit versioned client email palette retained'];
    WP_Theme_JSON_Resolver::clean_cached_data();wp_cache_flush();$remaining=h10_conflicts($state,$source_hash);if($remaining){WP_CLI::error('Migration requires inspection: '.implode(' ',$remaining));}update_option('commerce_h10_fixture',$state,false);
    echo wp_json_encode(['result'=>'migrated','native_style_id'=>$state['style_id'],'css_change'=>'Hero top spacing +16px; all other preset settings and styles unchanged','source_change_proven'=>'Reconstructed prior source fingerprint by reversing exactly one CSS string substitution','email_options_restored_to_versioned_client_palette'=>$email_changes,'email_auto_sync'=>get_option('woocommerce_email_auto_sync_with_theme'),'coming_soon'=>get_option('woocommerce_coming_soon'),'blog_public'=>get_option('blog_public'),'other_owned_resource_hashes_unchanged'=>true],JSON_PRETTY_PRINT)."\n";return;
}
if($mode==='open-demo') {
    if(($state['phase']??'')!=='complete'||$conflicts){WP_CLI::error('Opening the local DEMO requires every existing fixture/resource/configuration guard to pass.');}
    if(get_option('blog_public')!=='0'||wp_parse_url(home_url(),PHP_URL_HOST)!=='localhost'){WP_CLI::error('This migration only opens a loopback, non-indexed DEMO.');}
    foreach(['woocommerce_coming_soon','woocommerce_store_pages_only'] as $name){update_option($name,'no',false);if(!in_array($name,$state['option_names'],true)){$state['option_names'][]=$name;}}
    $state['configuration_hash']=h10_hash(h10_configuration($state['option_names']));$state['configuration_hashes']=h10_configuration_hashes($state['option_names']);$state['demo_store_opened_at_utc']=gmdate('c');update_option('commerce_h10_fixture',$state,false);
    echo wp_json_encode(['result'=>'configured','woocommerce_coming_soon'=>get_option('woocommerce_coming_soon'),'woocommerce_store_pages_only'=>get_option('woocommerce_store_pages_only'),'blog_public'=>get_option('blog_public'),'all_prior_owned_resource_hashes_preserved'=>true])."\n";return;
}
if($mode==='repair-style') {
    global $wpdb;
    if(($state['phase']??'')!=='complete'||!empty($state['native_style_repair'])||wc_get_orders(['limit'=>1,'return'=>'ids'])||get_users(['role'=>'customer','number'=>1])||(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\CommerceCore\Legal::table())){WP_CLI::error('Style repair is limited to the verified initial fixture before trading or legal QA.');}
    if(count($conflicts)!==1||!str_starts_with($conflicts[0],'Native commerce, identity, styles or shipping configuration edited; preserved.')){WP_CLI::error('The only accepted drift is the diagnosed lazy native CSS cache key. Preserve and inspect all other changes.');}
    $legacy=h10_configuration($state['option_names'],false);
    if(($legacy['theme_mods']['custom_css_post_id']??null)!==-1){WP_CLI::error('Unexpected custom CSS reference; preserve it.');}
    unset($legacy['theme_mods']['custom_css_post_id']);
    if(h10_hash($legacy)!==$state['configuration_hash']){WP_CLI::error('Configuration differs beyond the known lazily initialized CSS reference; preserve it.');}
    if(h10_hash(get_post_field('post_content',$state['style_id']))!==$state['style_hash']||wp_get_object_terms($state['style_id'],'wp_theme',['fields'=>'names'])!==[]){WP_CLI::error('The original unassociated native style must exactly match its stored fixture hash.');}
    $trash=[];$raw=$args[1]??'';
    if($raw&&!preg_match('/^\d+(,\d+)*$/D',$raw)){WP_CLI::error('Pass only inspected blank native style IDs, comma-separated.');}
    foreach($raw?array_map('intval',explode(',',$raw)):[] as $id){$p=get_post($id);$json=$p?json_decode($p->post_content,true):null;if(!$p||$id===$state['style_id']||$p->post_type!=='wp_global_styles'||$p->post_status!=='publish'||$json!==['version'=>3,'isGlobalStylesUserThemeJSON'=>true]||wp_get_object_terms($id,'wp_theme',['fields'=>'names'])!==[]){WP_CLI::error('An inspected empty style differs; preserve it.');}$trash[]=$id;}
    $r=wp_set_object_terms($state['style_id'],'commerce-base','wp_theme');if(is_wp_error($r)){WP_CLI::error($r->get_error_message());}
    foreach($trash as $id){if(!wp_trash_post($id)){WP_CLI::error('Could not trash a verified empty diagnostic style.');}}
    WP_Theme_JSON_Resolver::clean_cached_data();wp_cache_flush();
    $state['native_style_repair']=['at_utc'=>gmdate('c'),'original_style_id'=>$state['style_id'],'blank_diagnostic_styles_trashed'=>$trash,'previous_configuration_hash'=>$state['configuration_hash'],'only_configuration_drift'=>'Native custom_css_post_id=-1 cache initialization'];
    $state['configuration_hash']=h10_hash(h10_configuration($state['option_names']));$state['configuration_hashes']=h10_configuration_hashes($state['option_names']);update_option('commerce_h10_fixture',$state,false);
    echo wp_json_encode(['result'=>'repaired','style_id'=>$state['style_id'],'native_theme_terms'=>wp_get_object_terms($state['style_id'],'wp_theme',['fields'=>'names']),'blank_diagnostic_styles_trashed'=>$trash,'source_content_unchanged'=>h10_hash(get_post_field('post_content',$state['style_id']))===$state['style_hash'],'all_other_owned_resource_hashes_preserved'=>true,'configuration_change_proven'=>'Only absent to -1 native CSS reference; all other prior configuration hash input exactly matched.'],JSON_PRETTY_PRINT)."\n";return;
}
if($mode==='seed'&&$conflicts){echo wp_json_encode(['result'=>'conflict','conflicts'=>$conflicts],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";WP_CLI::halt(1);}
if($mode==='seed'&&!$state){
    global $wpdb;
    $orders=wc_get_orders(['limit'=>1,'return'=>'ids']);
    $requests=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\CommerceCore\Legal::table());
    if(wc_get_products(['limit'=>1,'return'=>'ids'])||$orders||get_users(['role'=>'customer','number'=>1])||$requests||WC_Shipping_Zones::get_zones()||wc_get_attribute_taxonomies()) { WP_CLI::error('First import requires a clean shop; no products, customers, orders, legal requests, shipping zones or custom attributes may exist.'); }
    foreach($content['products'] as $row){if(wc_get_product_id_by_sku($row['sku'])||get_page_by_path($row['slug'],OBJECT,'product')){WP_CLI::error('Unexpected product collision.');}}
    foreach($content['pages'] as $key=>$row){if(get_page_by_path($row['slug']??$key)){WP_CLI::error('Unexpected content page collision: '.$key);}}
    if(get_page_by_path('inicio')){WP_CLI::error('Unexpected Home collision.');}
    foreach(['shop','cart','checkout','myaccount'] as $kind){if(wc_get_page_id($kind)<1){WP_CLI::error('Create native WooCommerce pages before import.');}}
    if(!has_block('woocommerce/cart',get_post_field('post_content',wc_get_page_id('cart')))||!has_block('woocommerce/checkout',get_post_field('post_content',wc_get_page_id('checkout')))){WP_CLI::error('Native WooCommerce Cart and Checkout blocks are required.');}
    if(wp_get_nav_menu_object('ORIGEN CASA DEMO')){WP_CLI::error('Unmanaged navigation already exists.');}
    $state=['phase'=>'preparing','source_hash'=>$source_hash,'products'=>[],'pages'=>[],'terms'=>[],'media'=>[],'created_at_utc'=>gmdate('c')];
    update_option('commerce_h10_fixture',$state,false); // A killed import is visibly incomplete, never silently adopted.
    // Core can create HPOS tables (DDL). Finish this supported empty-store setting before the content transaction.
    update_option('woocommerce_custom_orders_table_enabled','yes',false);update_option('woocommerce_custom_orders_table_data_sync_enabled','no',false);
    $wpdb->query('START TRANSACTION');
    try {
        $brand=$client['brand'];$commerce=$client['commerce'];
        $options=h10_base_options($client);
        foreach($options as $name=>$value){update_option($name,$value,false);}
        $logo=h10_media($brand['logo'],$brand['name'].' · logotipo DEMO');$icon=h10_media($brand['icon'],'Símbolo de una casa dentro de un círculo · ORIGEN CASA DEMO');set_theme_mod('custom_logo',$logo);update_option('site_icon',$icon);$options['site_icon']=$icon;
        $options['woocommerce_email_header_image']=wp_get_attachment_url($logo);update_option('woocommerce_email_header_image',$options['woocommerce_email_header_image']);
        $options['commerce_base_exit_intent']=['enabled'=>false,'title'=>'Tu casa puede esperar','text'=>'Tu selección DEMO sigue en el carrito.'];update_option('commerce_base_exit_intent',$options['commerce_base_exit_intent'],false);
        $style=json_decode(file_get_contents(get_theme_file_path('styles/'.$brand['style'].'.json')),true,512,JSON_THROW_ON_ERROR);
        $style_content=wp_json_encode(['version'=>3,'isGlobalStylesUserThemeJSON'=>true,'settings'=>$style['settings']??[],'styles'=>$style['styles']??[]],JSON_UNESCAPED_SLASHES);
        $style_id=WP_Theme_JSON_Resolver::get_user_global_styles_post_id();if(!$style_id){$style_id=wp_insert_post(['post_type'=>'wp_global_styles','post_status'=>'publish','post_name'=>'wp-global-styles-commerce-base','post_title'=>'ORIGEN CASA · DEMO']);}
        // WP-CLI has user 0: native tax_input may silently skip assign_terms; associate explicitly through the taxonomy API.
        $associated=wp_set_object_terms($style_id,'commerce-base','wp_theme');if(is_wp_error($associated)){throw new RuntimeException($associated->get_error_message());}
        $updated=wp_update_post(wp_slash(['ID'=>$style_id,'post_content'=>$style_content]),true);if(is_wp_error($updated)){throw new RuntimeException($updated->get_error_message());}$state['style_id']=(int)$style_id;
        $taxterms=[];
        foreach($content['attributes'] as $slug=>$row){$attrid=wc_create_attribute(['name'=>$row['name'],'slug'=>$slug,'type'=>'select','order_by'=>'menu_order','has_archives'=>false]);if(is_wp_error($attrid)){throw new RuntimeException($attrid->get_error_message());}if(!taxonomy_exists('pa_'.$slug)){register_taxonomy('pa_'.$slug,['product'],['hierarchical'=>false,'label'=>$row['name']]);}foreach($row['terms'] as $term){$taxterms[$slug][$term]=h10_term('pa_'.$slug,$term,sanitize_title($term));}}
        delete_transient('wc_attribute_taxonomies');
        $categories=[];foreach($content['categories'] as $row){$id=h10_term('product_cat',$row['name'],$row['slug'],$row['description']);$categories[$row['slug']]=$id;update_term_meta($id,'thumbnail_id',h10_media($row['image'],$row['name'].' · selección ORIGEN CASA DEMO'));}
        foreach($content['products'] as $index=>$row){
            $p=$row['type']==='variable'?new WC_Product_Variable():new WC_Product_Simple();$p->set_props(['name'=>$row['name'],'slug'=>$row['slug'],'status'=>'publish','sku'=>$row['sku'],'short_description'=>$row['short'],'description'=>'<p>'.esc_html($row['description']).'</p>','category_ids'=>[$categories[$row['category']]],'featured'=>$row['featured'],'image_id'=>h10_media($row['image'],$row['name'].' · fotografía original generada para DEMO'),'backorders'=>'no','menu_order'=>$index,'weight'=>'1','length'=>'35','width'=>'30','height'=>'25']);
            $variable_slugs=$row['type']==='variable'?array_keys($row['variations'][0]['attributes']):[];$attributes=[];
            foreach($row['attributes'] as $slug=>$terms){$a=new WC_Product_Attribute();$a->set_id(wc_attribute_taxonomy_id_by_name($slug));$a->set_name('pa_'.$slug);$a->set_options(array_map(static fn($term)=>$taxterms[$slug][$term],$terms));$a->set_visible(true);$a->set_variation(in_array($slug,$variable_slugs,true));$a->set_position(count($attributes));$attributes[]=$a;}$p->set_attributes($attributes);
            $p->update_meta_data('_commerce_h10_owner','origen-casa');$p->update_meta_data('_cb_care','DEMO · Limpiar suavemente. La ficha de uso y cuidados debe validarse para el producto real.');
            if($row['type']==='simple'){$p->set_regular_price($row['price']);$p->set_sale_price($row['sale']??'');$p->set_manage_stock(true);$p->set_stock_quantity($row['stock']);$p->set_stock_status($row['stock']>0?'instock':'outofstock');}$id=$p->save();
            if($row['type']==='variable'){foreach($row['variations'] as $vindex=>$vrow){$v=new WC_Product_Variation();$attrs=[];foreach($vrow['attributes'] as $slug=>$term){$attrs['pa_'.$slug]=sanitize_title($term);}$v->set_props(['parent_id'=>$id,'status'=>'publish','sku'=>$row['sku'].'-'.($vindex+1),'attributes'=>$attrs,'regular_price'=>$vrow['price'],'manage_stock'=>true,'stock_quantity'=>$vrow['stock'],'stock_status'=>$vrow['stock']>0?'instock':'outofstock','image_id'=>$p->get_image_id()]);$v->update_meta_data('_commerce_h10_owner','origen-casa');$v->save();}WC_Product_Variable::sync($id);}
            $state['products'][$row['sku']]=['id'=>$id];
        }
        foreach($content['products'] as $row){$p=wc_get_product($state['products'][$row['sku']]['id']);$p->set_cross_sell_ids(array_map(static fn($sku)=>$state['products'][$sku]['id'],$row['cross_sells']));$p->save();}
        $zone=new WC_Shipping_Zone();$zone->set_zone_name('ORIGEN CASA · Argentina DEMO');$zone->set_zone_order(0);$zone->add_location('AR','country');$zone->save();$flat=$zone->add_shipping_method('flat_rate');$free=$zone->add_shipping_method('free_shipping');update_option('woocommerce_flat_rate_'.$flat.'_settings',['title'=>'Envío estándar · DEMO','tax_status'=>'none','cost'=>$commerce['flat_rate']],false);update_option('woocommerce_free_shipping_'.$free.'_settings',['title'=>'Envío gratis · DEMO','requires'=>'min_amount','min_amount'=>$commerce['free_shipping_minimum'],'ignore_discounts'=>'no'],false);
        foreach($content['pages'] as $key=>$row){$legal=in_array($key,['privacy','terms','shipping','returns','withdrawal'],true);h10_page($key,$row['title'],$row['slug']??$key,h10_copy($row['title'],$row['lead'],$row['sections'],$legal,$key==='withdrawal'),$legal);}
        $legal=$client['legal']+['pages'=>[],'email'=>$brand['legal'],'notification_email'=>$brand['legal'],'website'=>home_url('/')];foreach(['privacy','terms','shipping','returns','withdrawal'] as $key){$legal['pages'][$key]=$state['pages'][$key]['id'];}$options['commerce_core_legal']=$legal;update_option('commerce_core_legal',$legal,false);$options['wp_page_for_privacy_policy']=$state['pages']['privacy']['id'];$options['woocommerce_terms_page_id']=$state['pages']['terms']['id'];update_option('wp_page_for_privacy_policy',$options['wp_page_for_privacy_policy']);update_option('woocommerce_terms_page_id',$options['woocommerce_terms_page_id']);
        foreach(['shop'=>'Tienda','cart'=>'Carrito','checkout'=>'Finalizar compra','myaccount'=>'Mi cuenta'] as $key=>$title){$id=wc_get_page_id($key);wp_update_post(['ID'=>$id,'post_title'=>$title]);update_post_meta($id,'_commerce_h10_owner','origen-casa');$state['pages'][$key]=['id'=>$id];}
        $checkout=wc_get_page_id('checkout');$blocks=parse_blocks(get_post_field('post_content',$checkout));$terms_count=0;$walk=function(&$list)use(&$walk,&$terms_count,$legal){foreach($list as &$block){if($block['blockName']==='woocommerce/checkout-terms-block'){$block['attrs']['checkbox']=true;$block['attrs']['text']='Leí y acepto <a href="'.esc_url(get_permalink($legal['pages']['terms'])).'">los términos DEMO</a> y conozco <a href="'.esc_url(get_permalink($legal['pages']['privacy'])).'">la política de privacidad</a>. Consultá el <a href="'.esc_url(get_permalink($legal['pages']['withdrawal'])).'">Derecho de arrepentimiento</a>.';$terms_count++;}if(!empty($block['innerBlocks'])){$walk($block['innerBlocks']);}}};$walk($blocks);if($terms_count!==1){throw new RuntimeException('Expected one native checkout terms block.');}wp_update_post(wp_slash(['ID'=>$checkout,'post_content'=>serialize_blocks($blocks)]));
        $hero=$content['hero'];$hero_id=h10_media($hero['image'],$hero['alt']);$hero_url=wp_get_attachment_url($hero_id);
        $html='<section class="oc-hero cb-container" aria-labelledby="oc-hero-title"><div class="oc-hero-copy"><p class="cb-eyebrow">'.esc_html($hero['eyebrow']).'</p><h1 id="oc-hero-title">'.esc_html($hero['title']).'</h1><p class="oc-hero-description">'.esc_html($hero['text']).'</p><a class="cb-button" href="'.esc_url(wc_get_page_permalink('shop')).'">Descubrí la colección <span aria-hidden="true">↗</span></a><p class="cb-note">Tienda ficticia · precios y compras de prueba</p></div><figure class="oc-hero-media"><img src="'.esc_url($hero_url).'" width="1536" height="1024" fetchpriority="high" alt="'.esc_attr($hero['alt']).'"><figcaption>Materia, luz y tiempo / Una casa posible</figcaption></figure></section>';
        $html.='<section class="oc-section cb-container" aria-labelledby="oc-cat-title"><div class="oc-section-heading"><p class="cb-eyebrow">01 / Una selección para tu espacio</p><h2 id="oc-cat-title">Cada objeto, su lugar.</h2></div><div class="oc-category-grid">';foreach($content['categories'] as $row){$html.='<a class="oc-category-card" href="'.esc_url(get_term_link($categories[$row['slug']],'product_cat')).'"><img src="'.esc_url(wp_get_attachment_url($state['media'][$row['image']]['id'])).'" width="396" height="397" loading="lazy" alt=""><span>'.esc_html($row['name']).' <span aria-hidden="true">↗</span></span></a>';}$html.='</div></section>';
        $home='<!-- wp:html -->'.$html.'<!-- /wp:html -->';
        $home.='<!-- wp:html --><section class="oc-section cb-container" aria-labelledby="oc-featured-title"><div class="oc-section-heading"><p class="cb-eyebrow">02 / Objetos elegidos</p><h2 id="oc-featured-title">Pequeños gestos cotidianos.</h2><p>Selección DEMO · Cupón CASADEMO10 para probar un 10% de descuento.</p></div><!-- /wp:html -->';
        // Native Product Collection owns queries, stock, price and cart; IDs are resolved in this installation.
        $featured=[];foreach($content['products'] as $row){if($row['featured']){$featured[]=$state['products'][$row['sku']]['id'];}}
        $query=['queryId'=>101,'query'=>['perPage'=>4,'pages'=>1,'postType'=>'product','order'=>'asc','orderBy'=>'menu_order','inherit'=>false,'isProductCollectionBlock'=>true,'featured'=>true,'woocommerceStockStatus'=>['instock','outofstock'],'woocommerceAttributes'=>[],'taxQuery'=>(object)[]],'displayLayout'=>['type'=>'flex','columns'=>4,'shrinkColumns'=>true],'className'=>'cb-home-collection'];
        $home.='<!-- wp:woocommerce/product-collection '.wp_json_encode($query).' --><div class="wp-block-woocommerce-product-collection cb-home-collection"><!-- wp:woocommerce/product-template {"className":"cb-product-grid"} --><!-- wp:commerce-base/product-card /--><!-- /wp:woocommerce/product-template --></div><!-- /wp:woocommerce/product-collection --><!-- wp:html --></section><!-- /wp:html -->';
        $ed=$content['editorial'];$home.='<!-- wp:html --><section class="oc-section cb-container oc-editorial" aria-labelledby="oc-editorial-title"><div class="oc-editorial-copy"><p class="cb-eyebrow">'.esc_html($ed['eyebrow']).'</p><h2 id="oc-editorial-title">'.esc_html($ed['title']).'</h2><p>'.esc_html($ed['text']).'</p><a class="cb-link" href="'.esc_url(get_permalink($state['pages']['conocenos']['id'])).'">Conocé nuestra mirada ↗</a></div><div class="oc-editorial-media"><!-- /wp:html -->';
        $spots=[];foreach($ed['hotspots'] as $spot){$spots[]=['productId'=>$state['products'][$spot['sku']]['id'],'x'=>$spot['x'],'y'=>$spot['y']];}
        $home.='<!-- wp:commerce-lookbook/shoppable-lookbook '.wp_json_encode(['imageId'=>$hero_id,'imageUrl'=>$hero_url,'imageAlt'=>$hero['alt'],'imageWidth'=>1536,'imageHeight'=>1024,'hotspots'=>$spots]).' /-->';
        $home.='<!-- wp:html --></div></section><section class="oc-benefits cb-container" aria-label="Información de la tienda DEMO">';foreach($content['benefits'] as $i=>$benefit){$home.='<div class="oc-benefit"><p class="cb-eyebrow">0'.($i+1).'</p><h2>'.esc_html($benefit['title']).'</h2><p>'.esc_html($benefit['text']).'</p></div>';}$home.='</section><!-- /wp:html -->';
        $front=h10_page('home','ORIGEN CASA · Objetos para habitar','inicio',$home);wp_update_post(['ID'=>$front,'post_excerpt'=>$brand['description']]);set_post_thumbnail($front,$hero_id);$options['page_on_front']=$front;update_option('page_on_front',$front);
        $menu=wp_create_nav_menu('ORIGEN CASA DEMO');if(is_wp_error($menu)){throw new RuntimeException($menu->get_error_message());}foreach($client['navigation'] as $item){$is_cat=isset($categories[$item]);$id=$is_cat?$categories[$item]:$state['pages'][$item]['id'];$title=$is_cat?get_term($id,'product_cat')->name:get_the_title($id);$menu_item=wp_update_nav_menu_item($menu,0,['menu-item-title'=>$title,'menu-item-type'=>$is_cat?'taxonomy':'post_type','menu-item-object'=>$is_cat?'product_cat':'page','menu-item-object-id'=>$id,'menu-item-status'=>'publish']);if(is_wp_error($menu_item)){throw new RuntimeException($menu_item->get_error_message());}update_post_meta($menu_item,'_commerce_h10_owner','origen-casa');}set_theme_mod('nav_menu_locations',['primary'=>(int)$menu]);$state['menu_id']=(int)$menu;
        $coupon=new WC_Coupon();$coupon->set_code($commerce['coupon']);$coupon->set_discount_type('percent');$coupon->set_amount($commerce['coupon_percent']);$coupon->set_description('ORIGEN CASA DEMO · sólo pruebas locales, sin valor comercial.');$coupon->set_individual_use(true);$coupon->update_meta_data('_commerce_h10_owner','origen-casa');$state['coupon_id']=$coupon->save();
        $state['coupon_hash']=h10_hash([$coupon->get_code(),$coupon->get_discount_type(),$coupon->get_amount(),$coupon->get_individual_use(),$coupon->get_minimum_amount()]);
        $state['option_names']=array_keys($options);$state['phase']='imported';update_option('commerce_h10_fixture',$state,false);$wpdb->query('COMMIT');wp_cache_flush();WC_Cache_Helper::get_transient_version('shipping',true);
    } catch(Throwable $e) {
        $wpdb->query('ROLLBACK');wp_cache_flush();WP_CLI::error('Import interrupted; TRAMA was never targeted. SQL rollback is best effort because Core or plugin hooks may issue DDL. Inspect the independent installation before recovery. '.$e->getMessage());
    }
    // Read persisted values in a new request: parent variation metadata has request-local caches.
    $certify=WP_CLI::runcommand('eval-file /opt/local-tools/h10-store.php certify --quiet',['return'=>'all','exit_error'=>false]);echo $certify->stdout;WP_CLI::halt($certify->return_code);
}
// Snapshot is only permitted immediately after this import; a completed seed never accepts new hashes.
if($mode==='certify'&&($state['phase']??'')==='imported') {
    foreach($state['products'] as &$row){$row['hash']=h10_hash(h10_product($row['id']));}unset($row);
    foreach($state['pages'] as &$row){$row['hash']=h10_hash(h10_post($row['id']));}unset($row);
    foreach($state['terms'] as &$row){$row['hash']=h10_hash(h10_term_snapshot($row['id'],$row['taxonomy']));}unset($row);
    $state['configuration_hash']=h10_hash(h10_configuration($state['option_names']));$state['configuration_hashes']=h10_configuration_hashes($state['option_names']);$state['menu_hash']=h10_hash(h10_menu($state['menu_id']));$state['style_hash']=h10_hash(get_post_field('post_content',$state['style_id']));$state['phase']='complete';update_option('commerce_h10_fixture',$state,false);$conflicts=h10_conflicts($state,$source_hash);
}
$products=[];foreach($state['products']??[] as $sku=>$row){$p=wc_get_product($row['id']);if(!$p){continue;}$variations=[];foreach($p->get_children() as $id){$v=wc_get_product($id);$variations[]=['id'=>$id,'sku'=>$v->get_sku(),'attributes'=>$v->get_attributes(),'stock'=>$v->get_stock_quantity(),'price'=>$v->get_price()];}$products[$sku]=['id'=>$p->get_id(),'name'=>$p->get_name(),'url'=>$p->get_permalink(),'type'=>$p->get_type(),'stock_status'=>$p->get_stock_status(),'variations'=>$variations];}
$pages=[];foreach($state['pages']??[] as $key=>$row){$pages[$key]=['id'=>$row['id'],'url'=>get_permalink($row['id'])];}
$checks=['fixture_complete'=>($state['phase']??'')==='complete','ten_independent_products'=>count($products)===10,'two_variable_products'=>count(array_filter($products,static fn($p)=>$p['type']==='variable'))===2,'source_and_owned_data_intact'=>!$conflicts,'mercado_pago_deactivated'=>!is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php'),'private_demo'=>get_option('blog_public')==='0','native_bacs'=>(get_option('woocommerce_bacs_settings',[])['enabled']??'no')==='yes','no_bank_details'=>!get_option('woocommerce_bacs_accounts'),'native_legal'=>count(\CommerceCore\Legal::config()['pages']??[])===5,'independent_theme'=>get_stylesheet()==='commerce-base'];
$result=$mode==='inspect'?'inspected':(in_array(false,$checks,true)?'failed':'passed');
echo wp_json_encode(['result'=>$result,'home'=>$url.'/','brand'=>get_bloginfo('name'),'checks'=>$checks,'conflicts'=>$conflicts,'products'=>$products,'pages'=>$pages,'coupon'=>$client['commerce']['coupon'],'shipping'=>['flat_rate'=>$client['commerce']['flat_rate'],'free_from'=>$client['commerce']['free_shipping_minimum']],'repeat_behavior'=>'Completed import validates owned content and settings, then performs no writes. Edited content, stock or settings are preserved and cause refusal; prepare an explicit migration.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
if($mode!=='inspect'&&$result!=='passed'){WP_CLI::halt(1);}
