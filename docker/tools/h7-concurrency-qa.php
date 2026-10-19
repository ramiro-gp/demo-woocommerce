<?php
/** Local, fictional Store API fixtures. Never a payment gateway or receiver. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
$action = $args[0] ?? '';
$option = 'trama_h71_concurrency_qa';
$state = (array) get_option($option, []);
$emit = static function ($data) { echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"; };
$owned = static function ($order) use (&$state) {
    return $order instanceof WC_Order && $order->get_meta('_h71_concurrency_qa') === ($state['run'] ?? null);
};
$snapshot = static function ($order) {
    $data = $order->get_data();
    unset($data['date_modified']);
    $data['meta_data'] = array_map(static fn($meta) => $meta->get_data(), $order->get_meta_data());
    $data['items'] = array_map(static fn($item) => $item->get_data(), $order->get_items());
    $data['notes'] = array_map(static fn($note) => [$note->id, $note->content], wc_get_order_notes(['order_id' => $order->get_id()]));
    $data['stock_reduced'] = $order->get_data_store()->get_stock_reduced($order->get_id());
    return $data;
};
if ('prepare' === $action) {
    if (!$state) {
        foreach (['H71-CONCURRENCY-A', 'H71-CONCURRENCY-B'] as $sku) {
            if (wc_get_product_id_by_sku($sku)) { WP_CLI::error('Fixture collision; preserve existing data.'); }
        }
        $state = ['run' => wp_generate_uuid4(), 'products' => [], 'orders' => []];
        foreach (['A' => '50000', 'B' => '52000'] as $label => $price) {
            $product = new WC_Product_Simple();
            $product->set_props(['name' => 'H7.1 concurrencia ' . $label, 'sku' => 'H71-CONCURRENCY-' . $label, 'slug' => 'h71-concurrency-' . strtolower($label), 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => $price, 'manage_stock' => true, 'stock_quantity' => 40, 'weight' => '0.2', 'length' => '24', 'width' => '18', 'height' => '3']);
            $product->update_meta_data('_h71_concurrency_qa', $state['run']);
            $state['products'][$label] = $product->save();
        }
        update_option($option, $state, false);
    }
    foreach ($state['products'] as $id) {
        if (wc_get_product($id)->get_meta('_h71_concurrency_qa') !== $state['run']) { WP_CLI::error('Fixture ownership changed.'); }
    }
    $emit(['result' => 'passed', 'products' => $state['products'], 'stock_reset' => false]);
    return;
}
if (in_array($action, ['session', 'hydration'], true)) {
    if (!$state) { WP_CLI::error('Prepare fixtures first.'); }
    // Random guest session, independent of all existing browser sessions.
    wc_load_cart();
    $address = ['first_name' => 'Prueba', 'last_name' => 'Concurrencia', 'address_1' => 'Calle Ficticia 123', 'city' => 'Buenos Aires', 'state' => 'C', 'postcode' => '1001', 'country' => 'AR'];
    foreach ($address as $field => $value) {
        WC()->customer->{'set_billing_' . $field}($value);
        WC()->customer->{'set_shipping_' . $field}($value);
    }
    WC()->customer->set_billing_email('h71.concurrency@example.test');
    WC()->customer->set_billing_phone('1123456789');
    WC()->customer->save();
    WC()->cart->add_to_cart($state['products']['A'], 1);
    WC()->cart->calculate_totals();
    if ('fresh' === ($args[1] ?? '')) {
        WC()->session->save_data();
        $emit(['order_id' => 0, 'cart_token' => Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils::get_cart_token((string) WC()->session->get_customer_id()), 'products' => $state['products']]);
        return;
    }
    $controller = new Automattic\WooCommerce\StoreApi\Utilities\OrderController();
    $order = $controller->create_order_from_cart();
    $order->set_status(in_array($args[1] ?? '', ['failed', 'checkout-draft'], true) ? $args[1] : 'pending');
    $order->set_payment_method('woo-mercado-pago-basic');
    $order->set_payment_method_title('Mercado Pago');
    $order->set_transaction_id('H71-INTERNAL-NOT-A-PAYMENT');
    $order->update_meta_data('_h71_concurrency_qa', $state['run']);
    $order->update_meta_data('_h71_sentinel', 'internal-association-' . $order->get_id());
    $order->save();
    $order = wc_get_order($order->get_id());
    WC()->session->set('store_api_draft_order', $order->get_id());
    WC()->session->set('order_awaiting_payment', $order->get_id());
    // Reproduce session selection differing from the already placed order.
    WC()->session->set('chosen_payment_method', 'bacs');
    WC()->session->save_data();
    $state['orders'][] = $order->get_id();
    $state['before'][$order->get_id()] = $snapshot($order);
    update_option($option, $state, false);
    if ('hydration' === $action) {
        $hydration = Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Domain\Services\Hydration::class);
        $hydration->get_rest_api_response_data('/wc/store/v1/cart');
        $response = $hydration->get_rest_api_response_data('/wc/store/v1/checkout');
        $unchanged = wp_json_encode($state['before'][$order->get_id()]) === wp_json_encode($snapshot(wc_get_order($order->get_id())));
        $passed = $unchanged && 0 === ($response['body']['order_id'] ?? -1) && !WC()->session->get('store_api_draft_order');
        $emit(['result' => $passed ? 'passed' : 'failed', 'id' => $order->get_id(), 'native_hydration_order_id' => $response['body']['order_id'] ?? null, 'full_order_unchanged' => $unchanged]);
        if (!$passed) { exit(1); }
        return;
    }
    $token = Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils::get_cart_token((string) WC()->session->get_customer_id());
    // Caller MUST capture privately; Cart-Token and order keys are credentials.
    $emit(['order_id' => $order->get_id(), 'order_key' => $order->get_order_key(), 'email' => $order->get_billing_email(), 'cart_token' => $token, 'products' => $state['products']]);
    return;
}
if ('adopt' === $action) {
    $order = wc_get_order(absint($args[1] ?? 0));
    if (!$order instanceof WC_Order || $order->get_created_via() !== 'store-api' || !in_array($order->get_billing_email(), ['h71.concurrency@example.test', 'h71.browser.concurrency@example.test'], true) || $order->get_payment_method() !== 'bacs' || !$order->has_status('on-hold') || $order->get_transaction_id() || !$order->get_items()) { WP_CLI::error('Not a new native BACS QA order.'); }
    foreach ($order->get_items() as $item) {
        if (!in_array($item->get_product_id(), $state['products'], true)) { WP_CLI::error('Foreign product; preserve order.'); }
    }
    if (!in_array($order->get_id(), $state['orders'], true)) {
        $order->update_meta_data('_h71_concurrency_qa', $state['run']);
        $order->save();
        $state['orders'][] = $order->get_id();
        $state['before'][$order->get_id()] = $snapshot(wc_get_order($order->get_id()));
        update_option($option, $state, false);
    }
    $emit(['result' => 'passed', 'id' => $order->get_id(), 'status' => $order->get_status(), 'method' => $order->get_payment_method(), 'total' => $order->get_total(), 'stock_reduced' => (bool) $order->get_data_store()->get_stock_reduced($order->get_id()), 'paid' => $order->is_paid()]);
    return;
}
if ('adopt-pro' === $action) {
    $order = wc_get_order(absint($args[1] ?? 0));
    if (!$order instanceof WC_Order || $order->get_created_via() !== 'store-api' || !in_array($order->get_billing_email(), ['h71.concurrency@example.test', 'h71.browser.concurrency@example.test'], true) || $order->get_payment_method() !== 'woo-mercado-pago-basic' || !$order->has_status('pending') || $order->get_transaction_id() || !$order->get_items()) { WP_CLI::error('Not a new unpaid native Pro QA order.'); }
    foreach ($order->get_items() as $item) { if (!in_array($item->get_product_id(), $state['products'], true)) { WP_CLI::error('Foreign product; preserve order.'); } }
    if (!in_array($order->get_id(), $state['orders'], true)) {
        $order->update_meta_data('_h71_concurrency_qa', $state['run']); $order->save();
        $state['orders'][] = $order->get_id();
        $state['before'][$order->get_id()] = $snapshot(wc_get_order($order->get_id()));
        update_option($option, $state, false);
    }
    $emit(['result' => 'passed', 'id' => $order->get_id(), 'method' => $order->get_payment_method(), 'status' => $order->get_status(), 'total' => $order->get_total()]);
    return;
}
if ('checkpoint-bacs' === $action) {
    $order = wc_get_order(absint($args[1] ?? 0));
    if (!$owned($order) || $order->get_payment_method() !== 'bacs' || !$order->has_status('on-hold')) { WP_CLI::error('Not an owned legitimate BACS transition.'); }
    $state['legitimate_before'][$order->get_id()] = $state['before'][$order->get_id()];
    $state['before'][$order->get_id()] = $snapshot($order);
    update_option($option, $state, false);
    $emit(['result' => 'passed', 'id' => $order->get_id(), 'method' => $order->get_payment_method(), 'status' => $order->get_status(), 'stock_reduced' => (bool) $order->get_data_store()->get_stock_reduced($order->get_id())]);
    return;
}
if ('checkpoint-pro-retry' === $action) {
    $id = absint($args[1] ?? 0); $order = wc_get_order($id);
    if (!$owned($order) || !$order->has_status('pending') || $order->get_payment_method() !== 'woo-mercado-pago-basic') { WP_CLI::error('Not an owned native Pro retry.'); }
    $before = $state['before'][$id]; $after = $snapshot($order);
    $old_meta = []; foreach ($before['meta_data'] as $meta) { $old_meta[$meta['key']] = $meta['value']; }
    $new_meta = []; foreach ($after['meta_data'] as $meta) { $new_meta[$meta['key']] = $meta['value']; }
    // WC creates a new diagnostic source per POST; it is not payment metadata.
    unset($old_meta['_debug_log_source_pending_deletion'], $new_meta['_debug_log_source_pending_deletion']);
    if (wp_json_encode($old_meta) !== wp_json_encode($new_meta)) { WP_CLI::error('Unexpected payment metadata mutation on retry.'); }
    $before_compare = $before; $after_compare = $after;
    unset($before_compare['meta_data'], $after_compare['meta_data'], $before_compare['notes'], $after_compare['notes']);
    if (wp_json_encode($before_compare) !== wp_json_encode($after_compare)) { WP_CLI::error('Unexpected order mutation on retry.'); }
    $state['legitimate_before'][$id] = $before; $state['before'][$id] = $after;
    update_option($option, $state, false);
    $emit(['result' => 'passed', 'id' => $id, 'order_and_payment_fields_preserved' => true, 'native_diagnostic_source_and_note_may_change' => true]);
    return;
}
if ('assert' === $action) {
    $id = absint($args[1] ?? 0);
    $order = wc_get_order($id);
    if (!$owned($order) || !isset($state['before'][$id])) { WP_CLI::error('Unknown owned snapshot.'); }
    $before = $state['before'][$id]; $after = $snapshot($order); $changed = [];
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
        if (wp_json_encode($before[$key] ?? null) !== wp_json_encode($after[$key] ?? null)) { $changed[] = $key; }
    }
    $old_meta = []; foreach ($before['meta_data'] as $meta) { $old_meta[$meta['key']] = $meta['value']; }
    $new_meta = []; foreach ($after['meta_data'] as $meta) { $new_meta[$meta['key']] = $meta['value']; }
    $changed_meta = [];
    foreach (array_unique(array_merge(array_keys($old_meta), array_keys($new_meta))) as $key) {
        if (wp_json_encode($old_meta[$key] ?? null) !== wp_json_encode($new_meta[$key] ?? null)) { $changed_meta[] = $key; }
    }
    $raw_changed = $changed;
    // WC's asynchronous step-log cleanup removes this diagnostic pointer. It is
    // not payment metadata; keep its raw diff visible instead of failing order
    // integrity because the log rotation happened between requests.
    $diagnostic_only = ['meta_data'] === $changed && ['_debug_log_source_pending_deletion'] === $changed_meta;
    if ($diagnostic_only) { $changed = []; }
    $emit(['result' => $changed ? 'failed' : 'passed', 'id' => $id, 'changed_fields' => $changed, 'raw_changed_fields' => $raw_changed, 'changed_meta_keys' => $changed_meta, 'woocommerce_diagnostic_pointer_only' => $diagnostic_only, 'method' => $order->get_payment_method(), 'status' => $order->get_status(), 'total' => $order->get_total()]);
    if ($changed) { exit(1); }
    return;
}
if ('census' === $action) {
    $ids = wc_get_orders(['type' => 'shop_order', 'billing_email' => 'h71.concurrency@example.test', 'limit' => -1, 'return' => 'ids', 'status' => array_keys(wc_get_order_statuses())]);
    $emit(['count' => count($ids)]);
    return;
}
if ('stock' === $action) {
    $emit(array_map(static fn($id) => wc_get_product($id)->get_stock_quantity(), $state['products'])); return;
}
if (in_array($action, ['history-snapshot', 'history-assert'], true)) {
    $external = (array) get_option('trama_h7_mp_session', []);
    if (empty($external['products'])) { WP_CLI::error('External fixture ownership state missing.'); }
    $hashes = []; $snapshots = [];
    foreach (wc_get_orders(['type' => 'shop_order', 'limit' => -1]) as $order) {
        $items = $order->get_items(); $matches = (bool) $items;
        foreach ($items as $item) { if (!in_array($item->get_variation_id() ?: $item->get_product_id(), array_values($external['products']), true)) { $matches = false; } }
        if ($matches) { $snapshots[$order->get_id()] = $snapshot($order); $hashes[$order->get_id()] = hash('sha256', wp_json_encode($snapshots[$order->get_id()])); }
    }
    ksort($hashes);
    $stock = array_map(static fn($id) => wc_get_product($id)->get_stock_quantity(), $external['products']);
    $current = ['orders' => $hashes, 'stock' => $stock];
    if ('history-snapshot' === $action) { $state['external_before_reconstructed'] = $current; $state['external_snapshots'] = $snapshots; update_option($option, $state, false); }
    $passed = 'history-snapshot' === $action || $current === ($state['external_before_reconstructed'] ?? null);
    $changed = [];
    foreach ($hashes as $id => $hash) {
        if ($hash !== ($state['external_before_reconstructed']['orders'][$id] ?? null)) {
            $fields = [];
            if (isset($state['external_snapshots'][$id])) { foreach ($snapshots[$id] as $key => $value) { if (wp_json_encode($value) !== wp_json_encode($state['external_snapshots'][$id][$key] ?? null)) { $fields[] = $key; } } }
            $changed[$id] = ['fields' => $fields];
            if (in_array('meta_data', $fields, true)) {
                $old_meta = []; foreach ($state['external_snapshots'][$id]['meta_data'] as $meta) { $old_meta[$meta['key']] = $meta['value']; }
                $changed_meta = [];
                $new_meta = []; foreach ($snapshots[$id]['meta_data'] as $meta) { $new_meta[$meta['key']] = $meta['value']; }
                foreach (array_unique(array_merge(array_keys($old_meta), array_keys($new_meta))) as $key) {
                    if (wp_json_encode($new_meta[$key] ?? null) !== wp_json_encode($old_meta[$key] ?? null)) {
                        $changed_meta[$key] = ['date_only' => is_string($new_meta[$key] ?? null) && is_string($old_meta[$key] ?? null) && preg_replace('/^\[Date [^\]]+\]/', '[Date]', $new_meta[$key]) === preg_replace('/^\[Date [^\]]+\]/', '[Date]', $old_meta[$key])];
                    }
                }
                $changed[$id]['metadata'] = $changed_meta;
            }
        }
    }
    $emit(['result' => $passed ? 'passed' : 'failed', 'historical_external_orders' => count($hashes), 'changed_orders_and_fields' => $changed, 'full_snapshots_and_physical_stock_preserved' => $passed, 'physical_stock_preserved' => $stock === ($state['external_before_reconstructed']['stock'] ?? null)]);
    if (!$passed) { exit(1); } return;
}
if ('browser-orders' === $action) {
    $rows = [];
    foreach (wc_get_orders(['type' => 'shop_order', 'billing_email' => 'h71.browser.concurrency@example.test', 'limit' => -1]) as $order) {
        $rows[] = ['id' => $order->get_id(), 'method' => $order->get_payment_method(), 'status' => $order->get_status(), 'total' => $order->get_total()];
    }
    $emit(['orders' => $rows]); return;
}
if ('inspect' === $action) {
    $rows = [];
    foreach ($state['orders'] ?? [] as $id) {
        $order = wc_get_order($id);
        if (!$owned($order)) { WP_CLI::error('Owned order missing or changed.'); }
        $before = $state['before'][$id];
        $after = $snapshot($order);
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (wp_json_encode($before[$key] ?? null) !== wp_json_encode($after[$key] ?? null)) { $changed[] = $key; }
        }
        $rows[] = ['id' => $id, 'method' => $order->get_payment_method(), 'status' => $order->get_status(), 'changed_fields' => $changed, 'total' => $order->get_total(), 'transaction_preserved' => $order->get_transaction_id() === $before['transaction_id'], 'items_preserved' => wp_json_encode($before['items']) === wp_json_encode($after['items']), 'metadata_preserved' => wp_json_encode($before['meta_data']) === wp_json_encode($after['meta_data'])];
    }
    $emit(['result' => 'passed', 'orders' => $rows, 'stock' => array_map(static fn($id) => wc_get_product($id)->get_stock_quantity(), $state['products'])]);
    return;
}
WP_CLI::error('Use prepare|session [fresh|pending|failed|checkout-draft]|hydration|inspect|assert ID|adopt ID|adopt-pro ID|checkpoint-bacs ID|checkpoint-pro-retry ID|census|stock|browser-orders|history-snapshot|history-assert.');
