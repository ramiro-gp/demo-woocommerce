<?php
/**
 * Local, repeatable H2 setup. Run with wp eval-file /opt/local-tools/h2-fixtures.php.
 * Never loaded by WordPress HTTP requests. Existing fixture stock is never reset.
 */
if (!defined('WP_CLI') || !WP_CLI || !function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'local') {
    exit(1);
}

$assert = static function ($condition, $message) {
    if (!$condition) {
        WP_CLI::error($message);
    }
};
$assert(defined('WC_VERSION') && WC_VERSION === '11.2.1', 'H2 requires active WooCommerce 11.2.1.');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$mp_file = 'woocommerce-mercadopago/woocommerce-mercadopago.php';
$mp_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $mp_file, false, false);
$assert(is_plugin_active($mp_file) && $mp_data['Version'] === '8.9.6', 'H2 requires active official Mercado Pago 8.9.6.');

$marker = 'demo-woocommerce-h2-v1';
$meta_key = '_demo_h2_fixture';
$state_key = 'demo_h2_fixtures';
$state = get_option($state_key, []);
$assert(is_array($state) && (!$state || ($state['schema'] ?? '') === $marker), 'Unknown H2 fixture schema; refusing to overwrite it.');
$state['schema'] = $marker;
$state['attributes'] = $state['attributes'] ?? [];
$state['shipping_methods'] = $state['shipping_methods'] ?? [];
$assert(is_array($state['attributes']) && is_array($state['shipping_methods']), 'Invalid H2 fixture manifest; refusing to overwrite it.');
$save_state = static function () use (&$state, $state_key) {
    update_option($state_key, $state, false);
};

// Check SKU ownership before changing any store settings or catalog data.
$skus = [
    'H2-SIMPLE' => 'simple',
    'H2-VARIABLE' => 'variable',
    'H2-VARIABLE-S-NEGRO' => 'variation',
    'H2-VARIABLE-M-NEGRO' => 'variation',
];
$products = [];
foreach ($skus as $sku => $type) {
    $id = wc_get_product_id_by_sku($sku);
    $product = $id ? wc_get_product($id) : false;
    if ($id) {
        $assert($product && $product->is_type($type) && $product->get_meta($meta_key) === $marker, 'SKU collision or modified fixture: ' . $sku . '. Existing product preserved.');
    }
    $products[$sku] = $product;
}

// Switch through WooCommerce's option hooks, which validate/create HPOS tables.
// Refuse a migration of an existing store: this script only sets up a local fixture.
if (!\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    $assert(!wc_get_orders(['limit' => 1, 'return' => 'ids']), 'Existing orders found with legacy storage. Enable/migrate HPOS with WooCommerce before using H2 fixtures.');
    update_option('woocommerce_custom_orders_table_enabled', 'yes');
}
$assert(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'WooCommerce could not enable HPOS.');

// Technical store settings only. No address, tax identity, bank data or secrets.
$settings = [
    'WPLANG' => 'es_AR',
    'woocommerce_default_country' => 'AR',
    'woocommerce_currency' => 'ARS',
    'woocommerce_currency_pos' => 'left_space',
    'woocommerce_price_thousand_sep' => '.',
    'woocommerce_price_decimal_sep' => ',',
    'woocommerce_price_num_decimals' => '2',
    'woocommerce_allowed_countries' => 'specific',
    'woocommerce_specific_allowed_countries' => ['AR'],
    'woocommerce_ship_to_countries' => 'specific',
    'woocommerce_specific_ship_to_countries' => ['AR'],
    'woocommerce_enable_guest_checkout' => 'yes',
    'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
    'woocommerce_enable_myaccount_registration' => 'yes',
    'woocommerce_registration_generate_username' => 'yes',
    'woocommerce_registration_generate_password' => 'yes',
    'woocommerce_manage_stock' => 'yes',
    'woocommerce_hide_out_of_stock_items' => 'no',
    'woocommerce_hold_stock_minutes' => '60',
    'woocommerce_coming_soon' => 'no',
    'woocommerce_allow_tracking' => 'no',
];
foreach ($settings as $name => $value) {
    update_option($name, $value);
}
$profile = get_option('woocommerce_onboarding_profile', []);
$assert(is_array($profile), 'Unexpected WooCommerce onboarding profile; review the existing setting.');
$profile['completed'] = true;
update_option('woocommerce_onboarding_profile', $profile);

