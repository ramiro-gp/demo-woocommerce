<?php
/** Native local QA; internal state simulations are never MP payment evidence. */
require_once __DIR__ . '/h7-1-functions.php';
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['prepare', 'verify', 'orders', 'guard', 'cleanup'], true)) { WP_CLI::error('Use prepare|verify|orders|guard|cleanup.'); }
// This is defense in depth: no code below calls a payment provider or refunds.
// WordPress HTTP requests triggered by ancillary hooks are blocked as well.
$blocked_http = 0;
$http_guard = static function () use (&$blocked_http) { ++$blocked_http; return new WP_Error('h7_1_qa_network_disabled', 'External HTTP is disabled in local H7.1 QA.'); };
add_filter('pre_http_request', $http_guard, PHP_INT_MAX, 3);
$fixture_option = 'trama_h7_1_qa_fixtures';
$state = (array) get_option($fixture_option, []);
$coupon_code = 'h7-1-qa-10';
$fixture_summary = static function () use (&$state, $coupon_code) {
    $out = [];
    foreach (['simple', 'variable'] as $key) {
        $product = wc_get_product($state[$key] ?? 0);
        if (!$product || '1' !== $product->get_meta('_trama_h7_1_qa_fixture')) { throw new RuntimeException('Owned QA product missing.'); }
        $out[$key] = ['id' => $product->get_id(), 'parent_id' => $product->get_parent_id(), 'slug' => $product->get_slug(), 'sku' => $product->get_sku(), 'price' => (float) $product->get_price(), 'regular_price' => (float) $product->get_regular_price(), 'stock' => (int) $product->get_stock_quantity(), 'attributes' => $product->is_type('variation') ? $product->get_variation_attributes() : (object) [], 'url' => get_permalink($product->get_parent_id() ?: $product->get_id())];
    }
    $out['coupon'] = ['code' => $coupon_code, 'percent' => 10];
    return $out;
};
if ('prepare' === $mode) {
    // No collision is adopted and no field/stock of an existing fixture reset.
    foreach (['H7-1-QA-SIMPLE', 'H7-1-QA-VARIABLE', 'H7-1-QA-VARIATION'] as $sku) {
        $id = wc_get_product_id_by_sku($sku);
        if ($id && !in_array($id, array_values($state), true)) { WP_CLI::error('SKU collision; preserve existing product.'); }
    }
    if (!$state) {
        $simple = new WC_Product_Simple();
        $simple->set_props(['name' => 'Prueba H7.1 — producto simple', 'slug' => 'h7-1-qa-simple', 'sku' => 'H7-1-QA-SIMPLE', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => '59000', 'sale_price' => '50000', 'manage_stock' => true, 'stock_quantity' => 6, 'backorders' => 'no', 'tax_status' => 'taxable', 'weight' => '0.2', 'length' => '24', 'width' => '18', 'height' => '3']);
        $simple->update_meta_data('_trama_h7_1_qa_fixture', '1'); $simple->save();
        $parent = new WC_Product_Variable();
        $attributes = [];
        foreach (['Talle' => ['S'], 'Color' => ['Natural']] as $name => $values) {
            $attribute = new WC_Product_Attribute(); $attribute->set_name($name); $attribute->set_options($values); $attribute->set_visible(true); $attribute->set_variation(true); $attributes[] = $attribute;
        }
        $parent->set_props(['name' => 'Prueba H7.1 — producto variable', 'slug' => 'h7-1-qa-variable', 'sku' => 'H7-1-QA-VARIABLE', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'attributes' => $attributes, 'default_attributes' => ['talle' => 'S', 'color' => 'Natural'], 'weight' => '0.3', 'length' => '30', 'width' => '22', 'height' => '3']);
        $parent->update_meta_data('_trama_h7_1_qa_fixture', '1'); $parent->save();
        $variation = new WC_Product_Variation();
        $variation->set_props(['parent_id' => $parent->get_id(), 'sku' => 'H7-1-QA-VARIATION', 'status' => 'publish', 'attributes' => ['talle' => 'S', 'color' => 'Natural'], 'regular_price' => '60000', 'sale_price' => '52000', 'manage_stock' => true, 'stock_quantity' => 6, 'backorders' => 'no']);
        $variation->update_meta_data('_trama_h7_1_qa_fixture', '1'); $variation->save(); WC_Product_Variable::sync($parent->get_id());
        $state = ['simple' => $simple->get_id(), 'parent' => $parent->get_id(), 'variable' => $variation->get_id()];
        update_option($fixture_option, $state, false);
    }
    foreach ($state as $id) {
        $product = wc_get_product($id);
        if (!$product || '1' !== $product->get_meta('_trama_h7_1_qa_fixture')) { WP_CLI::error('Existing fixture ownership changed; preserve it.'); }
    }
    $coupon_id = wc_get_coupon_id_by_code($coupon_code);
    if ($coupon_id) {
        $coupon = new WC_Coupon($coupon_id);
        if ('1' !== $coupon->get_meta('_trama_h7_1_qa_fixture') || 'percent' !== $coupon->get_discount_type() || 10.0 !== (float) $coupon->get_amount()) { WP_CLI::error('Coupon collision or changed settings; preserve it.'); }
    } else {
        $coupon = new WC_Coupon(); $coupon->set_code($coupon_code); $coupon->set_discount_type('percent'); $coupon->set_amount(10); $coupon->set_individual_use(true); $coupon->set_product_ids([$state['simple'], $state['parent'], $state['variable']]); $coupon->set_description('Local H7.1 QA fixture, exclusively owned hidden products.'); $coupon->update_meta_data('_trama_h7_1_qa_fixture', '1'); $coupon->save();
    }
    h7_1_emit(['result' => 'passed', 'fixtures' => $fixture_summary(), 'stock_reset' => false, 'payment_provider_calls' => 0, 'blocked_ancillary_http_requests' => $blocked_http]); return;
}
if ('orders' === $mode) {
    $orders = wc_get_orders(['billing_email' => 'h7-1-qa@example.test', 'limit' => -1, 'orderby' => 'id', 'order' => 'ASC']);
    // Email alone never authorizes mutation; this is a read-only projection.
    $fixtures = $state ? $fixture_summary() : null;
    h7_1_emit(['result' => 'passed', 'fixtures' => $fixtures, 'stock' => $fixtures ? ['simple' => $fixtures['simple']['stock'], 'variable' => $fixtures['variable']['stock']] : null, 'orders' => array_map('h7_1_order_summary', $orders), 'sensitive_fields_withheld' => true]); return;
}
if ('cleanup' === $mode) {
    // Explicit operator action only, after all browser contexts have closed.
    // Preserve orders as evidence; cancellation is native and moves no money.
    $cancelled = []; $preserved = [];
    foreach (wc_get_orders(['billing_email' => 'h7-1-qa@example.test', 'limit' => -1]) as $order) {
        $owned = 0 === $order->get_customer_id() && 'store-api' === $order->get_created_via() && 'Prueba' === $order->get_billing_first_name() && 'H7.1' === $order->get_billing_last_name() && !$order->get_transaction_id() && ($order->get_payment_method() === 'bacs' || ($order->has_status('checkout-draft') && !$order->get_payment_method())) && $order->has_status(['pending', 'on-hold', 'processing', 'failed', 'cancelled', 'checkout-draft']);
        foreach ($order->get_items() as $item) {
            $product = wc_get_product($item->get_variation_id() ?: $item->get_product_id());
            if (!$product || !in_array($product->get_id(), array_values($state), true) || '1' !== $product->get_meta('_trama_h7_1_qa_fixture')) { $owned = false; }
        }
        if (!$owned || !$order->get_items()) { $preserved[] = $order->get_id(); continue; }
        if (!$order->has_status('cancelled')) { $order->update_status('cancelled', 'Explicit local H7.1 QA cleanup. Native cancellation only; no transfer, payment or refund.'); }
        $cancelled[] = $order->get_id();
    }
    h7_1_emit(['result' => 'passed', 'owned_order_ids_cancelled_or_already_cancelled' => $cancelled, 'preserved_order_ids' => $preserved, 'fixtures' => $state ? $fixture_summary() : null, 'orders_deleted' => false, 'stock_assigned' => false, 'refunds_executed' => false]); return;
}
if ('guard' === $mode) {
    $before = h7_1_sensitive_snapshot(); $checks = [];
    foreach (['h5-catalog.php seed h5-before-catalog', 'h6-1-cart-checkout.php configure h6-1-before-cart-checkout', 'h6-2-shipping.php seed h6-2-before-shipping-logistics', 'h7-1-payments.php configure h7-1-before-payments'] as $command) {
        $result = WP_CLI::runcommand('eval-file /opt/local-tools/' . $command . ' --quiet', ['return' => 'all', 'exit_error' => false]);
        $checks[strtok($command, ' ')] = 0 === $result->return_code && $before === h7_1_sensitive_snapshot();
    }
    $original_bacs = get_option('woocommerce_bacs_settings');
    $original_accounts = get_option('woocommerce_bacs_accounts', null);
    if (array_diff_assoc(h7_1_demo_bacs(), (array) $original_bacs) || h7_1_filled($original_accounts)) { WP_CLI::error('Conflict tests require unchanged, non-operational owned BACS demo configuration.'); }
    try {
        $temporary = array_replace($original_bacs, ['title' => 'H7.1 local manual configuration guard']);
        update_option('woocommerce_bacs_settings', $temporary, false);
        $result = WP_CLI::runcommand('eval-file /opt/local-tools/h7-1-payments.php configure h7-1-before-payments --quiet', ['return' => 'all', 'exit_error' => false]);
        $report = json_decode($result->stdout, true);
        $checks['manual_bacs_copy_preserved_and_configure_refused'] = 1 === $result->return_code && 'conflict' === ($report['result'] ?? '') && get_option('woocommerce_bacs_settings') === $temporary;
    } finally { update_option('woocommerce_bacs_settings', $original_bacs, false); }
    try {
        // An intentionally incomplete, non-operational record proves that even
        // disabled/empty-looking client banking configuration is not replaced.
        $temporary = [['account_name' => 'H7.1 QA NON OPERATIONAL', 'account_number' => '', 'sort_code' => '', 'bank_name' => '', 'iban' => '', 'bic' => '']];
        update_option('woocommerce_bacs_accounts', $temporary, false);
        $result = WP_CLI::runcommand('eval-file /opt/local-tools/h7-1-payments.php configure h7-1-before-payments --quiet', ['return' => 'all', 'exit_error' => false]);
        $report = json_decode($result->stdout, true);
        $checks['manual_banking_data_preserved_and_configure_refused'] = 1 === $result->return_code && 'conflict' === ($report['result'] ?? '') && get_option('woocommerce_bacs_accounts') === $temporary;
    } finally {
        if (null === $original_accounts) { delete_option('woocommerce_bacs_accounts'); } else { update_option('woocommerce_bacs_accounts', $original_accounts, false); }
    }
    $checks['sensitive_baseline_restored'] = $before === h7_1_sensitive_snapshot();
    h7_1_emit(['result' => in_array(false, $checks, true) ? 'failed' : 'passed', 'checks' => $checks, 'credential_and_bank_settings_preserved' => $before === h7_1_sensitive_snapshot(), 'sensitive_values_or_hashes_exposed' => false]); return;
}
if (!$state || !wc_get_coupon_id_by_code($coupon_code)) { WP_CLI::error('Prepare the owned QA fixtures first.'); }
$checks = []; $scenarios = []; $inventory = []; $technical_orders = []; $probe = null; $totals_products = []; $totals_coupon = null; $stage = 'setup'; $failed = false;
$assert = static function ($condition, $name) use (&$checks) { $checks[$name] = (bool) $condition; if (!$condition) { throw new RuntimeException($name); } };
$sensitive_before = h7_1_sensitive_snapshot();
$old_cart = WC()->cart; $old_customer = WC()->customer; $old_session = WC()->session;
$old_updates = $old_cart instanceof WC_Cart ? WC_Cart_Session::are_updates_enabled_for_cart($old_cart) : null;
if ($old_cart instanceof WC_Cart) { WC_Cart_Session::set_updates_enabled_for_cart($old_cart, false); }
WC()->session = new WC_Session_Handler(); WC()->session->init(); WC()->customer = new WC_Customer(0); WC()->cart = new WC_Cart();
$cart = WC()->cart; $cart->cart_context = 'store-api';
WC()->customer->set_props(['shipping_country' => 'AR', 'shipping_state' => 'C', 'shipping_postcode' => 'C1000', 'shipping_city' => 'Buenos Aires', 'shipping_address_1' => 'Dirección de prueba 123']); WC()->customer->set_calculated_shipping(true);
$shipping_state = get_option('trama_h6_2', []);
$flat = 'flat_rate:' . $shipping_state['flat_rate_instance_id']; $free = 'free_shipping:' . $shipping_state['free_shipping_instance_id'];
$calculate = static function ($rows, $chosen, $coupon = false) use ($cart, &$coupon_code) {
    $cart->empty_cart(false);
    foreach ($rows as $row) {
        $p = wc_get_product($row[0]);
        $key = $p->is_type('variation') ? $cart->add_to_cart($p->get_parent_id(), $row[1], $p->get_id(), $p->get_variation_attributes()) : $cart->add_to_cart($p->get_id(), $row[1]);
        if (!$key) { throw new RuntimeException('Native QA cart add failed.'); }
    }
    if ($coupon && !$cart->apply_coupon($coupon_code)) { throw new RuntimeException('Native QA coupon failed.'); }
    WC()->shipping()->reset_shipping(); WC()->session->__unset('shipping_for_package_0'); WC()->session->set('chosen_shipping_methods', [$chosen]); $cart->calculate_totals();
    $rates = [];
    foreach (WC()->shipping()->get_packages() as $package) { foreach ($package['rates'] as $id => $rate) { $rates[$id] = ['method' => $rate->get_method_id(), 'cost' => (float) $rate->get_cost()]; } }
    return ['subtotal' => (float) $cart->get_subtotal(), 'discount' => (float) $cart->get_discount_total(), 'shipping' => (float) $cart->get_shipping_total(), 'total' => (float) $cart->get_total('edit'), 'chosen' => WC()->session->get('chosen_shipping_methods'), 'rates' => $rates];
};
$run = str_replace('-', '', wp_generate_uuid4());
try {
    $stage = 'totals';
    // Each run owns fresh products for cart arithmetic. Browser fixtures may
    // legitimately be sold out after its checkout QA; never reset that stock.
    $totals_state = [];
    foreach (['simple', 'parent', 'variable'] as $key) {
        $source = wc_get_product($state[$key]);
        $copy = 'parent' === $key ? new WC_Product_Variable() : ('variable' === $key ? new WC_Product_Variation() : new WC_Product_Simple());
        $props = ['name' => 'H7.1 internal totals QA ' . $key, 'sku' => 'H7-1-TOTAL-' . $key . '-' . substr($run, 0, 12), 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => $source->get_regular_price(), 'sale_price' => $source->get_sale_price(), 'manage_stock' => 'parent' !== $key, 'backorders' => 'no', 'weight' => $source->get_weight('edit'), 'length' => $source->get_length('edit'), 'width' => $source->get_width('edit'), 'height' => $source->get_height('edit')];
        if ('parent' !== $key) { $props['stock_quantity'] = 6; }
        if ('parent' === $key) { $props['attributes'] = $source->get_attributes(); }
        if ('variable' === $key) { $props['parent_id'] = $totals_state['parent']; $props['attributes'] = $source->get_attributes(); }
        $copy->set_props($props); $copy->update_meta_data('_trama_h7_1_core', $run); $copy->save(); $totals_products[] = $copy->get_id(); $totals_state[$key] = $copy->get_id();
    }
    WC_Product_Variable::sync($totals_state['parent']);
    $coupon_code = 'h7-1-core-' . substr($run, 0, 12);
    $totals_coupon = new WC_Coupon(); $totals_coupon->set_code($coupon_code); $totals_coupon->set_discount_type('percent'); $totals_coupon->set_amount(10); $totals_coupon->set_product_ids(array_values($totals_state)); $totals_coupon->update_meta_data('_trama_h7_1_core', $run); $totals_coupon->save();
    foreach (['simple_flat' => [[$totals_state['simple'], 1], $flat, false, 54500.0], 'simple_pickup' => [[$totals_state['simple'], 1], 'pickup_location:0', false, 50000.0], 'variation_flat' => [[$totals_state['variable'], 1], $flat, false, 56500.0], 'exact_free' => [[$totals_state['simple'], 2], $free, false, 100000.0], 'coupon_removes_free' => [[$totals_state['simple'], 2], $flat, true, 94500.0]] as $name => $spec) {
        $scenarios[$name] = $calculate([$spec[0]], $spec[1], $spec[2]);
        $assert(abs($scenarios[$name]['total'] - $spec[3]) < 0.001, $name . '_native_total');
    }
    $scenarios['simple_and_variation'] = $calculate([[$totals_state['simple'], 1], [$totals_state['variable'], 1]], $free);
    $assert(102000.0 === $scenarios['simple_and_variation']['total'], 'simple_variation_free_total');
    $scenarios['simple_variation_coupon'] = $calculate([[$totals_state['simple'], 1], [$totals_state['variable'], 1]], $flat, true);
    $assert(96300.0 === $scenarios['simple_variation_coupon']['total'] && !isset($scenarios['simple_variation_coupon']['rates'][$free]), 'discount_before_free_threshold');
    $variation = wc_get_product($totals_state['variable']); $parent = wc_get_product($totals_state['parent']);
    $assert('' === $variation->get_weight('edit') && $variation->get_weight() === $parent->get_weight(), 'variation_inherits_native_physical_data');
    $stage = 'inventory';
    $probe = new WC_Product_Simple(); $probe->set_props(['name' => 'H7.1 internal stock simulation — no payment', 'sku' => 'H7-1-CORE-' . substr($run, 0, 12), 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => '1000', 'manage_stock' => true, 'stock_quantity' => 2, 'backorders' => 'no']); $probe->update_meta_data('_trama_h7_1_core', $run); $probe->save();
    $stock = static fn() => (int) wc_get_product($probe->get_id())->get_stock_quantity();
    $held = static fn() => (int) wc_get_held_stock_quantity(wc_get_product($probe->get_id()));
    $new_order = static function ($case, $qty = 1, $method = '') use (&$technical_orders, $run, $probe) {
        $order = wc_create_order(['status' => 'pending', 'customer_id' => 0, 'created_via' => 'h7-1-core-qa']);
        if (is_wp_error($order)) { throw new RuntimeException('Core QA order creation failed.'); }
        $technical_orders[] = $order->get_id(); $order->set_billing_email('h7-1-core@example.test'); $order->set_payment_method($method); $order->set_currency('ARS'); $order->update_meta_data('_trama_h7_1_core', $run); $order->update_meta_data('_trama_h7_1_case', $case); $order->add_product(wc_get_product($probe->get_id()), $qty); $order->calculate_totals(); $order->add_order_note('Internal WooCommerce state simulation. No Mercado Pago payment, notification, refund or movement of money.'); $order->save(); return $order;
    };
    $record = static function ($case, $order) use (&$inventory, $stock, $held) { $order = wc_get_order($order->get_id()); $inventory[] = ['case' => $case, 'stock' => $stock(), 'held' => $held(), 'order' => h7_1_order_summary($order)]; };
    $pending = $new_order('pending_abandonment'); wc_reserve_stock_for_order($pending); $record('pending_reservation', $pending);
    $assert(2 === $stock() && 1 === $held() && !$pending->is_paid(), 'pending_reserves_without_payment_or_physical_reduction');
    $pending->update_status('cancelled', 'Internal abandonment simulation.'); $record('pending_cancelled', $pending);
    $assert(2 === $stock() && 0 === $held(), 'pending_cancel_releases_reservation');
    $bacs = $new_order('native_bacs', 1, 'bacs');
    $gateway = WC()->payment_gateways()->payment_gateways()['bacs']; $response = $gateway->process_payment($bacs->get_id()); $bacs = wc_get_order($bacs->get_id()); $record('native_bacs_on_hold', $bacs);
    $assert('success' === ($response['result'] ?? '') && 'on-hold' === $bacs->get_status() && !$bacs->is_paid() && !$bacs->get_date_paid() && 1 === $stock(), 'bacs_native_on_hold_unpaid_reduces_once');
    $gateway->process_payment($bacs->get_id()); $record('bacs_process_repeat', $bacs); $assert(1 === $stock(), 'repeat_bacs_does_not_reduce_twice');
    $bacs->update_status('processing', 'Internal manual verification simulation; no money transferred.'); $record('bacs_manual_processing', $bacs); $assert(1 === $stock(), 'bacs_manual_processing_no_second_reduction');
    $bacs->update_status('cancelled', 'End of owned local BACS simulation.'); $record('bacs_cancelled', $bacs); $assert(2 === $stock(), 'cancel_reduced_order_restores_native_stock');
    $bacs->update_status('cancelled'); $assert(2 === $stock(), 'repeat_cancel_does_not_increase_twice');
    $approved = $new_order('core_approved_simulation'); wc_reserve_stock_for_order($approved); $approved->payment_complete('H7-1-CORE-NO-MONEY-' . $run); $record('core_payment_complete', $approved);
    $approved = wc_get_order($approved->get_id()); $assert($approved->is_paid() && 'processing' === $approved->get_status() && 1 === $stock() && 0 === $held(), 'core_paid_physical_processing_not_completed');
    $approved->payment_complete('H7-1-CORE-NO-MONEY-' . $run); $assert(1 === $stock(), 'repeat_core_payment_complete_idempotent_stock');
    $approved->update_status('cancelled', 'End of internal core simulation.'); $assert(2 === $stock(), 'core_cancellation_restores_stock');
    $rejected = $new_order('core_rejection_simulation'); wc_reserve_stock_for_order($rejected); $rejected->update_status('failed', 'Internal failed status; not a provider rejection.'); $record('pending_failed', $rejected); $assert(2 === $stock() && 0 === $held() && !$rejected->is_paid(), 'failed_pending_keeps_stock_unpaid');
    $hold_fail = $new_order('on_hold_to_failed'); $hold_fail->update_status('on-hold'); $hold_fail->update_status('failed'); $record('on_hold_failed', $hold_fail); $assert(2 === $stock(), 'failed_after_hold_restores_native_stock');
    $last = $new_order('last_units', 2); $last->update_status('on-hold'); $record('last_units_exhausted', $last); $assert(0 === $stock() && !wc_get_product($probe->get_id())->has_enough_stock(1), 'few_units_native_exhaustion');
    $over = $new_order('stock_exhausted_reject'); $error = null; try { wc_reserve_stock_for_order($over); } catch (\Automattic\WooCommerce\Checkout\Helpers\ReserveStockException $e) { $error = $e->getErrorCode(); }
    $assert('woocommerce_product_out_of_stock' === $error, 'native_reject_after_zero_stock'); $over->update_status('failed'); $last->update_status('cancelled'); $assert(2 === $stock(), 'last_units_cancel_restores_exactly_two');
    $stage = 'hpos';
    $queried = array_map('intval', wc_get_orders(['limit' => -1, 'return' => 'ids', 'meta_query' => [['key' => '_trama_h7_1_core', 'value' => $run]]])); sort($queried); $expected = $technical_orders; sort($expected);
    $assert($queried === $expected && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'hpos_crud_exact_owned_orders_and_metadata');
    $stage = 'confirmation_copy';
    $original_get = $_GET;
    try {
        foreach (['bacs', 'woo-mercado-pago-basic'] as $method) {
            $mock = new WC_Order(); $mock->set_payment_method($method); $mock->set_status('pending');
            $text_before = apply_filters('woocommerce_thankyou_order_received_text', 'NATIVE', $mock);
            $_GET['status'] = 'approved'; $_GET['collection_status'] = 'approved'; $_GET['payment_id'] = '123';
            $text_after = apply_filters('woocommerce_thankyou_order_received_text', 'NATIVE', $mock);
            $assert($text_before === $text_after && str_contains(mb_strtolower($text_after), 'pendiente') && !str_contains(mb_strtolower($text_after), 'pago confirmado'), $method . '_pending_copy_ignores_url_claims');
        }
        foreach (['failed', 'cancelled', 'refunded', 'completed'] as $status) { $mock = new WC_Order(); $mock->set_status($status); $assert('NATIVE' === apply_filters('woocommerce_thankyou_order_received_text', 'NATIVE', $mock), $status . '_native_confirmation_preserved'); }
        $mock = new WC_Order(); $mock->set_status('processing'); $mock->set_date_paid(time()); $assert(str_contains(mb_strtolower(apply_filters('woocommerce_thankyou_order_received_text', 'NATIVE', $mock)), 'confirmamos'), 'paid_processing_confirmation');
    } finally { $_GET = $original_get; }
    $assert($sensitive_before === h7_1_sensitive_snapshot(), 'no_credentials_or_bank_settings_mutated');
} catch (Throwable $exception) {
    $failed = true; $failure = ['stage' => $stage, 'type' => get_class($exception)];
    // Messages may embed private values in third-party exceptions. Withheld.
} finally {
    if ($probe) {
        foreach ($technical_orders as $id) {
            $order = wc_get_order($id);
            if ($order && $order->get_meta('_trama_h7_1_core') === $run) {
                if ($order->has_status(['pending', 'on-hold', 'processing'])) { $order->update_status('cancelled', 'Owned local QA cleanup; no money moved.'); }
                wc_release_stock_for_order($order);
            }
        }
        // Never set stock to manufacture a passing result. Cancellation hooks
        // must restore the original inventory; otherwise preserve evidence.
        $checks['native_cleanup_restored_stock'] = 2 === (int) wc_get_product($probe->get_id())->get_stock_quantity() && 0 === (int) wc_get_held_stock_quantity(wc_get_product($probe->get_id()));
        $probe = wc_get_product($probe->get_id()); $probe->set_status('draft'); $probe->save();
    }
    $cart->empty_cart(false); WC()->cart = $old_cart; WC()->customer = $old_customer; WC()->session = $old_session;
    if ($old_cart instanceof WC_Cart) { WC_Cart_Session::set_updates_enabled_for_cart($old_cart, $old_updates); }
    if ($totals_coupon && $run === $totals_coupon->get_meta('_trama_h7_1_core')) { $totals_coupon->delete(true); }
    foreach (array_reverse($totals_products) as $id) {
        $product = wc_get_product($id);
        if ($product && $run === $product->get_meta('_trama_h7_1_core')) { $product->delete(true); }
    }
    remove_filter('pre_http_request', $http_guard, PHP_INT_MAX);
}
h7_1_emit(['result' => $failed || in_array(false, $checks, true) ? 'failed' : 'passed', 'checks' => $checks, 'failure' => $failure ?? null, 'scenarios' => $scenarios, 'inventory' => $inventory, 'technical_order_ids' => $technical_orders, 'internal_simulation_only' => true, 'mercado_pago_external_test_executed' => false, 'payment_provider_calls' => 0, 'blocked_ancillary_http_requests' => $blocked_http, 'cleanup' => 'Only this run owned orders cancelled natively; probe hidden as draft. No stock assignment or refund.', 'notes' => 'State simulations validate WooCommerce core only; they do not validate Mercado Pago approved/pending/rejected notifications or authentic callbacks.']);
