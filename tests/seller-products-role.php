<?php
/** D1.1 read-only permission evidence from an isolated seller laboratory. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local'
    || get_option('commerce_seller_lab') !== 'd1-1-seller-audit'
    || home_url() !== 'http://localhost:8192') { exit(1); }
if (!class_exists('WooCommerce') || (function_exists('CommerceDemoPublic\\enabled') && \CommerceDemoPublic\enabled())) { WP_CLI::error('Native seller lab with public guard OFF required.'); }
require_once ABSPATH.'wp-admin/includes/user.php';
$caps=['read','manage_options','manage_woocommerce','view_woocommerce_reports','edit_products','publish_products','delete_products','manage_product_terms','upload_files','edit_shop_orders','edit_shop_coupons','publish_shop_coupons','list_users','edit_users','create_users','create_customers','delete_users','promote_users','activate_plugins','install_plugins','update_plugins','switch_themes','edit_theme_options','edit_pages'];
$users=[];
foreach (['administrator','shop_manager','customer'] as $role) {
    $found=get_users(['role'=>$role,'number'=>1]);
    if (!$found) { WP_CLI::error('Required synthetic role missing.'); }
    $users[$role]=$found[0];
    if (!str_ends_with($found[0]->user_email,'.test')) { WP_CLI::error('Synthetic users only.'); }
}
$original=get_current_user_id();$rows=[];
try {
    foreach ($users as $role=>$user) {
        wp_set_current_user($user->ID);
        $row=['role'=>$role,'capabilities'=>[],'targeted_user_permissions'=>[]];
        foreach ($caps as $cap) { $row['capabilities'][$cap]=current_user_can($cap); }
        foreach ($users as $target=>$subject) {
            $row['targeted_user_permissions'][$target]=['edit_user'=>current_user_can('edit_user',$subject->ID),'delete_user'=>current_user_can('delete_user',$subject->ID),'promote_user'=>current_user_can('promote_user',$subject->ID)];
        }
        $row['editable_roles']=array_keys(get_editable_roles());
        $rows[]=$row;
    }
} finally { wp_set_current_user($original); }
$definitionMatches=[];
if (is_file('/seller-private/original-role-definitions.json')) {
    $originalDefinitions=json_decode(file_get_contents('/seller-private/original-role-definitions.json'),true);
    foreach (['trama','origen'] as $brand) {
        foreach (array_keys($users) as $role) {
            $expected=array_map('boolval',(array)($originalDefinitions[$brand][$role]['capabilities']??[]));ksort($expected);
            $actual=array_map('boolval',(array)get_role($role)->capabilities);ksort($actual);
            $definitionMatches[$brand][$role]=$expected===$actual;
        }
    }
}
$checks=['native_public_guard_off'=>!\CommerceDemoPublic\enabled(),'administrator_has_technical_control'=>$rows[0]['capabilities']['manage_options']&&$rows[0]['capabilities']['activate_plugins'],
    'manager_can_operate_shop'=>$rows[1]['capabilities']['edit_products']&&$rows[1]['capabilities']['edit_shop_orders']&&$rows[1]['capabilities']['edit_shop_coupons']&&$rows[1]['capabilities']['manage_woocommerce'],
    'manager_cannot_change_plugins_or_general_settings'=>!$rows[1]['capabilities']['manage_options']&&!$rows[1]['capabilities']['activate_plugins'],
    'manager_cannot_edit_administrator'=>!$rows[1]['targeted_user_permissions']['administrator']['edit_user'],
    'manager_can_edit_customer'=>$rows[1]['targeted_user_permissions']['customer']['edit_user'],
    'customer_cannot_operate_shop'=>!$rows[2]['capabilities']['manage_woocommerce']&&!$rows[2]['capabilities']['edit_products']];
echo wp_json_encode(['result'=>in_array(false,$checks,true)?'failed':'passed','scope'=>'D1.1 native capabilities; synthetic local clone only','origin'=>home_url(),'checks'=>$checks,'stored_role_definitions_match_readonly_originals'=>$definitionMatches,'roles'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if(in_array(false,$checks,true)){WP_CLI::halt(1);}
