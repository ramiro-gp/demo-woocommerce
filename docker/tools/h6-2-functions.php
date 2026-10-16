<?php
/** Local fixture helpers only; never loaded by the storefront or theme. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
function h6_2_physical($product) {
    $values = [];
    foreach (['weight', 'length', 'width', 'height'] as $field) { $values[$field] = $product->{'get_' . $field}('edit'); }
    return $values;
}
// Same approved H5/H6.1 fingerprint. Normalize only explicitly migrated fields,
// including inherited cross-sells cached in the variation datastore parent_data.
function h6_2_product_hash($product, $cross_sells = null, $physical = null) {
    if (!$product) { return ''; }
    $data = $product->get_data();
    if (null !== $cross_sells) { $data['cross_sell_ids'] = $cross_sells; }
    if (null !== $physical) { foreach ($physical as $key => $value) { $data[$key] = $value; } }
    unset($data['date_created'], $data['date_modified'], $data['sales'], $data['total_sales'], $data['rating_counts'], $data['average_rating'], $data['review_count'], $data['meta_data']);
    $data['attributes'] = array_map(static fn($a) => $a instanceof WC_Product_Attribute ? $a->get_data() : $a, $data['attributes']);
    $data['care'] = $product->get_meta('_cb_care'); $data['guide'] = $product->get_meta('_cb_size_guide'); $data['children'] = [];
    foreach ($product->get_children() as $id) { $data['children'][$id] = h6_2_product_hash(wc_get_product($id), $cross_sells); }
    return hash('sha256', wp_json_encode($data));
}
function h6_2_shipping_snapshot() {
    global $wpdb;
    $options = [];
    foreach (['woocommerce_weight_unit', 'woocommerce_dimension_unit', 'woocommerce_allowed_countries', 'woocommerce_specific_allowed_countries', 'woocommerce_ship_to_countries', 'woocommerce_specific_ship_to_countries', 'woocommerce_shipping_hide_rates_when_free', 'woocommerce_pickup_location_settings', 'pickup_location_pickup_locations'] as $name) { $options[$name] = get_option($name); }
    return [
        'options' => $options,
        'zones' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones ORDER BY zone_id", ARRAY_A),
        'locations' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations ORDER BY location_id", ARRAY_A),
        'methods' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods ORDER BY instance_id", ARRAY_A),
        'settings' => $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name REGEXP '^woocommerce_(flat_rate|free_shipping|local_pickup)_[0-9]+_settings$' ORDER BY option_name", ARRAY_A),
    ];
}
// Deliberately identical to H6.1. This is updated only inside the protected
// shipping migration, retaining its full before snapshot in trama_h6_2.
function h6_2_h61_environment_hash() {
    global $wpdb;
    $options = [];
    foreach (['woocommerce_enable_guest_checkout', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_registration_generate_password', 'woocommerce_checkout_phone_field', 'woocommerce_checkout_company_field', 'woocommerce_checkout_address_2_field', 'woocommerce_ship_to_destination', 'woocommerce_hide_out_of_stock_items', 'woocommerce_custom_orders_table_enabled', 'woocommerce_bacs_settings', 'woocommerce_cheque_settings', 'woocommerce_cod_settings'] as $name) { $options[$name] = get_option($name); }
    $options['gateway_options'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_woo-mercado-pago%' ORDER BY option_name", ARRAY_A);
    $options['zones'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones ORDER BY zone_id", ARRAY_A);
    $options['methods'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods ORDER BY instance_id", ARRAY_A);
    $options['shipping_settings'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name REGEXP '^woocommerce_(flat_rate|free_shipping|local_pickup)_[0-9]+_settings$' ORDER BY option_name", ARRAY_A);
    return hash('sha256', wp_json_encode($options));
}
function h6_2_hash($value) { return hash('sha256', wp_json_encode($value)); }
function h6_2_checkout_attrs($blocks, $attrs, &$count) {
    foreach ($blocks as &$block) {
        if ('woocommerce/checkout-shipping-method-block' === $block['blockName']) { $block['attrs'] = array_replace($block['attrs'], $attrs); ++$count; }
        if ($block['innerBlocks']) { $block['innerBlocks'] = h6_2_checkout_attrs($block['innerBlocks'], $attrs, $count); }
    }
    return $blocks;
}
