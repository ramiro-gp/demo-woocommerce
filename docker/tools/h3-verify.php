<?php
/** Read-only H3 verification. No gateway requests, fixture installation or order writes. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') {
    exit(1);
}
$mode = $args[0] ?? '';
if (!in_array($mode, ['snapshot', 'theme'], true)) {
    WP_CLI::error('Usage: h3-verify.php snapshot|theme');
}
if (!function_exists('wc_get_products') || !class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
    WP_CLI::error('Existing WooCommerce installation required. H3 never installs fixtures.');
}

$normalize = static function ($value) use (&$normalize) {
    if (is_array($value)) {
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $normalize($item);
        }
    }
    return $value;
};
$hash = static function ($value) use ($normalize) {
    return hash('sha256', wp_json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
};
$assert = static function ($condition, $message) {
    if (!$condition) {
        WP_CLI::error($message);
    }
};

if (!function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$plugins = [];
foreach (get_plugins() as $file => $metadata) {
    if (is_plugin_active($file)) {
        $plugins[$file] = $metadata['Version'];
    }
}
ksort($plugins);
$settings = [];
foreach ([
    'woocommerce_currency', 'woocommerce_currency_pos', 'woocommerce_price_num_decimals',
    'woocommerce_default_country', 'woocommerce_default_customer_address', 'woocommerce_allowed_countries',
    'woocommerce_specific_allowed_countries', 'woocommerce_ship_to_countries', 'woocommerce_specific_ship_to_countries',
    'woocommerce_enable_guest_checkout', 'woocommerce_enable_signup_and_login_from_checkout',
    'woocommerce_enable_myaccount_registration', 'woocommerce_manage_stock', 'woocommerce_hold_stock_minutes',
    'woocommerce_notify_low_stock', 'woocommerce_notify_no_stock', 'woocommerce_stock_email_recipient',
    'woocommerce_low_stock_amount', 'woocommerce_notify_no_stock_amount', 'woocommerce_hide_out_of_stock_items',
    'woocommerce_calc_taxes', 'woocommerce_prices_include_tax', 'woocommerce_custom_orders_table_enabled',
    'woocommerce_custom_orders_table_data_sync_enabled', 'blog_public', 'timezone_string', 'WPLANG',
] as $name) {
    // Hash option values rather than exposing addresses, emails or future merchant data.
    $settings[$name] = $hash(get_option($name, null));
}
$gateway_options = [];
foreach ([
    'woocommerce_woo-mercado-pago-basic_settings', 'woocommerce_woo-mercado-pago-custom_settings',
    'woocommerce_woo-mercado-pago-pix_settings', 'woocommerce_woo-mercado-pago-ticket_settings',
    'woocommerce_mercadopago_settings', 'woocommerce_mercado_pago_settings', 'mercadopago_settings',
    '_mp_public_key_test', '_mp_access_token_test', '_mp_public_key_prod', '_mp_access_token_prod',
    '_mp_category_id', '_mp_store_identificator', '_mp_integrator_id', '_mp_custom_domain',
    'checkout_country', 'mp_statement_descriptor', 'checkbox_checkout_production_mode', 'checkbox_checkout_test_mode',
    '_site_id_v1', '_mp_client_id', '_collector_id_v1', '_test_user_v1',
    '_mp_installation_id', '_mp_installation_key', '_mp_debug_mode', '_mp_cron_sync_mode',
] as $name) {
    $gateway_options[$name] = $hash(get_option($name, null));
}
$shipping = [];
foreach (array_merge(WC_Shipping_Zones::get_zones(), [['id' => 0]]) as $zone_data) {
    $zone = new WC_Shipping_Zone((int) $zone_data['id']);
    $methods = [];
    foreach ($zone->get_shipping_methods() as $method) {
        $methods[] = [
            'id' => $method->id, 'instance_id' => $method->instance_id,
            'enabled' => $method->enabled, 'settings_hash' => $hash($method->instance_settings),
        ];
    }
    $shipping[] = ['id' => $zone->get_id(), 'locations_hash' => $hash($zone->get_zone_locations()), 'methods' => $methods];
}
$products = [];
$product_objects = wc_get_products(['limit' => -1, 'type' => ['simple', 'variable'], 'status' => ['publish', 'private', 'draft'], 'orderby' => 'ID', 'order' => 'ASC']);
foreach ($product_objects as $parent) {
    if ($parent->is_type('variable')) {
        foreach ($parent->get_children() as $child_id) {
            $child = wc_get_product($child_id);
            if ($child) {
                $product_objects[] = $child;
            }
        }
    }
}
usort($product_objects, static function ($left, $right) { return $left->get_id() <=> $right->get_id(); });
foreach ($product_objects as $product) {
    $attributes = [];
    foreach ($product->get_attributes() as $name => $attribute) {
        $attributes[$name] = $attribute instanceof WC_Product_Attribute ? $attribute->get_data() : $attribute;
    }
    $products[] = [
        'id' => $product->get_id(), 'type' => $product->get_type(), 'sku_hash' => $hash($product->get_sku()),
        'status' => $product->get_status(), 'price' => $product->get_price(), 'regular_price' => $product->get_regular_price(),
        'sale_price' => $product->get_sale_price(), 'managed_stock' => $product->managing_stock(),
        'stock' => $product->get_stock_quantity(), 'stock_status' => $product->get_stock_status(),
        'backorders' => $product->get_backorders(), 'attributes_hash' => $hash($attributes),
    ];
}
$orders = [];
$statuses = array_merge(array_keys(wc_get_order_statuses()), ['wc-checkout-draft']);
foreach (wc_get_orders(['type' => 'shop_order', 'limit' => -1, 'status' => $statuses, 'orderby' => 'ID', 'order' => 'ASC']) as $order) {
    $refunds = [];
    foreach ($order->get_refunds() as $refund) {
        $refunds[] = ['id' => $refund->get_id(), 'amount' => $refund->get_amount(), 'refunded_payment' => $refund->get_refunded_payment()];
    }
    $items = [];
    foreach ($order->get_items() as $item) {
        $items[] = ['product' => $item->get_product_id(), 'variation' => $item->get_variation_id(), 'quantity' => $item->get_quantity(), 'total' => $item->get_total()];
    }
    $orders[] = [
        'id' => $order->get_id(), 'status' => $order->get_status(), 'total' => $order->get_total(),
        'currency' => $order->get_currency(), 'payment_method' => $order->get_payment_method(),
        'refunded_total' => $order->get_total_refunded(), 'refunds' => $refunds, 'items' => $items,
    ];
}
$pages = [];
foreach (['cart', 'checkout', 'shop', 'myaccount'] as $kind) {
    $id = wc_get_page_id($kind);
    $post = $id > 0 ? get_post($id) : null;
    $pages[$kind] = [
        'id' => $id, 'status' => $post ? $post->post_status : null,
        'content_hash' => $hash($post ? $post->post_content : null),
        'native_block' => $post && in_array($kind, ['cart', 'checkout'], true) ? has_block('woocommerce/' . $kind, $post) : null,
        'url' => $id > 0 ? get_permalink($id) : null,
    ];
}
global $wpdb;
$reservations = $wpdb->get_results("SELECT order_id, product_id, stock_quantity FROM {$wpdb->prefix}wc_reserved_stock ORDER BY order_id, product_id", ARRAY_A);
$assert($wpdb->last_error === '', 'Could not read native stock reservations.');
$commerce = [
    'wordpress' => get_bloginfo('version'), 'woocommerce' => WC_VERSION, 'php' => PHP_VERSION,
    'plugins' => $plugins, 'hpos' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
    'settings_hashes' => $settings, 'gateway_option_hashes' => $gateway_options,
    'shipping' => $shipping, 'products' => $products, 'orders' => $orders, 'reservations' => $reservations,
    'pages' => $pages, 'home' => home_url('/'),
];
$theme = wp_get_theme();
$theme_report = ['stylesheet' => $theme->get_stylesheet(), 'name' => $theme->get('Name'), 'block_theme' => $theme->is_block_theme()];
if ($mode === 'theme') {
    $assert($theme->get_stylesheet() === 'commerce-base', 'Commerce Base must be active.');
    $assert(!$theme->errors() && $theme->is_block_theme(), 'Commerce Base must be a valid Block Theme.');
    $root = $theme->get_stylesheet_directory();
    $json = json_decode(file_get_contents($root . '/theme.json'), true);
    $assert(is_array($json) && json_last_error() === JSON_ERROR_NONE, 'theme.json must contain valid JSON.');
    $assert(($json['version'] ?? 0) >= 2, 'theme.json must use a supported modern schema.');
    $templates = array_merge(glob($root . '/templates/*.html'), glob($root . '/parts/*.html'));
    $assert(count($templates) > 0 && is_file($root . '/templates/index.html'), 'Block templates are missing.');
    $parsed = [];
    foreach ($templates as $file) {
        $blocks = parse_blocks(file_get_contents($file));
        $assert(count(array_filter($blocks, static function ($block) { return !empty($block['blockName']); })) > 0, 'Template contains no parsed blocks: ' . basename($file));
        $parsed[] = str_replace($root . '/', '', $file);
    }
    $font_files = glob($root . '/assets/fonts/*.woff2');
    $assert(count($font_files) >= 2, 'Local Newsreader and Manrope WOFF2 assets are required.');
    foreach ($font_files as $file) {
        $assert(file_get_contents($file, false, null, 0, 4) === 'wOF2', 'Invalid WOFF2 font: ' . basename($file));
    }
    $assert($commerce['hpos'] && $pages['cart']['native_block'] && $pages['checkout']['native_block'], 'Preserve HPOS and native Cart/Checkout Blocks.');
    $theme_report['parsed_templates'] = $parsed;
    $theme_report['local_font_files'] = array_map('basename', $font_files);
    $theme_report['theme_json_hash'] = $hash($json);
}
echo wp_json_encode([
    'result' => 'passed', 'mode' => $mode, 'verified_at_utc' => gmdate('c'),
    'scope' => 'Read-only local commerce snapshot; no external gateway calls or writes.',
    'commerce_state_hash' => $hash($commerce), 'commerce_state' => $commerce, 'theme' => $theme_report,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
