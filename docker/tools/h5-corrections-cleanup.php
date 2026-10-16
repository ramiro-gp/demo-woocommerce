<?php
/** Delete only unpaid checkout drafts created by this local browser QA. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8080') { exit(1); }
$deleted = [];
foreach (wc_get_orders(['billing_email' => 'h5-corrections@example.test', 'limit' => -1]) as $order) {
    if ($order->get_status() !== 'checkout-draft' || $order->get_created_via() !== 'store-api' || $order->get_shipping_first_name() !== 'Prueba' || $order->get_shipping_last_name() !== 'H5' || $order->get_payment_method() !== '' || $order->get_transaction_id() !== '' || $order->get_meta('_order_stock_reduced')) {
        throw new RuntimeException('Unexpected state: preserving the QA order for inspection.');
    }
    $deleted[] = $order->get_id();
    $order->delete(true);
}
echo wp_json_encode(['result' => 'passed', 'owned_unpaid_drafts_deleted' => $deleted, 'remaining' => count(wc_get_orders(['billing_email' => 'h5-corrections@example.test', 'limit' => -1, 'return' => 'ids']))]) . "\n";
