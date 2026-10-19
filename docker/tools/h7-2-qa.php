<?php
/**
 * Local H7.2 native order/email QA. Every run owns new synthetic data.
 * No provider API, historical-order mutation, deletion or direct stock repair.
 */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
require_once __DIR__ . '/h7-1-functions.php';
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['verify', 'links'], true)) { WP_CLI::error('Use verify|links [owned run].'); }
if ('links' === $mode) {
    $run = $args[1] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $run)) { WP_CLI::error('Use a valid owned QA run.'); }
    $links = [];
    foreach (wc_get_orders(['limit' => -1, 'meta_query' => [['key' => '_commerce_h72_qa_run', 'value' => $run]]]) as $order) {
        if ('h72-native-qa' !== $order->get_created_via() || !str_ends_with($order->get_billing_email(), '@example.test')) { WP_CLI::error('Owned synthetic order required.'); }
        $links[$order->get_meta('_commerce_h72_qa_case')] = ['id' => $order->get_id(), 'status' => $order->get_status(), 'received' => $order->get_checkout_order_received_url(), 'admin' => admin_url('admin.php?page=wc-orders&action=edit&id=' . $order->get_id())];
    }
    // Native order keys are emitted only on explicit request, for private storage.
    echo wp_json_encode(['run' => $run, 'private_review_links' => $links], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

$run = str_replace('-', '', wp_generate_uuid4());
$short = substr($run, 0, 10);
$checks = []; $steps = []; $events = []; $orders = []; $products = []; $refunds = [];
$stage = 'preflight'; $blocked_http = 0; $blocked_mail = 0; $smtp_checks = 0; $failure = null;
$check = static function ($name, $condition) use (&$checks) { $checks[$name] = (bool) $condition; if (!$condition) { throw new RuntimeException($name); } };
$emit = static function ($report) { echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"; };
$hash = static fn($data) => hash('sha256', wp_json_encode($data));
$prior_order_ids = wc_get_orders(['limit' => -1, 'return' => 'ids', 'type' => 'shop_order']);
$prior_product_ids = get_posts(['post_type' => ['product', 'product_variation', 'shop_coupon'], 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1]);
$history = static function () use ($prior_order_ids, $hash) {
    $out = [];
    foreach ($prior_order_ids as $id) {
        $order = wc_get_order($id);
        if (!$order) { $out[$id] = 'missing'; continue; }
        $data = $order->get_data();
        // WooCommerce asynchronously removes only this diagnostic field. It is
        // not financial state and is excluded on both sides of this comparison.
        $data['meta_data'] = array_values(array_filter(array_map(static fn($meta) => $meta->get_data(), $order->get_meta_data()), static fn($meta) => '_debug_log_source_pending_deletion' !== $meta['key']));
        $data['items'] = array_map(static fn($item) => $item->get_data(), $order->get_items(['line_item', 'shipping', 'fee', 'coupon', 'tax']));
        $data['refunds'] = array_map(static fn($refund) => $refund->get_data(), $order->get_refunds());
        $out[$id] = $hash($data);
    }
    return $out;
};
$catalog = static function () use ($prior_product_ids, $hash) {
    $out = [];
    foreach ($prior_product_ids as $id) {
        $object = 'shop_coupon' === get_post_type($id) ? new WC_Coupon($id) : wc_get_product($id);
        $out[$id] = $object ? $hash($object->get_data()) : 'missing';
    }
    return $out;
};
$history_before = $history(); $catalog_before = $catalog(); $sensitive_before = h7_1_sensitive_snapshot();
$old_user = get_current_user_id();

// WP HTTP hooks are blocked; SMTP to the internal Mailpit service remains live.
add_filter('pre_http_request', static function () use (&$blocked_http) { ++$blocked_http; return new WP_Error('h72_qa_offline', 'External HTTP disabled during local H7.2 QA.'); }, PHP_INT_MAX, 3);
add_filter('woocommerce_email_headers', static function ($headers, $id, $object) use ($run, &$stage) {
    if ($object instanceof WC_Order && $run === $object->get_meta('_commerce_h72_qa_run')) {
        $headers .= "X-Commerce-QA-Run: $run\r\nX-Commerce-QA-Stage: $stage\r\nX-Commerce-QA-Email: $id\r\nX-Commerce-QA-Order: " . $object->get_id() . "\r\n";
    }
    return $headers;
}, PHP_INT_MAX, 3);
add_filter('pre_wp_mail', static function ($result, $attributes) use ($run, &$blocked_mail) {
    $headers = is_array($attributes['headers'] ?? '') ? implode("\n", $attributes['headers']) : (string) ($attributes['headers'] ?? '');
    $recipients = is_array($attributes['to']) ? $attributes['to'] : explode(',', $attributes['to']);
    foreach (preg_split('/\r?\n/', $headers) as $line) {
        if (preg_match('/^(?:Cc|Bcc):\s*(.+)$/i', $line, $match)) { $recipients = array_merge($recipients, explode(',', $match[1])); }
    }
    $safe = str_contains($headers, 'X-Commerce-QA-Run: ' . $run) && (bool) $recipients;
    foreach ($recipients as $recipient) {
        if (preg_match('/<([^<>]+)>/', $recipient, $match)) { $recipient = $match[1]; }
        $recipient = trim($recipient);
        if (!is_email($recipient) || !str_ends_with(strtolower($recipient), '@example.test')) { $safe = false; }
    }
    if (!$safe) { ++$blocked_mail; return false; }
    return $result;
}, PHP_INT_MAX, 2);
add_action('phpmailer_init', static function ($mailer) use (&$smtp_checks) {
    if ('smtp' !== $mailer->Mailer || 'mailpit' !== $mailer->Host || 1025 !== (int) $mailer->Port || $mailer->SMTPAuth || $mailer->SMTPSecure) { throw new RuntimeException('QA requires internal Mailpit SMTP only.'); }
    ++$smtp_checks;
}, PHP_INT_MAX);
add_action('woocommerce_email_sent', static function ($sent, $id, $email) use ($run, &$stage, &$events) {
    $order = $email->object;
    if (!$order instanceof WC_Order || $run !== $order->get_meta('_commerce_h72_qa_run')) { return; }
    $events[] = [
        'stage' => $stage, 'email_id' => $id, 'order_id' => $order->get_id(), 'sent' => (bool) $sent,
        'recipient' => $email->get_recipient(), 'subject' => $email->get_subject(), 'email_type' => $email->get_email_type(),
        'from_address' => get_option('woocommerce_email_from_address'), 'from_name' => get_bloginfo('name'),
        'reply_to' => $email->is_customer_email() ? get_option('woocommerce_email_reply_to_address') : $order->get_billing_email(),
        'order_status_at_send' => $order->get_status(), 'total' => (float) $order->get_total(),
        'total_text' => html_entity_decode(wp_strip_all_tags(wc_price($order->get_total(), ['currency' => $order->get_currency()])), ENT_QUOTES, 'UTF-8'),
        'shipping_text' => html_entity_decode(wp_strip_all_tags(wc_price($order->get_shipping_total(), ['currency' => $order->get_currency()])), ENT_QUOTES, 'UTF-8'),
        'items' => array_values(array_map(static fn($item) => ['name' => $item->get_name(), 'quantity' => $item->get_quantity(), 'variant' => array_values(array_map(static fn($meta) => (string) $meta->value, $item->get_formatted_meta_data()))], $order->get_items())),
    ];
}, PHP_INT_MAX, 3);

$step = static function ($name, $operation, $expected) use (&$stage, &$events, &$steps, $check) {
    $stage = $name; $before = count($events); $result = $operation();
    $sent = array_slice($events, $before); $actual = array_column($sent, 'email_id'); sort($actual); sort($expected);
    $check($name . '_native_email_set', $actual === $expected);
    $check($name . '_mail_sent', !array_filter($sent, static fn($event) => !$event['sent'] || 'multipart' !== $event['email_type']));
    $steps[] = ['stage' => $name, 'emails' => $actual, 'count' => count($actual)];
    return $result;
};
$stock = static function () use (&$products) { return ['simple' => (int) wc_get_product($products['simple'])->get_stock_quantity(), 'variation' => (int) wc_get_product($products['variation'])->get_stock_quantity()]; };
$stock_is = static function ($name, $simple, $variation) use ($stock, $check, &$steps) { $actual = $stock(); $check($name . '_stock', ['simple' => $simple, 'variation' => $variation] === $actual); $steps[] = ['stage' => $name, 'stock' => $actual]; };
$summary = static function ($order) {
    $order = wc_get_order($order->get_id());
    return ['id' => $order->get_id(), 'case' => $order->get_meta('_commerce_h72_qa_case'), 'status' => $order->get_status(), 'customer_id' => $order->get_customer_id(), 'method' => $order->get_payment_method(), 'currency' => $order->get_currency(), 'total' => (float) $order->get_total(), 'shipping' => (float) $order->get_shipping_total(), 'tax' => (float) $order->get_total_tax(), 'total_refunded' => (float) $order->get_total_refunded(), 'refund_count' => count($order->get_refunds()), 'stock_reduced' => (bool) $order->get_data_store()->get_stock_reduced($order->get_id()), 'has_date_paid' => (bool) $order->get_date_paid(), 'has_transaction_id' => (bool) $order->get_transaction_id(), 'notes_count' => count(wc_get_order_notes(['order_id' => $order->get_id()]))];
};
$inventory = [];
try {
    $check('woocommerce_11_2_1', '11.2.1' === WC_VERSION);
    $check('hpos_active', \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled());
    $check('guest_checkout_enabled', 'yes' === get_option('woocommerce_enable_guest_checkout'));
    $check('integrity_extension_active', is_plugin_active('commerce-order-integrity/commerce-order-integrity.php'));
    $check('checkout_pro_disabled', 'yes' !== (get_option('woocommerce_woo-mercado-pago-basic_settings', [])['enabled'] ?? 'no'));
    $mailer = WC()->mailer();
    foreach ($mailer->get_emails() as $email) { $inventory[$email->id] = ['class' => get_class($email), 'enabled' => $email->is_enabled(), 'type' => $email->get_email_type(), 'manual' => (bool) $email->is_manual(), 'customer' => (bool) $email->is_customer_email()]; }
    foreach (['new_order', 'cancelled_order', 'failed_order', 'customer_cancelled_order', 'customer_failed_order', 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_note'] as $id) { $check($id . '_enabled_multipart', !empty($inventory[$id]['enabled']) && 'multipart' === $inventory[$id]['type']); }
    $gateways = WC()->payment_gateways()->payment_gateways(); $bacs = $gateways['bacs'] ?? null;
    $check('native_bacs_available', $bacs instanceof WC_Gateway_BACS && 'yes' === $bacs->enabled);
    $check('demo_bacs_non_operational', str_contains($bacs->instructions, 'No realizar transferencias reales') && !h7_1_filled(get_option('woocommerce_bacs_accounts', [])));
    $shipping_state = get_option('trama_h6_2', []);
    $shipping_method = WC_Shipping_Zones::get_shipping_method($shipping_state['flat_rate_instance_id'] ?? 0);
    $check('native_shipping_configuration_available', $shipping_method && 'flat_rate' === $shipping_method->id && is_numeric($shipping_method->get_option('cost')));
    $shipping_cost = (float) $shipping_method->get_option('cost');
    $admin = get_user_by('login', getenv('LOCAL_ADMIN_USER'));
    $check('controlled_local_admin_exists', $admin && str_ends_with($admin->user_email, '@example.test'));
    wp_set_current_user($admin->ID); $check('native_order_management_capability', current_user_can('manage_woocommerce') && current_user_can('edit_shop_orders'));

    $stage = 'fresh_products';
    $simple = new WC_Product_Simple();
    $simple->set_props(['name' => 'Prueba H7.2 — bolso ' . $short, 'sku' => 'H72-' . $short . '-S', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => '12000', 'manage_stock' => true, 'stock_quantity' => 100, 'backorders' => 'no', 'tax_status' => 'none']);
    $simple->update_meta_data('_commerce_h72_qa_run', $run); $simple->save(); $products['simple'] = $simple->get_id();
    $parent = new WC_Product_Variable(); $attributes = [];
    foreach (['Talle' => ['M'], 'Color' => ['Natural']] as $name => $values) { $attribute = new WC_Product_Attribute(); $attribute->set_name($name); $attribute->set_options($values); $attribute->set_visible(true); $attribute->set_variation(true); $attributes[] = $attribute; }
    $parent->set_props(['name' => 'Prueba H7.2 — remera ' . $short, 'sku' => 'H72-' . $short . '-P', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'attributes' => $attributes, 'tax_status' => 'none']);
    $parent->update_meta_data('_commerce_h72_qa_run', $run); $parent->save(); $products['parent'] = $parent->get_id();
    $variation = new WC_Product_Variation();
    $variation->set_props(['parent_id' => $parent->get_id(), 'sku' => 'H72-' . $short . '-V', 'status' => 'publish', 'attributes' => ['talle' => 'M', 'color' => 'Natural'], 'regular_price' => '18000', 'manage_stock' => true, 'stock_quantity' => 100, 'backorders' => 'no', 'tax_status' => 'none']);
    $variation->update_meta_data('_commerce_h72_qa_run', $run); $variation->save(); $products['variation'] = $variation->get_id(); WC_Product_Variable::sync($parent->get_id());
    $stock_is('initial', 100, 100);
    $make = static function ($case, $mixed = false) use ($run, $short, &$orders, &$products, $shipping_method, $shipping_cost) {
        $order = wc_create_order(['status' => 'pending', 'customer_id' => 0, 'created_via' => 'h72-native-qa']);
        if (is_wp_error($order)) { throw new RuntimeException('Synthetic native order creation failed.'); }
        $orders[$case] = $order;
        $order->set_props(['currency' => 'ARS', 'payment_method' => 'bacs', 'payment_method_title' => 'Transferencia bancaria', 'billing_first_name' => 'Prueba', 'billing_last_name' => 'H7.2', 'billing_email' => 'h72-' . $short . '-' . str_replace('_', '-', $case) . '@example.test', 'billing_address_1' => 'Dirección de prueba 123', 'billing_city' => 'Buenos Aires', 'billing_state' => 'C', 'billing_postcode' => 'C1000', 'billing_country' => 'AR', 'shipping_first_name' => 'Prueba', 'shipping_last_name' => 'H7.2', 'shipping_address_1' => 'Dirección de prueba 123', 'shipping_city' => 'Buenos Aires', 'shipping_state' => 'C', 'shipping_postcode' => 'C1000', 'shipping_country' => 'AR']);
        $order->update_meta_data('_commerce_h72_qa_run', $run); $order->update_meta_data('_commerce_h72_qa_case', $case);
        $order->add_product(wc_get_product($products['simple']), $mixed ? 2 : 1);
        if ($mixed) { $order->add_product(wc_get_product($products['variation']), 1); }
        $shipping = new WC_Order_Item_Shipping(); $shipping->set_method_id($shipping_method->id); $shipping->set_instance_id($shipping_method->get_instance_id()); $shipping->set_method_title($shipping_method->get_option('title')); $shipping->set_total($shipping_cost); $order->add_item($shipping);
        $order->calculate_totals(false); $order->add_order_note('QA H7.2 sintética. Sin transferencia, pago externo ni devolución de dinero.'); $order->save();
        return wc_get_order($order->get_id());
    };
    $on_hold = static function ($case, $mixed = false) use ($make, $bacs, $step, $check) {
        $order = $make($case, $mixed);
        $step($case . '_on_hold', static function () use ($bacs, $order, $check, $case) { $response = $bacs->process_payment($order->get_id()); $check($case . '_native_bacs_success', 'success' === ($response['result'] ?? '')); }, ['new_order', 'customer_on_hold_order']);
        $order = wc_get_order($order->get_id()); $check($case . '_on_hold_unpaid', $order->has_status('on-hold') && !$order->is_paid() && !$order->get_date_paid() && !$order->get_transaction_id());
        return $order;
    };

    $main = $on_hold('main', true); $stock_is('main_on_hold', 98, 99);
    $check('mixed_native_totals', 42000.0 + $shipping_cost === (float) $main->get_total() && 0.0 === (float) $main->get_total_tax());
    $step('on_hold_repeated_save', static function () use ($main) { $main->save(); $main->update_status('on-hold'); $main->save(); }, []); $stock_is('on_hold_repeated_save', 98, 99);
    $step('internal_note', static fn() => $main->add_order_note('Nota interna de QA. No enviar al comprador.'), []);
    $step('customer_note', static fn() => $main->add_order_note('Esta es una nota de prueba para tu pedido. No requiere ninguna transferencia real.', true), ['customer_note']);
    $step('main_processing', static fn() => $main->update_status('processing', 'Verificación bancaria simulada exclusivamente para QA H7.2.'), ['customer_processing_order']); $stock_is('main_processing', 98, 99);
    $check('manual_processing_native_paid_status', wc_get_order($main->get_id())->is_paid() && wc_get_order($main->get_id())->get_date_paid());
    $step('processing_repeated_save', static function () use ($main) { $main->save(); $main->update_status('processing'); $main->save(); }, []); $stock_is('processing_repeated_save', 98, 99);
    $step('main_completed', static fn() => $main->update_status('completed', 'Operación sintética completada. Sin tracking ni entrega física afirmada.'), ['customer_completed_order']); $stock_is('main_completed', 98, 99);
    $step('completed_repeated_save', static function () use ($main) { $main->save(); $main->update_status('completed'); }, []);

    // Exercise the exact native administration action, with a verified local
    // administrator. Its HTTP nonce/capability wrapper remains unchanged.
    require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-order-actions.php';
    $resend = static function ($action) use ($main) {
        $post_before = $_POST;
        try { $_POST['wc_order_action'] = $action; WC_Meta_Box_Order_Actions::save($main->get_id(), $main); }
        finally { $_POST = $post_before; }
    };
    $step('explicit_customer_resend', static fn() => $resend('send_order_details'), ['customer_invoice']);
    $step('explicit_customer_resend_again', static fn() => $resend('send_order_details'), ['customer_invoice']);
    $step('explicit_admin_resend', static fn() => $resend('send_order_details_admin'), ['new_order']);
    $stock_is('explicit_resends', 98, 99);

    $pending_cancel = $make('pending_cancel');
    $step('pending_cancel', static fn() => $pending_cancel->update_status('cancelled', 'Cancelación sintética de pedido impago.'), ['cancelled_order']); $stock_is('pending_cancel', 98, 99);
    $cancel = $on_hold('unpaid_cancel'); $stock_is('unpaid_cancel_held', 97, 99);
    $step('unpaid_cancel', static fn() => $cancel->update_status('cancelled', 'Cancelación administrativa sintética sin pago.'), ['cancelled_order', 'customer_cancelled_order']); $stock_is('unpaid_cancel', 98, 99);
    $step('cancelled_repeated_save', static function () use ($cancel) { $cancel->save(); $cancel->update_status('cancelled'); }, []); $stock_is('cancelled_repeated_save', 98, 99);
    $paid_cancel = $on_hold('paid_cancel');
    $step('paid_cancel_processing', static fn() => $paid_cancel->update_status('processing', 'Confirmación bancaria simulada.'), ['customer_processing_order']);
    $step('paid_cancel', static fn() => $paid_cancel->update_status('cancelled', 'Cancelación sintética posterior a confirmación. No equivale a devolver dinero.'), ['cancelled_order', 'customer_cancelled_order']); $stock_is('paid_cancel', 98, 99);
    $check('paid_cancellation_does_not_create_refund', !wc_get_order($paid_cancel->get_id())->get_refunds() && 0.0 === (float) wc_get_order($paid_cancel->get_id())->get_total_refunded() && wc_get_order($paid_cancel->get_id())->get_date_paid());
    $pending_failed = $make('pending_failed');
    $step('pending_failed', static fn() => $pending_failed->update_status('failed', 'Fallo interno sintético sin proveedor de pagos.'), ['failed_order', 'customer_failed_order']); $stock_is('pending_failed', 98, 99);
    $held_failed = $on_hold('held_failed'); $stock_is('held_failed_on_hold', 97, 99);
    $step('held_failed', static fn() => $held_failed->update_status('failed', 'Fallo sintético tras espera. Sin consulta externa.'), ['failed_order', 'customer_failed_order']); $stock_is('held_failed', 98, 99);
    $step('failed_repeated_save', static function () use ($held_failed) { $held_failed->save(); $held_failed->update_status('failed'); }, []); $stock_is('failed_repeated_save', 98, 99);

    $refund_order = $on_hold('refund', true);
    $step('refund_processing', static fn() => $refund_order->update_status('processing', 'Pago BACS simulado sólo para registro contable.'), ['customer_processing_order']); $stock_is('refund_before', 96, 98);
    $items = array_values($refund_order->get_items()); $shipping_items = array_values($refund_order->get_items('shipping'));
    $partial = $step('partial_manual_refund', static fn() => wc_create_refund(['order_id' => $refund_order->get_id(), 'amount' => 12000, 'reason' => ' — QA: registro contable parcial manual. No devuelve dinero.', 'refund_payment' => false, 'restock_items' => true, 'line_items' => [$items[0]->get_id() => ['qty' => 1, 'refund_total' => 12000, 'refund_tax' => []]]]), ['customer_partially_refunded_order']);
    $check('partial_manual_refund_record', $partial instanceof WC_Order_Refund && !$partial->get_refunded_payment() && 12000.0 === (float) $partial->get_amount()); $refunds[] = $partial->get_id();
    $stock_is('partial_manual_refund', 97, 98);
    $check('partial_keeps_processing', wc_get_order($refund_order->get_id())->has_status('processing') && 12000.0 === (float) wc_get_order($refund_order->get_id())->get_total_refunded());
    $check('partial_quantity_recorded', -1.0 === (float) wc_get_order($refund_order->get_id())->get_qty_refunded_for_item($items[0]->get_id()));
    $step('partial_save_no_extra_mail', static function () use ($partial, $refund_order) { $partial->save(); $refund_order->save(); }, []); $stock_is('partial_save_no_extra_mail', 97, 98);
    $remaining = 30000 + $shipping_cost;
    $full = $step('full_manual_refund', static fn() => wc_create_refund(['order_id' => $refund_order->get_id(), 'amount' => $remaining, 'reason' => ' — QA: registro contable del saldo y envío. No devuelve dinero.', 'refund_payment' => false, 'restock_items' => true, 'line_items' => [$items[0]->get_id() => ['qty' => 1, 'refund_total' => 12000, 'refund_tax' => []], $items[1]->get_id() => ['qty' => 1, 'refund_total' => 18000, 'refund_tax' => []], $shipping_items[0]->get_id() => ['qty' => 0, 'refund_total' => $shipping_cost, 'refund_tax' => []]]]), ['customer_refunded_order']);
    $check('full_manual_refund_record', $full instanceof WC_Order_Refund && !$full->get_refunded_payment() && $remaining === (float) $full->get_amount()); $refunds[] = $full->get_id(); $stock_is('full_manual_refund', 98, 99);
    $refreshed = wc_get_order($refund_order->get_id());
    $check('full_status_set_by_refund_api', $refreshed->has_status('refunded') && 42000.0 + $shipping_cost === (float) $refreshed->get_total_refunded() && 2 === count($refreshed->get_refunds()));
    $check('full_quantities_and_shipping_recorded', -2.0 === (float) $refreshed->get_qty_refunded_for_item($items[0]->get_id()) && -1.0 === (float) $refreshed->get_qty_refunded_for_item($items[1]->get_id()) && $shipping_cost === (float) $refreshed->get_total_refunded_for_item($shipping_items[0]->get_id(), 'shipping'));
    $step('refunded_repeated_save', static function () use ($refreshed) { $refreshed->save(); $refreshed->update_status('refunded'); }, []); $stock_is('refunded_repeated_save', 98, 99);
    $no_restock = $on_hold('no_restock');
    $step('no_restock_processing', static fn() => $no_restock->update_status('processing', 'Confirmación bancaria simulada.'), ['customer_processing_order']); $stock_is('no_restock_before', 97, 99);
    $no_items = array_values($no_restock->get_items()); $no_shipping = array_values($no_restock->get_items('shipping'));
    $without = $step('full_manual_refund_without_restock', static fn() => wc_create_refund(['order_id' => $no_restock->get_id(), 'amount' => $no_restock->get_total(), 'reason' => ' — QA: registro manual sin reponer existencias. Sin movimiento de dinero.', 'refund_payment' => false, 'restock_items' => false, 'line_items' => [$no_items[0]->get_id() => ['qty' => 1, 'refund_total' => 12000, 'refund_tax' => []], $no_shipping[0]->get_id() => ['qty' => 0, 'refund_total' => $shipping_cost, 'refund_tax' => []]]]), ['customer_refunded_order']);
    $check('manual_refund_without_restock_record', $without instanceof WC_Order_Refund && !$without->get_refunded_payment() && wc_get_order($no_restock->get_id())->has_status('refunded')); $refunds[] = $without->get_id(); $stock_is('full_manual_refund_without_restock', 97, 99);
    $check('all_owned_orders_guest_and_no_provider_transaction', !array_filter($orders, static fn($order) => 0 !== $order->get_customer_id() || $order->get_transaction_id()));
    $check('only_controlled_qa_mail_attempted', 0 === $blocked_mail);
    $check('all_expected_mails_used_internal_smtp', count($events) === $smtp_checks);
} catch (Throwable $error) {
    $checks['completed_without_exception'] = false;
    $failure = ['stage' => $stage, 'class' => get_class($error), 'line' => $error->getLine()];
} finally {
    wp_set_current_user($old_user);
    $checks['historical_orders_preserved'] = $history_before === $history();
    $checks['prior_products_coupons_and_stock_preserved'] = $catalog_before === $catalog();
    $checks['payment_and_bank_settings_preserved'] = $sensitive_before === h7_1_sensitive_snapshot();
}
$report = ['result' => in_array(false, $checks, true) ? 'failed' : 'passed', 'run' => $run, 'site_name' => get_bloginfo('name'), 'demo_logo_empty' => '' === get_option('woocommerce_email_header_image', ''), 'checks' => $checks, 'failure' => $failure, 'email_inventory' => $inventory, 'steps' => $steps, 'emails' => $events, 'orders' => array_values(array_map($summary, $orders)), 'products' => $products, 'refund_ids' => $refunds, 'final_owned_stock' => isset($products['simple'], $products['variation']) ? $stock() : null, 'prior_order_count' => count($prior_order_ids), 'prior_catalog_objects' => count($prior_product_ids), 'blocked_ancillary_http_requests' => $blocked_http, 'blocked_unsafe_mail_attempts' => $blocked_mail, 'provider_calls' => 0, 'money_refunds' => 0, 'orders_deleted' => false, 'direct_stock_repairs' => false, 'tax_scope' => 'Non-taxable synthetic products and shipping; no live tax rules changed. Shipping included in full manual refund.'];
$emit($report);
if ('passed' !== $report['result']) { exit(1); }
