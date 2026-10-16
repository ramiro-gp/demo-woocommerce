<?php
/** Native shipping QA with isolated carts; no product/stock/order writes. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
require_once __DIR__ . '/h6-2-functions.php';
$mode = $args[0] ?? 'verify'; $coupon_code = 'h6-2-qa-10';
$coupon_id = wc_get_coupon_id_by_code($coupon_code);
if ('prepare' === $mode) {
    if ($coupon_id) {
        $coupon = new WC_Coupon($coupon_id);
        if ('1' !== $coupon->get_meta('_trama_h6_2_qa') || 'percent' !== $coupon->get_discount_type() || 10.0 !== (float) $coupon->get_amount() || $coupon->get_usage_count()) { WP_CLI::error('Existing coupon is not the unchanged owned QA fixture.'); }
    } else {
        $coupon = new WC_Coupon(); $coupon->set_code($coupon_code); $coupon->set_discount_type('percent'); $coupon->set_amount(10); $coupon->set_individual_use(true); $coupon->set_description('Temporary local H6.2 QA; removed by cleanup.'); $coupon->update_meta_data('_trama_h6_2_qa', '1'); $coupon->save();
    }
    echo wp_json_encode(['result' => 'passed', 'temporary_coupon' => $coupon_code]) . "\n"; return;
}
if ('cleanup' === $mode) {
    $deleted = [];
    foreach (wc_get_orders(['billing_email' => 'h6-2-qa@example.test', 'limit' => -1]) as $order) {
        if ('checkout-draft' !== $order->get_status() || 'store-api' !== $order->get_created_via() || 'Prueba' !== $order->get_shipping_first_name() || 'H6.2' !== $order->get_shipping_last_name() || $order->get_payment_method() || $order->get_transaction_id() || $order->get_meta('_order_stock_reduced')) { WP_CLI::error('Unexpected QA order; preserve and inspect it.'); }
        $deleted[] = $order->get_id(); $order->delete(true);
    }
    if ($coupon_id) {
        $coupon = new WC_Coupon($coupon_id);
        if ('1' !== $coupon->get_meta('_trama_h6_2_qa') || 'percent' !== $coupon->get_discount_type() || 10.0 !== (float) $coupon->get_amount() || $coupon->get_usage_count()) { WP_CLI::error('Owned coupon changed; preserve and inspect it.'); }
        $coupon->delete(true);
    }
    echo wp_json_encode(['result' => 'passed', 'temporary_coupon_removed' => !wc_get_coupon_id_by_code($coupon_code), 'owned_unpaid_drafts_deleted' => $deleted]) . "\n"; return;
}
if ('cleanup-sessions' === $mode) {
    // Run only after all QA browser contexts have closed. Never truncate or
    // sweep ordinary customer sessions. Session identifiers remain private.
    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_sessions';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT session_key, session_value FROM {$table} WHERE session_value LIKE %s", '%' . $wpdb->esc_like('h6-2-qa@example.test') . '%'), ARRAY_A);
    $handler = new WC_Session_Handler();
    if (!is_callable([$handler, 'delete_session'])) { WP_CLI::error('Public WooCommerce session deletion API is unavailable; preserve sessions.'); }
    $deleted = 0; $preserved = [];
    foreach ($rows as $row) {
        $reason = null;
        $session = maybe_unserialize($row['session_value']);
        $customer = is_array($session) ? maybe_unserialize($session['customer'] ?? []) : null;
        if (ctype_digit((string) $row['session_key'])) { $reason = 'registered_customer'; }
        elseif (!is_array($session) || !is_array($customer)) { $reason = 'malformed_payload'; }
        else {
            $emails = [];
            foreach (['email', 'billing_email'] as $field) { if (!empty($customer[$field])) { $emails[] = $customer[$field]; } }
            foreach (['billing', 'billing_address'] as $group) { if (!empty($customer[$group]['email'])) { $emails[] = $customer[$group]['email']; } }
            if (!$emails || array_filter($emails, static fn($email) => 'h6-2-qa@example.test' !== $email)) { $reason = 'customer_owner_mismatch'; }
            foreach (['first_name' => 'Prueba', 'last_name' => 'H6.2'] as $field => $expected) {
                foreach ([$field, 'billing_' . $field, 'shipping_' . $field] as $key) {
                    if (isset($customer[$key]) && '' !== $customer[$key] && $expected !== $customer[$key]) { $reason = 'customer_owner_mismatch'; }
                }
                foreach (['billing', 'shipping', 'billing_address', 'shipping_address'] as $group) {
                    if (isset($customer[$group][$field]) && '' !== $customer[$group][$field] && $expected !== $customer[$group][$field]) { $reason = 'customer_owner_mismatch'; }
                }
            }
            foreach (['order_awaiting_payment', 'store_api_draft_order', 'order_id', 'chosen_payment_method', 'payment_method', 'transaction_id'] as $key) {
                if (!empty(maybe_unserialize($session[$key] ?? null))) { $reason = 'associated_order_or_payment'; }
            }
        }
        if ($reason) { $preserved[$reason] = ($preserved[$reason] ?? 0) + 1; continue; }
        $handler->delete_session($row['session_key']);
        if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE session_key = %s", $row['session_key'])) !== 0) { WP_CLI::error('An owned QA session could not be removed; identifiers withheld.'); }
        ++$deleted;
    }
    echo wp_json_encode(['result' => 'passed', 'candidates' => count($rows), 'owned_guest_sessions_deleted' => $deleted, 'preserved_by_reason' => $preserved, 'scope' => 'Exact QA customer email; any present names must match Prueba/H6.2; registered customers and order/payment sessions always preserved.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"; return;
}
if (!in_array($mode, ['verify', 'guard'], true)) { WP_CLI::error('Use prepare|verify|guard|cleanup|cleanup-sessions.'); }
$state = get_option('trama_h6_2', []);
if (($state['phase'] ?? '') !== 'complete') { WP_CLI::error('Completed shipping migration required.'); }
if ('guard' === $mode) {
    $checks = [];
    $baseline = WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php verify --quiet', ['return' => 'all', 'exit_error' => false]);
    if ($baseline->return_code) { WP_CLI::error('Resolve existing differences before testing guards.'); }
    $option_name = 'woocommerce_flat_rate_' . $state['flat_rate_instance_id'] . '_settings';
    $original = get_option($option_name); $temporary = array_replace($original, ['cost' => '4567']);
    $product = wc_get_product(wc_get_product_id_by_sku('TRM-ACC-001')); $physical = h6_2_physical($product);
    $variation = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001-NAT-S')); $variation_physical = h6_2_physical($variation);
    try {
        update_option($option_name, $temporary, false);
        $result = WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php seed ' . $state['backup_id'] . ' --quiet', ['return' => 'all', 'exit_error' => false]);
        $report = json_decode($result->stdout, true);
        $checks['manual_shipping_blocks_seed'] = 1 === $result->return_code && 'conflict' === ($report['result'] ?? '') && get_option($option_name) === $temporary;
    } finally { update_option($option_name, $original, false); WC_Cache_Helper::get_transient_version('shipping', true); }
    try {
        $product->set_weight('1.23'); $product->save();
        $result = WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php seed ' . $state['backup_id'] . ' --quiet', ['return' => 'all', 'exit_error' => false]);
        $report = json_decode($result->stdout, true);
        $checks['manual_product_blocks_seed'] = 1 === $result->return_code && 'conflict' === ($report['result'] ?? '') && '1.23' === wc_get_product($product->get_id())->get_weight('edit');
    } finally { $product->set_props($physical); $product->save(); }
    try {
        $variation->set_length('99'); $variation->save();
        $result = WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php seed ' . $state['backup_id'] . ' --quiet', ['return' => 'all', 'exit_error' => false]);
        $report = json_decode($result->stdout, true);
        $checks['manual_variation_blocks_seed'] = 1 === $result->return_code && 'conflict' === ($report['result'] ?? '') && '99' === wc_get_product($variation->get_id())->get_length('edit');
    } finally { $variation->set_props($variation_physical); $variation->save(); }
    $checks['baseline_restored'] = 0 === WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php verify --quiet', ['return' => 'all', 'exit_error' => false])->return_code;
    echo wp_json_encode(['result' => !in_array(false, $checks, true) ? 'passed' : 'failed', 'checks' => $checks, 'restored_in_finally' => true], JSON_PRETTY_PRINT) . "\n";
    if (in_array(false, $checks, true)) { exit(1); } return;
}
if (!$coupon_id) { WP_CLI::error('Prepare the owned QA coupon first.'); }
$source = json_decode(file_get_contents('/opt/demo-shipping/h6-2-shipping.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = []; $scenarios = [];
$old_cart = WC()->cart; $old_customer = WC()->customer; $old_session = WC()->session;
// WooCommerce 11.2 registers session callbacks on every cart. The original CLI
// cart is empty and would otherwise clear the isolated cart's chosen rates at
// after_calculate_totals. Its documented internal switch is used only in QA.
$old_updates = $old_cart instanceof WC_Cart ? WC_Cart_Session::are_updates_enabled_for_cart($old_cart) : null;
if ($old_cart instanceof WC_Cart) { WC_Cart_Session::set_updates_enabled_for_cart($old_cart, false); }
WC()->session = new WC_Session_Handler(); WC()->session->init();
WC()->customer = new WC_Customer(0); WC()->cart = new WC_Cart();
$cart = WC()->cart;
$cart->cart_context = 'store-api';
$set_address = static function ($country = 'AR', $province = 'C', $postcode = 'C1000', $city = 'Buenos Aires') {
    WC()->customer->set_shipping_country($country); WC()->customer->set_shipping_state($province); WC()->customer->set_shipping_postcode($postcode); WC()->customer->set_shipping_city($city); WC()->customer->set_shipping_address_1('Dirección de prueba 123'); WC()->customer->set_calculated_shipping(true);
};
$rates = static function () {
    $all = [];
    foreach (WC()->shipping()->get_packages() as $package) { foreach ($package['rates'] as $id => $rate) { $all[$id] = ['method' => $rate->get_method_id(), 'cost' => (float) $rate->get_cost(), 'label' => $rate->get_label()]; } }
    return $all;
};
$calculate = static function ($chosen = null) use ($cart, $rates) {
    $previous = WC()->session->get('chosen_shipping_methods', []);
    WC()->shipping()->reset_shipping();
    WC()->session->__unset('shipping_for_package_0');
    WC()->session->set('chosen_shipping_methods', null !== $chosen ? [$chosen] : $previous);
    $cart->calculate_totals();
    return ['subtotal' => (float) $cart->get_subtotal(), 'discount' => (float) $cart->get_discount_total(), 'shipping' => (float) $cart->get_shipping_total(), 'total' => (float) $cart->get_total('edit'), 'chosen' => WC()->session->get('chosen_shipping_methods'), 'rates' => $rates()];
};
$build = static function ($rows) use ($cart) {
    $cart->empty_cart(false);
    WC()->session->set('chosen_shipping_methods', []);
    foreach ($rows as $row) {
        $p = wc_get_product(wc_get_product_id_by_sku($row['sku']));
        $key = $p->is_type('variation') ? $cart->add_to_cart($p->get_parent_id(), $row['qty'], $p->get_id(), $p->get_variation_attributes()) : $cart->add_to_cart($p->get_id(), $row['qty']);
        if (!$key) { throw new RuntimeException('Native cart could not add ' . $row['sku']); }
        if (isset($row['price'])) { $item = clone $cart->cart_contents[$key]['data']; $item->set_price((string) $row['price']); $cart->cart_contents[$key]['data'] = $item; }
    }
};
$has = static fn($scenario, $method) => in_array($method, array_column($scenario['rates'], 'method'), true);
$flat = 'flat_rate:' . $state['flat_rate_instance_id']; $free = 'free_shipping:' . $state['free_shipping_instance_id'];
$threshold = (float) $source['free_shipping']['min_amount'];
$step = pow(10, -wc_get_price_decimals());
try {
    $set_address();
    foreach (['below' => $threshold - $step, 'exact' => $threshold, 'above' => $threshold + $step] as $name => $price) {
        $build([['sku' => 'TRM-ACC-001', 'qty' => 1, 'price' => $price]]); $scenario = $calculate(); $scenarios[$name] = $scenario;
        $checks[$name . '_core_threshold'] = $has($scenario, 'free_shipping') === ('below' !== $name) && $has($scenario, 'flat_rate') === ('below' === $name);
        $checks[$name . '_pickup_available'] = $has($scenario, 'pickup_location');
    }
    $build([['sku' => 'TRM-ACC-001', 'qty' => 1, 'price' => $threshold]]); $calculate();
    $cart->apply_coupon($coupon_code); $scenarios['coupon_after_discount'] = $calculate($flat);
    $checks['coupon_after_discount_removes_free'] = $has($scenarios['coupon_after_discount'], 'flat_rate') && !$has($scenarios['coupon_after_discount'], 'free_shipping') && (float) $cart->get_discount_total() === $threshold * 0.1;
    $checks['coupon_total'] = abs($scenarios['coupon_after_discount']['total'] - ($threshold * 0.9 + (float) $source['flat_rate']['cost'])) < 0.001;
    $build([['sku' => 'TRM-ACC-001', 'qty' => 1, 'price' => $threshold / 0.9 + 1]]); $cart->apply_coupon($coupon_code); $scenarios['coupon_still_above'] = $calculate($free);
    $checks['coupon_still_above_free'] = $has($scenarios['coupon_still_above'], 'free_shipping') && !$has($scenarios['coupon_still_above'], 'flat_rate');
    $build([['sku' => 'TRM-ACC-001', 'qty' => 1]]); $scenarios['simple_flat'] = $calculate($flat); $scenarios['simple_pickup'] = $calculate('pickup_location:0');
    $checks['method_switch_totals'] = (float) $source['flat_rate']['cost'] === $scenarios['simple_flat']['shipping'] && 0.0 === $scenarios['simple_pickup']['shipping'] && abs($scenarios['simple_flat']['total'] - $scenarios['simple_pickup']['total'] - (float) $source['flat_rate']['cost']) < 0.001;
    $checks['pickup_selected'] = ['pickup_location:0'] === $scenarios['simple_pickup']['chosen'];
    $build([['sku' => 'TRM-ACC-001', 'qty' => 1, 'price' => $threshold / 2]]); $scenarios['quantity_one'] = $calculate($flat);
    $key = array_key_first($cart->cart_contents); $cart->set_quantity($key, 2, false); $scenarios['quantity_two'] = $calculate();
    $checks['quantity_recalculates_free_and_selection'] = $has($scenarios['quantity_one'], 'flat_rate') && $has($scenarios['quantity_two'], 'free_shipping') && !$has($scenarios['quantity_two'], 'flat_rate') && [$free] === $scenarios['quantity_two']['chosen'] && 0.0 === $scenarios['quantity_two']['shipping'];
    $build([['sku' => 'TRM-REM-001-NAT-S', 'qty' => 1]]); $scenarios['variation'] = $calculate($flat);
    $v = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001-NAT-S')); $parent = wc_get_product($v->get_parent_id());
    $checks['variation_native_inheritance'] = '' === $v->get_weight('edit') && $v->get_weight() === $parent->get_weight() && $v->get_length() === $parent->get_length() && $v->get_width() === $parent->get_width() && $v->get_height() === $parent->get_height() && $has($scenarios['variation'], 'flat_rate');
    $build([['sku' => 'TRM-ACC-001', 'qty' => 2], ['sku' => 'TRM-REM-001-NAT-S', 'qty' => 2]]); $scenarios['multiple'] = $calculate($free);
    $checks['multiple_products_total'] = 102000.0 === $scenarios['multiple']['subtotal'] && 102000.0 === $scenarios['multiple']['total'] && $has($scenarios['multiple'], 'free_shipping');
    $build([['sku' => 'TRM-ACC-001', 'qty' => 1]]); $set_address('AR', 'X', '5000', 'Córdoba'); $scenarios['province_postcode'] = $calculate($flat);
    $checks['province_postcode_recalculate'] = $has($scenarios['province_postcode'], 'flat_rate') && 4500.0 === $scenarios['province_postcode']['shipping'];
    $set_address('UY', 'MO', '11000', 'Montevideo'); $scenarios['outside_zone'] = $calculate($flat);
    $checks['outside_zone_no_delivery'] = !$has($scenarios['outside_zone'], 'flat_rate') && !$has($scenarios['outside_zone'], 'free_shipping') && $has($scenarios['outside_zone'], 'pickup_location') && [''] === $scenarios['outside_zone']['chosen'];
    $set_address(); $scenarios['return_to_argentina'] = $calculate($flat);
    $checks['return_to_argentina_recalculates'] = $has($scenarios['return_to_argentina'], 'flat_rate') && 4500.0 === $scenarios['return_to_argentina']['shipping'];
    $set_address('', '', '', ''); $scenarios['pickup_no_delivery_address'] = $calculate('pickup_location:0');
    $checks['pickup_no_delivery_address'] = $has($scenarios['pickup_no_delivery_address'], 'pickup_location') && 0.0 === $scenarios['pickup_no_delivery_address']['shipping'] && ['pickup_location:0'] === $scenarios['pickup_no_delivery_address']['chosen'];
    $checks['hpos'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    $checks['no_new_gateway'] = count(WC()->payment_gateways()->get_available_payment_gateways()) === 0;
    $checks['native_selection_state_consistent'] = !array_filter($scenarios, static function ($row) {
        if (!is_array($row['chosen']) || count($row['chosen']) !== 1) { return true; }
        $chosen = current($row['chosen']);
        if ('' !== $chosen) { return !isset($row['rates'][$chosen]); }
        // Blocks can intentionally leave delivery unselected when only pickup
        // exists. It must never retain a nonexistent paid/free delivery rate.
        return (bool) array_filter($row['rates'], static fn($rate) => 'pickup_location' !== $rate['method']);
    });
} finally {
    $cart->empty_cart(false); WC()->cart = $old_cart; WC()->customer = $old_customer; WC()->session = $old_session;
    if ($old_cart instanceof WC_Cart) { WC_Cart_Session::set_updates_enabled_for_cart($old_cart, $old_updates); }
}
echo wp_json_encode(['result' => !in_array(false, $checks, true) ? 'passed' : 'failed', 'checks' => $checks, 'scenarios' => $scenarios, 'cart_context' => 'store-api', 'threshold_step' => $step, 'simulation' => 'Boundary prices alter isolated cart item clones only. No product save, order, payment or stock write.', 'pickup_scope' => 'Native Blocks pickup is independent of shipping zones; outside AR has no delivery rates and does not silently select pickup.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if (in_array(false, $checks, true)) { exit(1); }
