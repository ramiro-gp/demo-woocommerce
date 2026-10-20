<?php
/** Local-only H8.1 fixtures and preservation QA. Never loaded by HTTP or plugins. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
$mode = $args[0] ?? 'inspect';
$state_path = '/backups/h8-1-qa-state.json';
$fixture_path = '/backups/h8-1-qa-fixtures.json';
$hash = static fn($value) => hash('sha256', wp_json_encode($value));
$emit = static fn($value) => print(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
$require = static function ($condition) { if (!$condition) { WP_CLI::error('H8.1 owned local fixture or preservation guard failed; private data withheld.'); } };
$fingerprints = static function ($ids, $qa_dates = []) use ($hash) {
    $out = [];
    foreach ($ids['orders'] as $id) {
        $order = wc_get_order($id);
        if (!$order) { $out['orders'][$id] = 'missing'; continue; }
        $data = $order->get_data();
        $data['meta_data'] = array_values(array_filter(array_map(static fn($m) => $m->get_data(), $order->get_meta_data()), static fn($m) => '_debug_log_source_pending_deletion' !== $m['key']));
        $data['items'] = array_map(static fn($item) => $item->get_data(), $order->get_items(['line_item', 'shipping', 'fee', 'coupon', 'tax']));
        $data['refunds'] = array_map(static fn($r) => $r->get_data(), $order->get_refunds());
        $out['orders'][$id] = $hash($data);
    }
    foreach ($ids['catalog'] as $id) {
        $object = 'shop_coupon' === get_post_type($id) ? new WC_Coupon($id) : wc_get_product($id);
        $data = $object ? $object->get_data() : null;
        if ($data && isset($qa_dates[$id])) {
            // Comparison only, never a write: restore the original timestamp
            // in memory for the two identified legacy QA fixtures, after native
            // stock restoration. Every other data field must still hash equal.
            if ('product' === get_post_type($id) && str_starts_with($object->get_sku(), 'H71-CONCURRENCY-')
                && $object->get_meta('_h71_concurrency_qa') && 'hidden' === $object->get_catalog_visibility()
                && (int) $object->get_stock_quantity() === $qa_dates[$id]['stock']) {
                $data['date_modified'] = wc_string_to_datetime($qa_dates[$id]['post_modified']);
            }
        }
        $out['catalog'][$id] = $object ? $hash($data) : 'missing';
    }
    foreach ($ids['users'] as $id) {
        $user = get_userdata($id);
        $meta = array_filter(get_user_meta($id), static fn($key) => str_starts_with($key, 'billing_') || str_starts_with($key, 'shipping_'), ARRAY_FILTER_USE_KEY);
        $out['users'][$id] = $hash([$user->user_email, $user->display_name, $user->roles, $meta]);
    }
    $settings = [];
    foreach (['woocommerce_bacs_settings', 'woocommerce_bacs_accounts', 'woocommerce_woo-mercado-pago-basic_settings', '_mp_access_token_prod', '_mp_access_token_test', '_mp_public_key_prod', '_mp_public_key_test', 'template', 'stylesheet', 'theme_mods_commerce-base', 'commerce_account_show_downloads', 'woocommerce_custom_orders_table_enabled', 'woocommerce_email_from_address', 'woocommerce_email_from_name', 'woocommerce_email_header_image', 'woocommerce_email_footer_text', 'woocommerce_registration_privacy_policy_text'] as $key) { $settings[$key] = get_option($key); }
    foreach (WC()->mailer()->get_emails() as $email) { $settings['email_' . $email->id] = get_option('woocommerce_' . $email->id . '_settings'); }
    $settings['zones'] = WC_Shipping_Zones::get_zones();
    $out['settings'] = $hash($settings);
    $out['pages'] = $hash(array_map(static fn($page) => [$page->ID, $page->post_name, $page->post_status, $page->post_title, $page->post_content], get_posts(['post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC'])));
    return $out;
};
$require(in_array($mode, ['snapshot', 'prepare', 'reset', 'verify', 'inspect', 'source', 'theme', 'cleanup-concurrency'], true));
if ('cleanup-concurrency' === $mode) {
    $baseline = json_decode(file_get_contents($state_path), true);
    $concurrency = get_option('trama_h71_concurrency_qa', []);
    $targets = []; $units = [];
    foreach ($concurrency['orders'] ?? [] as $id) {
        if (in_array($id, $baseline['ids']['orders'], true)) { continue; }
        $order = wc_get_order($id);
        if (!$order || !$order->has_status('on-hold') || 'bacs' !== $order->get_payment_method()) { continue; }
        $require($concurrency['run'] === $order->get_meta('_h71_concurrency_qa') && 'h71.concurrency@example.test' === $order->get_billing_email()
            && !$order->get_date_paid() && in_array($order->get_transaction_id(), ['', 'H71-INTERNAL-NOT-A-PAYMENT'], true));
        foreach ($order->get_items() as $item) {
            $require(in_array($item->get_product_id(), $concurrency['products'], true));
            $units[$item->get_product_id()] = ($units[$item->get_product_id()] ?? 0) + $item->get_quantity();
        }
        $targets[] = $order;
    }
    $require(6 === count($targets) && 5 === $units[$concurrency['products']['A']] && 1 === $units[$concurrency['products']['B']]);
    // Cancel only this milestone's new, unpaid synthetic BACS orders. The core
    // restores its own reduced stock; never edit quantities or historical orders.
    $stock_before = array_map(static fn($id) => wc_get_product($id)->get_stock_quantity(), $concurrency['products']);
    foreach ($targets as $order) { $order->update_status('cancelled', 'H8.1 QA cleanup: native cancellation of this run only; no funds or refund.'); }
    $after = $fingerprints($baseline['ids']);
    $stock_after = array_map(static fn($id) => wc_get_product($id)->get_stock_quantity(), $concurrency['products']);
    $restored = $stock_after['A'] === $stock_before['A'] + 5 && $stock_after['B'] === $stock_before['B'] + 1;
    $emit(['result' => $restored ? 'passed' : 'failed', 'new_qa_orders_cancelled' => array_map(static fn($o) => $o->get_id(), $targets), 'native_restock_units' => array_values($units), 'historical_orders_touched' => false, 'strict_catalog_hash_including_qa_timestamps' => $baseline['before']['catalog'] === $after['catalog']]);
    return;
}
if ('snapshot' === $mode) {
    $require(!file_exists($state_path));
    $ids = ['orders' => wc_get_orders(['limit' => -1, 'return' => 'ids', 'type' => 'shop_order']), 'catalog' => get_posts(['post_type' => ['product', 'product_variation', 'shop_coupon'], 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1]), 'users' => get_users(['fields' => 'ID'])];
    file_put_contents($state_path, wp_json_encode(['ids' => $ids, 'before' => $fingerprints($ids)]));
    $emit(['result' => 'passed', 'baseline_counts' => array_map('count', $ids)]); return;
}
if ('prepare' === $mode || 'reset' === $mode) {
    $require(file_exists($state_path));
    $fixture = file_exists($fixture_path) ? json_decode(file_get_contents($fixture_path), true) : ['customers' => [], 'account' => wc_get_page_permalink('myaccount'), 'checkout' => wc_get_checkout_url(), 'cart' => wc_get_cart_url()];
    $fixture['cart'] = wc_get_cart_url();
    foreach (wc_get_products(['limit' => 30, 'type' => 'simple', 'status' => 'publish']) as $product) {
        if ($product->is_purchasable() && $product->is_in_stock() && $product->is_visible()) { $fixture['product'] = ['id' => $product->get_id(), 'url' => $product->get_permalink()]; break; }
    }
    foreach (['complete', 'blank', 'different'] as $case) {
        if (!isset($fixture['customers'][$case])) {
            $username = 'h81_' . $case . '_' . strtolower(wp_generate_password(8, false));
            $password = wp_generate_password(32, true);
            $id = wp_insert_user(['user_login' => $username, 'user_pass' => $password, 'user_email' => $username . '@example.test', 'display_name' => 'Prueba H8.1 ' . $case, 'role' => 'customer']);
            $require(!is_wp_error($id));
            update_user_meta($id, '_commerce_h81_qa', $case);
            $fixture['customers'][$case] = ['id' => $id, 'username' => $username, 'password' => $password];
        }
        $entry = &$fixture['customers'][$case];
        $require($case === get_user_meta($entry['id'], '_commerce_h81_qa', true) && ['customer'] === get_userdata($entry['id'])->roles);
        $billing = ['first_name' => 'Prueba', 'last_name' => 'Commerce Core', 'company' => 'Empresa de prueba', 'country' => 'AR', 'state' => 'C', 'city' => 'Buenos Aires', 'postcode' => '1001', 'address_1' => 'Calle Ficticia 123', 'address_2' => 'Piso 2', 'phone' => '1123456789'];
        if ('different' === $case) { $billing = array_merge($billing, ['first_name' => 'Otra', 'last_name' => 'Persona de prueba', 'state' => 'B', 'city' => 'La Plata', 'postcode' => '1900', 'address_1' => 'Calle Distinta 456']); }
        if ('blank' === $case) { $billing = array_fill_keys(array_keys($billing), ''); }
        $shipping = array_fill_keys(array_keys($billing), '');
        if ('complete' === $case) { $shipping = array_merge($billing, ['address_1' => 'Envío previo 999', 'address_2' => '', 'company' => '']); }
        $customer = new WC_Customer($entry['id']);
        foreach (['billing' => $billing, 'shipping' => $shipping] as $group => $values) {
            foreach ($values as $key => $value) { $setter = 'set_' . $group . '_' . $key; if (is_callable([$customer, $setter])) { $customer->$setter($value); } }
        }
        $customer->set_billing_email(get_userdata($entry['id'])->user_email);
        $customer->save();
        $entry['billing'] = $billing; $entry['shipping'] = $shipping;
        unset($entry);
    }
    file_put_contents($fixture_path, wp_json_encode($fixture));
    $emit(['result' => 'passed', 'owned_customers' => count($fixture['customers']), 'credentials' => 'Private backups/h8-1-qa-fixtures.json only; never console or Git.']); return;
}
$checks = [
    'wc_11_2_1' => '11.2.1' === WC_VERSION,
    'hpos' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
    'commerce_core_active' => is_plugin_active('commerce-core/commerce-core.php'),
    'integrity_active' => is_plugin_active('commerce-order-integrity/commerce-order-integrity.php'),
    'communications_active' => is_plugin_active('commerce-communications/commerce-communications.php'),
    'bacs_enabled' => 'yes' === (get_option('woocommerce_bacs_settings')['enabled'] ?? ''),
    'checkout_pro_disabled' => 'yes' !== (get_option('woocommerce_woo-mercado-pago-basic_settings')['enabled'] ?? ''),
    'native_checkout_blocks' => has_block('woocommerce/checkout', wc_get_page_id('checkout')),
    'native_save_handler' => false !== has_action('template_redirect', ['WC_Form_Handler', 'save_address']),
];
if ('source' === $mode || 'theme' === $mode) {
    $fixture = json_decode(file_get_contents($fixture_path), true);
    $class = 'CommerceCore\\Address_Copy';
    wp_set_current_user(0);
    $checks['guest_no_source'] = [] === $class::source();
    ob_start(); $class::render(); $checks['guest_no_markup'] = '' === ob_get_clean();
    foreach ($fixture['customers'] as $case => $entry) {
        wp_set_current_user($entry['id']);
        // Deliberately injected query IDs never select the source customer.
        $_GET['user_id'] = $fixture['customers']['different']['id'];
        $values = $class::source();
        $checks[$case . '_current_customer_only'] = 'blank' === $case ? [] === $values : (new WC_Customer($entry['id']))->get_billing_address_1() === ($values['address_1'] ?? null);
        $checks[$case . '_no_email'] = !isset($values['email']);
    }
    wp_set_current_user($fixture['customers']['complete']['id']);
    $customer = new WC_Customer(get_current_user_id());
    $before = $hash($customer->get_data());
    ob_start();
    if ('theme' === $mode) { WC_Shortcode_My_Account::edit_address('shipping'); }
    else { $class::render(); }
    $html = ob_get_clean();
    $checks['render_no_write'] = $before === $hash((new WC_Customer(get_current_user_id()))->get_data());
    $checks['shipping_hook_once'] = 10 === has_action('woocommerce_before_edit_address_form_shipping', [$class, 'render']);
    $checks['no_billing_hook'] = false === has_action('woocommerce_before_edit_address_form_billing', [$class, 'render']);
    $checks['markup_and_asset_without_theme_dependency'] = str_contains($html, 'commerce-core-copy-source') && wp_script_is('commerce-core-address-copy', 'enqueued');
    $custom = static function ($fields) { $fields['shipping_delivery_note'] = ['required' => true, 'label' => 'Custom field']; return $fields; };
    add_filter('woocommerce_shipping_fields', $custom);
    $custom_source = $class::source();
    $checks['custom_required_fields_left_for_native_manual_validation'] = !empty($custom_source) && !isset($custom_source['delivery_note']);
    remove_filter('woocommerce_shipping_fields', $custom);
    foreach (['address_1' => '', 'state' => 'INVALID', 'country' => 'INVALID'] as $field => $value) {
        $override = static fn() => $value;
        add_filter('woocommerce_customer_get_billing_' . $field, $override);
        $checks['invalid_or_incomplete_' . $field . '_rejected'] = [] === $class::source();
        remove_filter('woocommerce_customer_get_billing_' . $field, $override);
    }
    $restrict = static fn() => [];
    add_filter('woocommerce_countries_shipping_countries', $restrict);
    $checks['unavailable_shipping_destination_rejected'] = [] === $class::source();
    remove_filter('woocommerce_countries_shipping_countries', $restrict);
    $us = static fn() => ['US' => 'United States'];
    $country = static fn() => 'US'; $state = static fn() => 'CA'; $postcode = static fn() => 'INVALID';
    add_filter('woocommerce_countries_shipping_countries', $us);
    add_filter('woocommerce_customer_get_billing_country', $country);
    add_filter('woocommerce_customer_get_billing_state', $state);
    add_filter('woocommerce_customer_get_billing_postcode', $postcode);
    $checks['invalid_us_postcode_rejected_by_native_validator'] = [] === $class::source();
    remove_filter('woocommerce_countries_shipping_countries', $us);
    remove_filter('woocommerce_customer_get_billing_country', $country);
    remove_filter('woocommerce_customer_get_billing_state', $state);
    remove_filter('woocommerce_customer_get_billing_postcode', $postcode);
    if ('theme' === $mode) {
        $checks['isolated_other_theme_loaded'] = 'twentytwentyfive' === get_stylesheet() && !function_exists('commerce_base_header');
        $checks['native_form_with_other_theme'] = str_contains($html, 'woocommerce-edit-address-nonce') && str_contains($html, 'name="shipping_address_1"');
    }
}
if ('verify' === $mode) {
    $state = json_decode(file_get_contents($state_path), true);
    $after = $fingerprints($state['ids']);
    foreach ($state['before'] as $key => $value) { $checks[$key . '_preserved'] = $value === $after[$key]; }
    if (!$checks['catalog_preserved'] && file_exists('/backups/h8-1-qa-product-origin.json')) {
        $qa_dates = json_decode(file_get_contents('/backups/h8-1-qa-product-origin.json'), true);
        $normalized = $fingerprints($state['ids'], $qa_dates);
        $checks['catalog_preserved'] = $state['before']['catalog'] === $normalized['catalog'];
        $checks['only_owned_qa_modified_timestamps_differ'] = $checks['catalog_preserved'];
    }
}
$pages = [];
foreach (['politica-privacidad', 'privacy-policy', 'envios', 'cambios-y-devoluciones', 'terminos', 'boton-de-arrepentimiento', 'contacto', 'preguntas-frecuentes'] as $slug) {
    $page = get_page_by_path($slug);
    $pages[$slug] = $page ? ['id' => $page->ID, 'status' => $page->post_status] : ['status' => 'absent'];
}
$pages['native_privacy_page'] = ['id' => wc_privacy_policy_page_id(), 'status' => get_post_status(wc_privacy_policy_page_id())];
$emit(['result' => in_array(false, $checks, true) ? 'failed' : 'passed', 'checks' => $checks, 'legal_inventory_only' => $pages]);
if (in_array(false, $checks, true)) { exit(1); }
