<?php
/** D2 facts and setup. Never imports products: all product changes use native CSV UI. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || home_url() !== 'http://localhost:8196' || getenv('WORDPRESS_DB_NAME') !== 'd2_bulk') { exit(1); }
$mode=$args[0]??'snapshot';
if($mode==='initialize'){
    if(get_option('commerce_d2_lab')){WP_CLI::error('Fresh lab required.');}
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    foreach(['woocommerce','commerce-core','commerce-communications','commerce-order-integrity','commerce-account-navigation','commerce-lookbook'] as $slug){$r=activate_plugin($slug.'/'.$slug.'.php');if(is_wp_error($r)){WP_CLI::error($r->get_error_message());}}
    WC_Install::install();switch_theme('commerce-base');update_option('WPLANG','es_AR');
    foreach(['commerce_d2_lab'=>'native-csv-v1','blog_public'=>'0','users_can_register'=>'0','woocommerce_manage_stock'=>'yes','woocommerce_currency'=>'ARS','woocommerce_store_address'=>'QA ficticia','woocommerce_default_country'=>'AR:C','woocommerce_coming_soon'=>'no','woocommerce_onboarding_profile'=>['completed'=>true],'woocommerce_notify_low_stock'=>'no','woocommerce_notify_no_stock'=>'no','woocommerce_allow_tracking'=>'no','woocommerce_enable_marketplace_suggestions'=>'no','woocommerce_enable_myaccount_registration'=>'no'] as $k=>$v){update_option($k,$v);}
    $password=trim(file_get_contents('/run/secrets/manager'));$username='d2_seller_'.bin2hex(random_bytes(4));$id=wp_insert_user(['user_login'=>$username,'user_pass'=>$password,'user_email'=>'manager@bulk.example.test','role'=>'shop_manager']);
    if(is_wp_error($id)){WP_CLI::error('Cannot create manager.');}
    $access=['installation'=>'d2-native-csv','base'=>home_url(),'users'=>['manager'=>['id'=>$id,'username'=>$username,'password'=>$password]]];
    file_put_contents('/seller-private/access.json',wp_json_encode($access));chmod('/seller-private/access.json',0600);
    $image=wp_insert_attachment(['post_title'=>'D2 imagen pública QA','post_mime_type'=>'image/webp','post_status'=>'inherit'],WP_CONTENT_DIR.'/uploads/d2/sample.webp');update_attached_file($image,WP_CONTENT_DIR.'/uploads/d2/sample.webp');
    WP_CLI::success('Independent empty catalog and native Shop Manager ready.');return;
}
if(get_option('commerce_d2_lab')!=='native-csv-v1'){WP_CLI::error('D2 marker required.');}
if($mode==='sale-probe'){
    $p=wc_get_product(wc_get_product_id_by_sku('D2-S-0007'));$before=$p->get_stock_quantity();
    wc_update_product_stock($p,2,'decrease');
    echo wp_json_encode(['sku'=>$p->get_sku(),'before'=>$before,'after'=>wc_get_product($p->get_id())->get_stock_quantity(),'operation'=>'native stock decrease, synthetic sale equivalent, no real order']);return;
}
global $wpdb;
$rows=$wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status NOT IN ('trash','auto-draft') ORDER BY ID");$products=[];
foreach($rows as $row){$p=wc_get_product($row->ID);if(!$p){continue;}$attrs=[];foreach($p->get_attributes() as $key=>$a){$attrs[$key]=$a instanceof WC_Product_Attribute?['name'=>$a->get_name(),'options'=>$a->get_options(),'visible'=>$a->get_visible(),'variation'=>$a->get_variation(),'id'=>$a->get_id()]:$a;}
    $products[$p->get_sku()]=['id'=>$p->get_id(),'type'=>$p->get_type(),'parent'=>$p->get_parent_id(),'name'=>$p->get_name(),'description'=>$p->get_description(),'status'=>$p->get_status(),'regular'=>$p->get_regular_price(),'sale'=>$p->get_sale_price(),'image'=>$p->get_image_id(),'gallery'=>$p->get_gallery_image_ids(),'categories'=>$p->get_category_ids(),'attributes'=>$attrs,'stock'=>$p->get_stock_quantity(),'manage_stock'=>$p->get_manage_stock(),'stock_status'=>$p->get_stock_status()];
}
$manager=get_users(['role'=>'shop_manager'])[0];wp_set_current_user($manager->ID);
echo wp_json_encode(['wordpress'=>get_bloginfo('version'),'woocommerce'=>WC_VERSION,'php'=>PHP_VERSION,'database'=>$wpdb->db_version(),'manager'=>['roles'=>$manager->roles,'manage_woocommerce'=>current_user_can('manage_woocommerce'),'edit_products'=>current_user_can('edit_products'),'import'=>current_user_can('import'),'export'=>current_user_can('export')],'counts'=>array_count_values(array_column($products,'type')),'products'=>$products,'duplicate_skus'=>$wpdb->get_results("SELECT meta_value,COUNT(*) n FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value<>'' GROUP BY meta_value HAVING COUNT(*)>1"),'peak_php_bytes'=>memory_get_peak_usage(true)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
