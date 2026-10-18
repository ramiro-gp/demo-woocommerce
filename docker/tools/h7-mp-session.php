<?php
/** Local QA operations through native options/CRUD and official read-only SDK. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { exit(1); }
class H71SessionException extends RuntimeException {}
$action = $args[0] ?? 'inspect';
$expected = $args[1] ?? '';
$mp = $GLOBALS['mercadopago'] ?? null;
if (!$mp) { WP_CLI::error('Official plugin required.'); }
$state = get_option('trama_h7_mp_session', []);
$bacs = static fn() => hash('sha256', wp_json_encode([get_option('woocommerce_bacs_settings'), get_option('woocommerce_bacs_accounts')]));
$out = ['scope' => 'H7.1 external test session', 'action' => $action, 'result' => 'passed'];
try {
    if ($state && $state['bacs_hash'] !== $bacs() && $action !== 'disable') { throw new H71SessionException('BACS changed unexpectedly.'); }
    if ($action === 'prepare') {
        if ($expected === '') { throw new H71SessionException('Expected seller required.'); }
        $identity = $mp->sellerConfig->getSellerInfo($mp->sellerConfig->getCredentialsAccessTokenProd());
        $seller = $identity['data'] ?? [];
        if (($identity['status'] ?? 0) !== 200 || !in_array('test_user', (array) ($seller['tags'] ?? []), true)
            || ($seller['site_id'] ?? '') !== 'MLA' || strcasecmp($seller['nickname'] ?? '', $expected) !== 0) {
            throw new H71SessionException('Expected Argentine test seller not verified.');
        }
        if (!$state) {
            foreach (['H71-MP-SIMPLE', 'H71-MP-VARIABLE', 'H71-MP-VARIATION'] as $sku) {
                if (wc_get_product_id_by_sku($sku)) { throw new H71SessionException('Owned fixture collision.'); }
            }
            if (wc_get_coupon_id_by_code('h71-mp-10')) { throw new H71SessionException('Coupon collision.'); }
            $state = ['expected_seller' => $expected, 'seller_id' => (string) $seller['id'], 'bacs_hash' => $bacs(), 'created_at' => gmdate('c')];
            $simple = new WC_Product_Simple();
            $simple->set_props(['name' => 'Prueba externa H7.1 — simple', 'slug' => 'h71-mp-simple', 'sku' => 'H71-MP-SIMPLE', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => '59000', 'sale_price' => '50000', 'manage_stock' => true, 'stock_quantity' => 8, 'backorders' => 'no', 'weight' => '0.2', 'length' => '24', 'width' => '18', 'height' => '3']);
            if (wc_get_product_id_by_sku($simple->get_sku())) { throw new H71SessionException('Owned fixture collision.'); }
            $simple->update_meta_data('_trama_h7_mp_fixture', '1'); $simple->save();
            $parent = new WC_Product_Variable(); $attribute = new WC_Product_Attribute();
            $attribute->set_name('Talle'); $attribute->set_options(['S']); $attribute->set_variation(true); $attribute->set_visible(true);
            $parent->set_props(['name' => 'Prueba externa H7.1 — variable', 'slug' => 'h71-mp-variable', 'sku' => 'H71-MP-VARIABLE', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'attributes' => [$attribute], 'default_attributes' => ['talle' => 'S'], 'weight' => '0.3', 'length' => '30', 'width' => '22', 'height' => '3']);
            $parent->update_meta_data('_trama_h7_mp_fixture', '1'); $parent->save();
            $variation = new WC_Product_Variation();
            $variation->set_props(['parent_id' => $parent->get_id(), 'sku' => 'H71-MP-VARIATION', 'status' => 'publish', 'attributes' => ['talle' => 'S'], 'regular_price' => '60000', 'sale_price' => '52000', 'manage_stock' => true, 'stock_quantity' => 6, 'backorders' => 'no']);
            $variation->update_meta_data('_trama_h7_mp_fixture', '1'); $variation->save(); WC_Product_Variable::sync($parent->get_id());
            $coupon = new WC_Coupon(); $coupon->set_code('h71-mp-10'); $coupon->set_discount_type('percent'); $coupon->set_amount(10); $coupon->set_product_ids([$simple->get_id(), $parent->get_id(), $variation->get_id()]);
            if (wc_get_coupon_id_by_code($coupon->get_code())) { throw new H71SessionException('Coupon collision.'); }
            $coupon->update_meta_data('_trama_h7_mp_fixture', '1'); $coupon->save();
            $state['products'] = ['simple' => $simple->get_id(), 'parent' => $parent->get_id(), 'variation' => $variation->get_id()];
            $state['coupon'] = $coupon->get_id(); update_option('trama_h7_mp_session', $state, false);
        }
        if ($state['expected_seller'] !== $expected || $state['bacs_hash'] !== $bacs()) { throw new H71SessionException('Session or BACS changed.'); }
        $out['test_seller_verified'] = true; unset($identity, $seller);
    } elseif ($action === 'enable') {
        if (!$state || $expected !== $state['expected_seller'] || wp_parse_url(home_url(), PHP_URL_SCHEME) !== 'https') { throw new H71SessionException('Verified session and public HTTPS required.'); }
        $identity = $mp->sellerConfig->getSellerInfo($mp->sellerConfig->getCredentialsAccessTokenProd()); $seller = $identity['data'] ?? [];
        if (($identity['status'] ?? 0) !== 200 || !in_array('test_user', (array) ($seller['tags'] ?? []), true) || ($seller['site_id'] ?? '') !== 'MLA' || (string) ($seller['id'] ?? '') !== $state['seller_id']) { throw new H71SessionException('Test seller changed.'); }
        foreach (WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
            if (str_starts_with($id, 'woo-mercado-pago') && $id !== 'woo-mercado-pago-basic' && $gateway->enabled === 'yes') { throw new H71SessionException('Unexpected MP checkout enabled.'); }
        }
        $mp->storeConfig->setCheckboxCheckoutTestMode('no');
        $mp->storeConfig->setCheckboxCheckoutProductionMode('yes');
        $settings = get_option('woocommerce_woo-mercado-pago-basic_settings', []);
        update_option('woocommerce_woo-mercado-pago-basic_settings', array_replace($settings, ['enabled' => 'yes', 'title' => 'Mercado Pago', 'method' => 'redirect', 'auto_return' => 'no', 'binary_mode' => 'no']), false);
        $out['native_pro_enabled_for_verified_test_seller'] = true;
    } elseif ($action === 'disable') {
        $settings = get_option('woocommerce_woo-mercado-pago-basic_settings', []); $settings['enabled'] = 'no';
        update_option('woocommerce_woo-mercado-pago-basic_settings', $settings, false);
        $out['pro_disabled'] = true;
    } elseif ($action === 'payment') {
        $order = wc_get_order((int) ($args[1] ?? 0));
        $pid = (string) ($args[2] ?? '');
        if (!$state || !$order || !preg_match('/^[0-9]+$/', $pid) || $order->get_payment_method() !== 'woo-mercado-pago-basic') { throw new H71SessionException('Owned order/payment required.'); }
        foreach ($order->get_items() as $item) { if (!in_array($item->get_variation_id() ?: $item->get_product_id(), $state['products'], true)) { throw new H71SessionException('Unowned order.'); } }
        $reader = new \MercadoPago\Woocommerce\Notification\IpnNotification(WC()->payment_gateways()->payment_gateways()['woo-mercado-pago-basic'], $mp->logs, $mp->orderStatus, $mp->sellerConfig, $mp->storeConfig, $mp->helpers->requester);
        $payment = $reader->getPaymentInfo($pid);
        if ((string) ($payment['external_reference'] ?? '') !== 'WC-' . $order->get_id() || (string) ($payment['collector_id'] ?? '') !== $state['seller_id']) { throw new H71SessionException('Payment ownership mismatch.'); }
        $out['authenticated_payment'] = ['order' => $order->get_id(), 'status' => $payment['status'], 'detail' => $payment['status_detail'], 'amount' => $payment['transaction_amount'], 'currency' => $payment['currency_id'], 'test_seller_matches' => true, 'buyer_differs' => !empty($payment['payer']['id']) && (string) $payment['payer']['id'] !== $state['seller_id']];
        $core = new \MercadoPago\Woocommerce\Notification\CoreNotification(WC()->payment_gateways()->payment_gateways()['woo-mercado-pago-basic'], $mp->logs, $mp->orderStatus, $mp->sellerConfig, $mp->storeConfig);
        foreach (['payment' => 'P-' . $pid, 'merchant_order' => 'M-' . ($payment['order']['id'] ?? '')] as $type => $notificationId) {
            try {
                $notification = $core->getSdkInstance()->getNotificationInstance()->read(['id' => $notificationId])->toArray();
                $target = (string) ($notification['notification_url'] ?? '');
                $out['core_lookup'][$type] = ['found' => true, 'resource_id' => $notificationId, 'status' => $notification['status'] ?? null, 'destination_https' => wp_parse_url($target, PHP_URL_SCHEME) === 'https', 'destination_host_matches' => wp_parse_url($target, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST), 'reference_matches' => ($notification['external_reference'] ?? '') === 'WC-' . $order->get_id()];
            } catch (Throwable $lookupError) { $out['core_lookup'][$type] = ['found' => false, 'error_type' => get_class($lookupError), 'error_code' => $lookupError->getCode(), 'http_status' => method_exists($lookupError, 'getApiStatus') ? $lookupError->getApiStatus() : null]; }
        }
    } elseif ($action !== 'inspect') { throw new H71SessionException('Use prepare|enable|disable|inspect|payment.'); }
    if ($action === 'disable' && $state) { $out['bacs_preserved'] = $bacs() === $state['bacs_hash']; }
    if ($state && $action !== 'disable') {
        if ($bacs() !== $state['bacs_hash'] && $action !== 'disable') { throw new H71SessionException('BACS changed unexpectedly.'); }
        $out['bacs_preserved'] = $bacs() === $state['bacs_hash']; $out['fixtures'] = [];
        foreach ($state['products'] as $key => $id) { $p = wc_get_product($id); $out['fixtures'][$key] = ['id' => $id, 'price' => $p->get_price(), 'stock' => $p->get_stock_quantity(), 'held_stock' => $p->managing_stock() ? wc_get_held_stock_quantity($p) : 0, 'type' => $p->get_type()]; }
        $out['orders'] = [];
        foreach (wc_get_orders(['limit' => 100, 'orderby' => 'id', 'order' => 'DESC']) as $order) {
            if (!$order instanceof WC_Order) { continue; }
            if (!in_array($order->get_payment_method(), ['woo-mercado-pago-basic', 'bacs'], true)) { continue; }
            $owned = count($order->get_items()) > 0;
            foreach ($order->get_items() as $item) { if (!in_array($item->get_variation_id() ?: $item->get_product_id(), $state['products'], true)) { $owned = false; } }
            if (!$owned) { continue; }
            $row = ['id' => $order->get_id(), 'status' => $order->get_status(), 'paid' => $order->is_paid(), 'date_paid_present' => (bool) $order->get_date_paid(), 'transaction_id_present' => $order->get_transaction_id() !== '', 'total' => $order->get_total(), 'shipping' => $order->get_shipping_total(), 'discount' => $order->get_discount_total(), 'currency' => $order->get_currency(), 'stock_reduced' => $order->get_data_store()->get_stock_reduced($order->get_id()), 'notes_count' => count(wc_get_order_notes(['order_id' => $order->get_id()])), 'data_store' => $order->get_data_store()->get_current_class_name(), 'payment_resources' => []];
            $row['native_mp_payment_ids_present'] = (string) $mp->orderMetadata->getPaymentsIdMeta($order) !== '';
            $row['method'] = $order->get_payment_method();
            $row['date_paid'] = $order->get_date_paid()?->date(DATE_ATOM);
            $row['guest'] = $order->get_customer_id() === 0;
            $row['items'] = array_values(array_map(static fn($item) => ['product' => $item->get_product_id(), 'variation' => $item->get_variation_id(), 'quantity' => $item->get_quantity(), 'subtotal' => $item->get_subtotal(), 'total' => $item->get_total()], $order->get_items()));
            $row['shipping_items'] = array_values(array_map(static fn($item) => ['method' => $item->get_method_id(), 'total' => $item->get_total()], $order->get_shipping_methods()));
            if ($mp->storeConfig->isProductionMode()) {
                $reader = new \MercadoPago\Woocommerce\Notification\IpnNotification(WC()->payment_gateways()->payment_gateways()['woo-mercado-pago-basic'], $mp->logs, $mp->orderStatus, $mp->sellerConfig, $mp->storeConfig, $mp->helpers->requester);
                foreach (preg_split('/\s*,\s*/', (string) $mp->orderMetadata->getPaymentsIdMeta($order)) as $pid) {
                    if (!preg_match('/^[0-9]+$/', $pid)) { continue; }
                    $payment = $reader->getPaymentInfo($pid);
                    $notificationUrl = (string) ($payment['notification_url'] ?? '');
                    $row['callback_provider_bridge'] = wp_parse_url($notificationUrl, PHP_URL_HOST) === 'eplatforms-core-notification.melisystems.com';
                    $row['callback_legacy_merchant_path'] = str_contains($notificationUrl, 'merchant-order-notification');
                    $row['callback_host'] = wp_parse_url($notificationUrl, PHP_URL_HOST);
                    parse_str((string) wp_parse_url($notificationUrl, PHP_URL_QUERY), $callbackQuery);
                    $row['callback_parameter_names'] = array_keys($callbackQuery);
                    $row['plugin_custom_domain_present'] = $mp->storeConfig->getCustomDomain() !== '';
                    $row['callback_same_origin'] = wp_parse_url($notificationUrl, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST);
                    $row['callback_has_native_route'] = stripos($notificationUrl, 'WC_WooMercadoPago_Basic_Gateway') !== false;
                    $row['callback_uses_ipn_source'] = str_contains($notificationUrl, 'source_news=ipn');
                    $row['payment_resources'][] = ['status' => $payment['status'] ?? null, 'detail' => $payment['status_detail'] ?? null, 'currency' => $payment['currency_id'] ?? null, 'amount' => $payment['transaction_amount'] ?? null, 'seller_matches_verified_test' => (string) ($payment['collector_id'] ?? '') === $state['seller_id'], 'buyer_differs_from_seller' => !empty($payment['payer']['id']) && (string) $payment['payer']['id'] !== $state['seller_id'], 'reference_matches_order' => (string) ($payment['external_reference'] ?? '') === 'WC-' . $order->get_id()];
                }
            }
            $out['orders'][] = $row;
        }
    }
} catch (Throwable $error) { $out = ['result' => 'failed', 'action' => $action, 'error_type' => get_class($error), 'source' => basename($error->getFile()), 'line' => $error->getLine(), 'detail' => $error instanceof H71SessionException ? $error->getMessage() : 'Provider error details withheld.']; }
WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($out['result'] !== 'passed') { exit(1); }
