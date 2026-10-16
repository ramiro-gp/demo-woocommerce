<?php
/** Synthetic local WooCommerce order smoke; no gateway invocation or real buyer. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8080') { exit(1); }
$assert = static function ($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
$simple = wc_get_product(wc_get_product_id_by_sku('TRM-ACC-001'));
$variation = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001-NAT-S'));
$exhausted = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001-NAT-XL'));
$assert($simple && $variation && $exhausted, 'H5 fixtures required.');
$stock = [$simple->get_id() => $simple->get_stock_quantity(), $variation->get_id() => $variation->get_stock_quantity()];
$order = null;
$reduced = false;
try {
    $assert(!$exhausted->is_in_stock() && !$exhausted->has_enough_stock(1), 'Exhausted variation must reject stock.');
    $order = wc_create_order(['status' => 'pending', 'customer_id' => 0, 'created_via' => 'trama-h5-smoke']);
    $assert(!is_wp_error($order), 'Synthetic order creation failed.');
    $order->update_meta_data('_trama_h5_smoke', 1);
    $order->set_currency('ARS');
    $order->add_product($simple, 1);
    $order->add_product($variation, 1);
    $order->calculate_totals();
    $order->save();
    $id = $order->get_id();
    $read = wc_get_order($id);
    $assert($read && count($read->get_items()) === 2 && (float) $read->get_total() === 51000.0 && $read->get_customer_id() === 0, 'Persisted guest order totals differ.');
    $assert(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'HPOS must remain enabled.');
    wc_reduce_stock_levels($id);
    $reduced = true;
    foreach ($stock as $product_id => $quantity) { $assert(wc_get_product($product_id)->get_stock_quantity() === $quantity - 1, 'Native stock reduction failed.'); }
    wc_reduce_stock_levels($id);
    foreach ($stock as $product_id => $quantity) { $assert(wc_get_product($product_id)->get_stock_quantity() === $quantity - 1, 'Stock was reduced twice.'); }
    wc_increase_stock_levels($id);
    $reduced = false;
    foreach ($stock as $product_id => $quantity) { $assert(wc_get_product($product_id)->get_stock_quantity() === $quantity, 'Stock restoration failed.'); }
    echo wp_json_encode(['result' => 'passed', 'synthetic_order' => $id, 'guest' => true, 'hpos' => true, 'items' => 2, 'total' => 51000, 'currency' => 'ARS', 'stock_reduction_once' => true, 'stock_restored' => true, 'payment_submitted' => false, 'cleanup' => 'Synthetic order deleted in finally'], JSON_PRETTY_PRINT) . "\n";
} finally {
    if ($order && !is_wp_error($order)) {
        if ($reduced) { wc_increase_stock_levels($order->get_id()); }
        if ($order->get_meta('_trama_h5_smoke') === 1) { $order->delete(true); }
    }
}
