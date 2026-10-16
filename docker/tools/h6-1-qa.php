<?php
/** Disposable local QA fixtures. No gateway, no real buyer, no stock writes. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
$mode = $args[0] ?? 'verify';
$code = 'h6-1-qa-10'; $email = 'h6-1-qa@example.test';
$coupon_id = wc_get_coupon_id_by_code($code);
if ('prepare' === $mode) {
    if ($coupon_id && get_post_meta($coupon_id, '_trama_h6_1_qa', true) !== '1') { WP_CLI::error('Coupon exists and is not owned by this test.'); }
    if (!$coupon_id) {
        $coupon = new WC_Coupon(); $coupon->set_code($code); $coupon->set_discount_type('percent'); $coupon->set_amount(10); $coupon->set_description('Temporary local H6.1 automated QA; removed in finally.'); $coupon->set_individual_use(true); $coupon->update_meta_data('_trama_h6_1_qa', '1'); $coupon->save();
    }
    echo wp_json_encode(['result' => 'passed', 'temporary_coupon' => $code]) . "\n"; return;
}
if ('cleanup' === $mode) {
    $deleted = [];
    foreach (wc_get_orders(['billing_email' => $email, 'limit' => -1]) as $order) {
        if ('checkout-draft' !== $order->get_status() || 'store-api' !== $order->get_created_via() || 'Prueba' !== $order->get_shipping_first_name() || 'H6.1' !== $order->get_shipping_last_name() || $order->get_payment_method() || $order->get_transaction_id() || $order->get_meta('_order_stock_reduced')) { WP_CLI::error('Unexpected QA order state; preserving it.'); }
        $deleted[] = $order->get_id(); $order->delete(true);
    }
    if ($coupon_id) {
        $coupon = new WC_Coupon($coupon_id);
        if ('1' !== $coupon->get_meta('_trama_h6_1_qa') || $coupon->get_discount_type() !== 'percent' || (float) $coupon->get_amount() !== 10.0 || $coupon->get_usage_count()) { WP_CLI::error('QA coupon changed; preserving it.'); }
        $coupon->delete(true);
    }
    echo wp_json_encode(['result' => 'passed', 'owned_unpaid_drafts_deleted' => $deleted, 'temporary_coupon_removed' => !wc_get_coupon_id_by_code($code), 'remaining_drafts' => count(wc_get_orders(['billing_email' => $email, 'limit' => -1, 'return' => 'ids']))]) . "\n"; return;
}
if ('verify' !== $mode) { WP_CLI::error('Use prepare|verify|cleanup.'); }
$checks = [];
$check = static function ($value, $name) use (&$checks) { $checks[$name] = (bool) $value; };
$simple = wc_get_product(wc_get_product_id_by_sku('TRM-ACC-001'));
$variation = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001-NAT-S'));
$exhausted = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001-NAT-XL'));
$check($simple->is_purchasable() && $simple->is_in_stock() && $simple->has_enough_stock(1), 'simple_available');
$check($variation->is_purchasable() && $variation->is_in_stock() && $variation->has_enough_stock(1), 'variation_available');
$check(!$exhausted->is_in_stock() && !$exhausted->has_enough_stock(1), 'exhausted_variation_rejected');
$check(!$simple->has_enough_stock($simple->get_stock_quantity() + 1), 'above_stock_rejected');
$old_cart = WC()->cart;
if (!WC()->session) { WC()->initialize_session(); }
if (!WC()->customer) { WC()->customer = new WC_Customer(0); }
$cart = new WC_Cart(); WC()->cart = $cart;
try {
    $key = $cart->add_to_cart($simple->get_id(), 1);
    $check((bool) $key && true === $cart->check_cart_item_stock(), 'native_available_cart');
    // Only the item object in this isolated CLI cart changes. Nothing is saved.
    $item = clone $cart->cart_contents[$key]['data']; $item->set_stock_quantity(1); $cart->cart_contents[$key]['data'] = $item;
    $check(true === $cart->check_cart_item_stock(), 'last_unit_cart_valid');
    $cart->cart_contents[$key]['quantity'] = 2;
    $check(is_wp_error($cart->check_cart_item_stock()), 'last_unit_quantity_two_rejected');
    $cart->cart_contents[$key]['quantity'] = 1; $item->set_stock_quantity(0); $item->set_stock_status('outofstock');
    $stock_error = $cart->check_cart_item_stock();
    $check(is_wp_error($stock_error), 'became_out_of_stock_in_cart_rejected');
    $check(is_wp_error($stock_error) && !preg_match('/Sorry|stock available|not enough/i', $stock_error->get_error_message()), 'stock_error_spanish');
} finally {
    $cart->empty_cart(false); WC()->cart = $old_cart;
}
$check(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'hpos');
$check('yes' === get_option('woocommerce_enable_guest_checkout'), 'guest');
$check('yes' === get_option('woocommerce_enable_signup_and_login_from_checkout'), 'account_optional');
$check(count(WC()->payment_gateways()->get_available_payment_gateways()) === 0, 'no_new_gateway');
echo wp_json_encode(['result' => !in_array(false, $checks, true) ? 'passed' : 'failed', 'checks' => $checks, 'stock_simulation' => 'Isolated cart item clone, no product saves or stock changes.', 'stock_error' => is_wp_error($stock_error ?? null) ? $stock_error->get_error_message() : null], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if (in_array(false, $checks, true)) { exit(1); }