// Native installer supplies the complete Cart/Checkout block markup. Only newly
// created pages are marked; existing pages and their content remain untouched.
$page_filter = static function ($pages) {
    unset($pages['refund_returns']); // Do not generate legal policy boilerplate.
    return $pages;
};
$page_created = static function ($id) use ($marker, $meta_key) {
    add_post_meta($id, $meta_key, $marker, true);
};
add_filter('woocommerce_create_pages', $page_filter);
add_action('woocommerce_page_created', $page_created);
WC_Install::create_pages();
remove_filter('woocommerce_create_pages', $page_filter);
remove_action('woocommerce_page_created', $page_created);
foreach (['shop', 'cart', 'checkout', 'myaccount'] as $page_name) {
    $page = get_post(wc_get_page_id($page_name));
    $assert($page && $page->post_type === 'page' && $page->post_status === 'publish', 'WooCommerce page is not published: ' . $page_name);
    if (in_array($page_name, ['cart', 'checkout'], true)) {
        $assert(has_block('woocommerce/' . $page_name, $page), 'Existing ' . $page_name . ' content has no WooCommerce Block. Preserved without conversion; review it manually.');
    }
}

// Attribute IDs live in the fixture manifest because WC attributes have no meta.
foreach (['talle' => 'Talle', 'color' => 'Color'] as $slug => $label) {
    $taxonomy = wc_attribute_taxonomy_name($slug);
    $id = wc_attribute_taxonomy_id_by_name($taxonomy);
    if ($id) {
        $assert((int) ($state['attributes'][$slug] ?? 0) === $id, 'Unowned global attribute collision: ' . $slug . '. Existing attribute preserved.');
        $attribute = wc_get_attribute($id);
        $assert($attribute && $attribute->name === $label && $attribute->type === 'select', 'Fixture global attribute was modified: ' . $slug);
    } else {
        $assert(empty($state['attributes'][$slug]), 'Fixture attribute was deleted: ' . $slug . '. Review before recreating it.');
        $id = wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
        $assert(!is_wp_error($id) && $id > 0, 'Cannot create H2 global attribute: ' . $slug);
        $state['attributes'][$slug] = (int) $id;
        $save_state();
    }
    // WC registered taxonomies earlier in this CLI request; expose new ones now.
    // The next normal request uses WooCommerce's complete taxonomy registration.
    if (!taxonomy_exists($taxonomy)) {
        register_taxonomy($taxonomy, ['product'], ['label' => $label, 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'rewrite' => false]);
    }
}

$terms = [];
foreach (['pa_talle' => ['s' => 'S', 'm' => 'M'], 'pa_color' => ['negro' => 'Negro']] as $taxonomy => $values) {
    foreach ($values as $slug => $label) {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term) {
            $assert(get_term_meta($term->term_id, $meta_key, true) === $marker && $term->name === $label, 'Unowned/modified attribute term: ' . $taxonomy . '/' . $slug);
            $id = $term->term_id;
        } else {
            $result = wp_insert_term($label, $taxonomy, ['slug' => $slug]);
            $assert(!is_wp_error($result), 'Cannot create attribute term: ' . $taxonomy . '/' . $slug);
            $id = $result['term_id'];
            add_term_meta($id, $meta_key, $marker, true);
        }
        $terms[$taxonomy][$slug] = (int) $id;
    }
}

$new_product = static function ($class, $sku, $name) use ($marker, $meta_key) {
    $product = new $class();
    $product->set_name($name);
    $product->set_slug(strtolower($sku));
    $product->set_sku($sku);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_virtual(false);
    $product->set_downloadable(false);
    $product->set_backorders('no');
    $product->set_description('Fixture técnico local de H2 para verificar WooCommerce y Mercado Pago.');
    $product->update_meta_data($meta_key, $marker);
    return $product;
};

if (!$products['H2-SIMPLE']) {
    $simple = $new_product('WC_Product_Simple', 'H2-SIMPLE', 'H2 — Producto simple técnico');
    $simple->set_regular_price('1000');
    $simple->set_manage_stock(true);
    $simple->set_stock_quantity(5);
    $simple->set_stock_status('instock');
    $simple->save();
    $products['H2-SIMPLE'] = $simple;
}

