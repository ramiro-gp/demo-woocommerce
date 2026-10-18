<?php
/** Shared local tooling. Never loaded by HTTP, theme or payment receivers. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
function h7_1_hash($value) { return hash('sha256', wp_json_encode($value)); }
function h7_1_demo_bacs() {
    return ['enabled' => 'yes', 'title' => 'Transferencia bancaria', 'description' => 'Realizá una transferencia y verificaremos el pago antes de preparar tu pedido. TIENDA DE DEMOSTRACIÓN — No realizar transferencias reales.', 'instructions' => 'TIENDA DE DEMOSTRACIÓN — No realizar transferencias reales. Esta tienda no tiene una cuenta bancaria operativa.'];
}
function h7_1_filled($value) {
    if (is_array($value)) { return (bool) array_filter($value, 'h7_1_filled'); }
    return null !== $value && false !== $value && '' !== trim((string) $value);
}
function h7_1_sensitive_snapshot() {
    global $wpdb;
    $options = [];
    // Hash values in memory only. Neither credentials nor account data are
    // serialized into reports, fixtures, CLI output or migration snapshots.
    foreach (['_mp_public_key_prod', '_mp_public_key_test', '_mp_access_token_prod', '_mp_access_token_test', '_mp_installation_key', '_mp_integration_code_verifier', '_mp_integration_code_challenge', '_collector_id_v1', '_test_user_v1', 'woocommerce_bacs_settings', 'woocommerce_bacs_accounts'] as $name) { $options[$name] = get_option($name, null); }
    $options['gateways'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_woo-mercado-pago%' ORDER BY option_name", ARRAY_A);
    return h7_1_hash($options);
}
function h7_1_mp_snapshot() {
    global $wpdb;
    $options = [];
    foreach (['_mp_public_key_prod', '_mp_public_key_test', '_mp_access_token_prod', '_mp_access_token_test', '_mp_installation_key', '_collector_id_v1', '_test_user_v1'] as $name) { $options[$name] = get_option($name, null); }
    $options['gateways'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_woo-mercado-pago%' ORDER BY option_name", ARRAY_A);
    return h7_1_hash($options);
}
function h7_1_non_mp_environment_hash() {
    global $wpdb;
    $options = [];
    foreach (['woocommerce_enable_guest_checkout', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_registration_generate_password', 'woocommerce_checkout_phone_field', 'woocommerce_checkout_company_field', 'woocommerce_checkout_address_2_field', 'woocommerce_ship_to_destination', 'woocommerce_hide_out_of_stock_items', 'woocommerce_custom_orders_table_enabled', 'woocommerce_bacs_settings', 'woocommerce_bacs_accounts', 'woocommerce_cheque_settings', 'woocommerce_cod_settings'] as $name) { $options[$name] = get_option($name); }
    $options['shipping'] = h6_2_shipping_snapshot();
    foreach ([wc_get_page_id('cart'), wc_get_page_id('checkout'), get_option('page_on_front')] as $id) { $options['pages'][$id] = [get_post_field('post_content', $id), get_the_title($id)]; }
    return h7_1_hash($options);
}
function h7_1_mp_report() {
    $mp = $GLOBALS['mercadopago'] ?? null;
    $settings = (array) get_option('woocommerce_woo-mercado-pago-basic_settings', []);
    $gateways = WC()->payment_gateways()->payment_gateways();
    $pro = $gateways['woo-mercado-pago-basic'] ?? null;
    $other_enabled = false;
    foreach ($gateways as $id => $gateway) {
        if (str_starts_with($id, 'woo-mercado-pago') && 'woo-mercado-pago-basic' !== $id && 'yes' === $gateway->enabled) { $other_enabled = true; }
    }
    return [
        'official_plugin_active' => is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php'),
        'version' => defined('MP_VERSION') ? MP_VERSION : (get_plugin_data(WP_PLUGIN_DIR . '/woocommerce-mercadopago/woocommerce-mercadopago.php', false, false)['Version'] ?? null),
        'official_checkout_pro_registered' => $pro instanceof \MercadoPago\Woocommerce\Gateways\BasicGateway,
        'checkout_blocks_class_present' => class_exists('MercadoPago\\Woocommerce\\Blocks\\BasicBlock'),
        'checkout_pro_enabled' => 'yes' === ($settings['enabled'] ?? 'no'),
        'other_mp_gateways_enabled' => $other_enabled,
        'credential_presence' => ['production_public_key' => h7_1_filled(get_option('_mp_public_key_prod')), 'production_access_token' => h7_1_filled(get_option('_mp_access_token_prod')), 'test_public_key' => h7_1_filled(get_option('_mp_public_key_test')), 'test_access_token' => h7_1_filled(get_option('_mp_access_token_test'))],
        'seller_marked_test_by_plugin' => $mp ? (bool) $mp->sellerConfig->isTestUser() : false,
        'seller_linked' => h7_1_filled(get_option('_collector_id_v1')),
        'test_mode_selected' => $mp ? $mp->storeConfig->isTestMode() : null,
        'sales_mode_selected' => $mp ? $mp->storeConfig->isProductionMode() : null,
        'external_payment_authorized_by_this_tool' => false,
    ];
}
function h7_1_emit($report) {
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if (in_array($report['result'] ?? '', ['failed', 'conflict'], true)) { exit(1); }
}
function h7_1_validate_backup($id) {
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/', $id)) { WP_CLI::error('A valid local backup ID is required.'); }
    $root = '/backups/' . $id . '/';
    if (!is_file($root . 'manifest.json')) { WP_CLI::error('Create the preventive local backup first.'); }
    $manifest = json_decode(file_get_contents($root . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (1 !== ($manifest['formatVersion'] ?? null) || 'demo-woocommerce-local' !== ($manifest['project'] ?? '') || $id !== ($manifest['backupId'] ?? '') || 2 !== count($manifest['files'] ?? [])) { WP_CLI::error('Expected a complete backup belonging to the principal local instance.'); }
    foreach (['database.sql', 'wordpress.tar.gz'] as $file) {
        $entries = array_values(array_filter($manifest['files'], static fn($entry) => $file === ($entry['name'] ?? '')));
        if (1 !== count($entries) || !is_file($root . $file) || hash_file('sha256', $root . $file) !== $entries[0]['sha256']) { WP_CLI::error('Backup integrity failed.'); }
    }
}
function h7_1_order_summary($order) {
    $items = [];
    foreach ($order->get_items() as $item) {
        $items[] = ['product_id' => $item->get_product_id(), 'variation_id' => $item->get_variation_id(), 'quantity' => $item->get_quantity(), 'subtotal' => (float) $item->get_subtotal(), 'total' => (float) $item->get_total(), 'variation' => array_map(static fn($meta) => ['key' => $meta->key, 'value' => $meta->value], $item->get_formatted_meta_data())];
    }
    $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
    return ['id' => $order->get_id(), 'status' => $order->get_status(), 'is_paid' => $order->is_paid(), 'has_date_paid' => (bool) $order->get_date_paid(), 'date_paid' => (bool) $order->get_date_paid(), 'has_transaction_id' => (bool) $order->get_transaction_id(), 'payment_method' => $order->get_payment_method(), 'created_via' => $order->get_created_via(), 'customer_id' => $order->get_customer_id(), 'currency' => $order->get_currency(), 'subtotal' => (float) $order->get_subtotal(), 'discount' => (float) $order->get_discount_total(), 'shipping_total' => (float) $order->get_shipping_total(), 'total' => (float) $order->get_total(), 'shipping_methods' => array_values(array_map(static fn($item) => ['id' => $item->get_method_id(), 'instance_id' => $item->get_instance_id(), 'total' => (float) $item->get_total()], $order->get_shipping_methods())), 'stock_reduced' => (bool) $order->get_data_store()->get_stock_reduced($order->get_id()), 'notes_count' => count($notes), 'notes' => array_map(static fn($note) => ['id' => $note->id, 'customer_note' => $note->customer_note], $notes), 'items' => $items, 'data_store' => $order->get_data_store()->get_current_class_name()];
}