if (!$products['H2-VARIABLE']) {
    $variable = $new_product('WC_Product_Variable', 'H2-VARIABLE', 'H2 — Producto variable técnico');
    $attributes = [];
    foreach (['pa_talle' => 'talle', 'pa_color' => 'color'] as $taxonomy => $slug) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id($state['attributes'][$slug]);
        $attribute->set_name($taxonomy);
        $attribute->set_options(array_values($terms[$taxonomy]));
        $attribute->set_position(count($attributes));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;
    }
    $variable->set_attributes($attributes);
    $variable->set_default_attributes(['pa_talle' => 's', 'pa_color' => 'negro']);
    $variable->set_manage_stock(false);
    $variable->save();
    $products['H2-VARIABLE'] = $variable;
}
$variable_id = $products['H2-VARIABLE']->get_id();
$created_variation = false;
foreach (['s' => 3, 'm' => 0] as $size => $stock) {
    $sku = 'H2-VARIABLE-' . strtoupper($size) . '-NEGRO';
    if ($products[$sku]) {
        $assert($products[$sku]->get_parent_id() === $variable_id && $products[$sku]->get_attributes() == ['pa_talle' => $size, 'pa_color' => 'negro'], 'Fixture variation parent or attributes were modified: ' . $sku);
        continue;
    }
    $variation = $new_product('WC_Product_Variation', $sku, 'H2 — ' . strtoupper($size) . ' / Negro');
    $variation->set_parent_id($variable_id);
    $variation->set_attributes(['pa_talle' => $size, 'pa_color' => 'negro']);
    $variation->set_regular_price('1500');
    $variation->set_manage_stock(true);
    $variation->set_stock_quantity($stock);
    $variation->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
    $variation->save();
    $products[$sku] = $variation;
    $created_variation = true;
}
if ($created_variation) {
    WC_Product_Variable::sync($variable_id);
}

// Native WC shipping zone/method APIs; manifest prevents duplicate methods.
$zone_name = 'H2 — Argentina';
if (!empty($state['shipping_zone_id'])) {
    $zone = new WC_Shipping_Zone((int) $state['shipping_zone_id']);
    $assert($zone->get_id() && $zone->get_zone_name() === $zone_name, 'H2 shipping zone is missing or modified; existing zones preserved.');
    $locations = $zone->get_zone_locations();
    $assert(count($locations) === 1 && $locations[0]->code === 'AR' && $locations[0]->type === 'country', 'H2 shipping zone locations were modified.');
} else {
    foreach (WC_Shipping_Zones::get_zones() as $existing_zone) {
        $assert($existing_zone['zone_name'] !== $zone_name, 'Unowned H2 shipping zone collision. Existing zone preserved.');
    }
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name($zone_name);
    $zone->set_zone_order(0);
    $zone->add_location('AR', 'country');
    $state['shipping_zone_id'] = $zone->save();
    $assert($state['shipping_zone_id'] > 0, 'Could not create H2 shipping zone.');
    $save_state();
}
foreach (['flat_rate' => ['title' => 'H2 — Tarifa fija', 'cost' => '500', 'tax_status' => 'none'], 'local_pickup' => ['title' => 'H2 — Retiro local', 'cost' => '0', 'tax_status' => 'none']] as $method_id => $method_settings) {
    $methods = $zone->get_shipping_methods();
    $instance_id = (int) ($state['shipping_methods'][$method_id] ?? 0);
    if ($instance_id) {
        $assert(isset($methods[$instance_id]) && $methods[$instance_id]->id === $method_id, 'H2 shipping method is missing or modified: ' . $method_id);
        continue;
    }
    $instance_id = $zone->add_shipping_method($method_id);
    $assert($instance_id > 0, 'Could not add native shipping method: ' . $method_id);
    update_option('woocommerce_' . $method_id . '_' . $instance_id . '_settings', $method_settings);
    $state['shipping_methods'][$method_id] = $instance_id;
    $save_state();
}

// No offline payment method is part of H2. Never touch Mercado Pago credentials.
foreach (['bacs', 'cheque', 'cod'] as $gateway_id) {
    $option_name = 'woocommerce_' . $gateway_id . '_settings';
    $gateway_settings = get_option($option_name, []);
    $gateway_settings['enabled'] = 'no';
    update_option($option_name, $gateway_settings);
}
if (empty($state['gateway_initialized'])) {
    $pro_option = 'woocommerce_woo-mercado-pago-basic_settings';
    $pro_settings = get_option($pro_option, []);
    $pro_settings['enabled'] = 'no';
    update_option($pro_option, $pro_settings);
    $state['gateway_initialized'] = true;
}
$save_state();
flush_rewrite_rules(false);

foreach ($products as $sku => $product) {
    WP_CLI::log($sku . ': id=' . $product->get_id() . ', stock=' . ($product->get_manage_stock() ? $product->get_stock_quantity() : 'by-variation'));
}
WP_CLI::success('H2 local fixtures ready. Existing stock preserved; HPOS and Cart/Checkout Blocks active. Mercado Pago still needs test seller configuration and public HTTPS.');
