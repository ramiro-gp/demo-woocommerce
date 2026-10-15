<?php
/**
 * Local H2 checks, invoked only with `wp eval-file /opt/local-tools/h2-verify.php inspect|inventory`.
 * This file is never loaded by WordPress HTTP requests. It implements no gateway or inventory hooks.
 * `inventory` exercises WooCommerce public APIs; it does NOT test Mercado Pago payments/webhooks.
 */
if (!defined('WP_CLI') || !WP_CLI || !function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'local') {
    if (defined('STDERR')) {
        fwrite(STDERR, "H2 verification requires WP-CLI and WP_ENVIRONMENT_TYPE=local.\n");
    }
    exit(1);
}

$mode = $args[0] ?? '';
if (!in_array($mode, ['inspect', 'inventory'], true)) {
    WP_CLI::error('Usage: wp eval-file /opt/local-tools/h2-verify.php inspect|inventory');
}
if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
    WP_CLI::error('WooCommerce must be active before running H2 verification.');
}

$report = [
    'mode' => $mode,
    'scope' => $mode === 'inspect' ? 'woocommerce_core_checks_and_read_only_official_mercado_pago_evidence' : 'local_woocommerce_core_apis_only',
    'payment_submission' => false,
    'webhook_delivery' => false,
    'money_movement_requested' => false,
    'checks' => [],
];
$stage = 'prerequisites';
$failed = false;
$orders = [];
$probe = null;
$probe_owned = false;
$assert = static function ($condition, $name) use (&$report) {
    $report['checks'][$name] = (bool) $condition;
    if (!$condition) {
        throw new RuntimeException($name);
    }
};
$product_summary = static function ($product) {
    if (!$product) {
        return null;
    }
    return [
        'id' => $product->get_id(),
        'sku' => $product->get_sku(),
        'type' => $product->get_type(),
        'status' => $product->get_status(),
        'price' => $product->get_price(),
        'manage_stock' => $product->managing_stock(),
        'stock' => $product->get_stock_quantity(),
        'stock_status' => $product->get_stock_status(),
        'backorders' => $product->backorders_allowed(),
        'purchasable' => $product->is_purchasable(),
        'attributes' => $product->is_type('variation') ? $product->get_attributes() : array_keys($product->get_attributes()),
    ];
};
$fixture_summary = static function () use ($product_summary) {
    $result = [];
    foreach (['H2-SIMPLE', 'H2-VARIABLE'] as $sku) {
        $product = wc_get_product(wc_get_product_id_by_sku($sku));
        $result[$sku] = $product_summary($product);
        if ($product && $product->is_type('variable')) {
            $result[$sku]['variations'] = [];
            foreach ($product->get_children() as $id) {
                $result[$sku]['variations'][] = $product_summary(wc_get_product($id));
            }
        }
    }
    return $result;
};
$order_summary = static function ($order) {
    return [
        'id' => $order->get_id(),
        'status' => $order->get_status(),
        'guest' => $order->get_customer_id() === 0,
        'created_via' => $order->get_created_via(),
        'payment_method_empty' => $order->get_payment_method() === '',
        'case' => $order->get_meta('_h2_probe_case'),
        'total' => $order->get_total(),
        'refunded_total' => $order->get_total_refunded(),
        'refund_ids' => array_map(static function ($refund) { return $refund->get_id(); }, $order->get_refunds()),
    ];
};

try {
    $hpos = class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    $report['versions'] = ['wordpress' => get_bloginfo('version'), 'woocommerce' => WC_VERSION, 'php' => PHP_VERSION];
    $report['hpos'] = ['enabled' => $hpos, 'order_data_store' => WC_Data_Store::load('order')->get_current_class_name()];
    $report['configuration'] = [
        'locale' => get_locale(),
        'timezone' => get_option('timezone_string'),
        'base_country' => WC()->countries->get_base_country(),
        'currency' => get_woocommerce_currency(),
        'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
        'guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
        'signup_at_checkout' => get_option('woocommerce_enable_signup_and_login_from_checkout'),
        'manage_stock' => get_option('woocommerce_manage_stock'),
        'hold_stock_minutes' => (int) get_option('woocommerce_hold_stock_minutes'),
    ];
    $report['pages'] = [];
    foreach (['cart', 'checkout', 'shop', 'myaccount'] as $page) {
        $id = wc_get_page_id($page);
        $post = $id > 0 ? get_post($id) : null;
        $report['pages'][$page] = [
            'id' => $id,
            'published' => $post && $post->post_status === 'publish',
            'block' => in_array($page, ['cart', 'checkout'], true) && $post ? has_block('woocommerce/' . $page, $post) : null,
        ];
    }
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $report['plugins'] = [];
    $mercado_pago_active = false;
    foreach (get_plugins() as $file => $data) {
        if (is_plugin_active($file)) {
            // Public metadata only; never dump gateway options, tokens, users or raw orders.
            $report['plugins'][] = ['file' => $file, 'version' => $data['Version']];
            if (strpos($file, 'woocommerce-mercadopago/') === 0) {
                $mercado_pago_active = true;
                $report['versions']['mercado_pago'] = $data['Version'];
            }
        }
    }
    $mp_file = 'woocommerce-mercadopago/woocommerce-mercadopago.php';
    $mp_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $mp_file, false, false);
    $assert(WC_VERSION === '11.2.1', 'pinned_woocommerce_active');
    $assert(is_plugin_active($mp_file) && $mp_data['Version'] === '8.9.6', 'pinned_official_mercadopago_active');
    $gateways = WC()->payment_gateways()->payment_gateways();
    $pro = $gateways['woo-mercado-pago-basic'] ?? null;
    $assert($pro && $pro instanceof \MercadoPago\Woocommerce\Gateways\BasicGateway, 'official_checkout_pro_registered');
    $report['checkout_pro'] = [
        'gateway' => $pro->id,
        'enabled' => $pro->enabled,
        'missing_credentials' => $pro->isMissingCredentials(),
        'available' => $pro->is_available(),
        'supports_refunds' => $pro->supports('refunds'),
        'settings_fields_present' => !empty($pro->form_fields),
        'block_class_present' => class_exists('MercadoPago\\Woocommerce\\Blocks\\BasicBlock'),
    ];
    // Identity and mode only. Never include credential values or raw account data.
    $mp = $GLOBALS['mercadopago'];
    $report['mercado_pago_account'] = [
        'collector_id' => $mp->sellerConfig->getCollectorId(),
        'is_test_user' => $mp->sellerConfig->isTestUser(),
        'site_id' => $mp->sellerConfig->getSiteId(),
        'production_mode' => $mp->storeConfig->isProductionMode(),
    ];
    if ($mode === 'inspect' && !$pro->isMissingCredentials()) {
        // The official plugin resolves this account using its server-side token.
        // Checkout Pro tests use the linked test seller's production-mode credential.
        $seller_identity = $mp->sellerConfig->getSellerInfo($mp->sellerConfig->getCredentialsAccessTokenProd());
        $seller_data = $seller_identity['data'] ?? [];
        $report['mercado_pago_account']['identity_lookup_status'] = $seller_identity['status'] ?? null;
        $report['mercado_pago_account']['linked_account_id'] = (string) ($seller_data['id'] ?? '');
        $report['mercado_pago_account']['linked_account_is_test_user'] = in_array('test_user', $seller_data['tags'] ?? [], true);
        $report['mercado_pago_account']['linked_account_site_id'] = $seller_data['site_id'] ?? '';
        $report['mercado_pago_account']['linked_application_id'] = (string) ($seller_data['client_id'] ?? '');
        $report['mercado_pago_account']['linked_application_name'] = $seller_data['app_name'] ?? '';
    }
    $report['shipping'] = [];
    foreach (WC_Shipping_Zones::get_zones() as $zone) {
        $methods = [];
        foreach ($zone['shipping_methods'] as $method) {
            $methods[] = ['id' => $method->id, 'enabled' => $method->enabled];
        }
        $report['shipping'][] = ['id' => $zone['id'], 'methods' => $methods];
    }
    $before = $fixture_summary();
    $report['fixtures'] = $before;
    $assert($hpos, 'hpos_enabled');
    $assert($mercado_pago_active, 'official_mercado_pago_plugin_active');
    $assert(get_locale() === 'es_AR', 'locale_es_AR');
    $assert(WC()->countries->get_base_country() === 'AR', 'base_country_AR');
    $assert(get_woocommerce_currency() === 'ARS', 'currency_ARS');
    $assert(get_option('woocommerce_enable_guest_checkout') === 'yes', 'guest_checkout_enabled');
    foreach (['cart', 'checkout'] as $page) {
        $assert($report['pages'][$page]['published'] && $report['pages'][$page]['block'], $page . '_block_assigned');
    }
    $simple = wc_get_product(wc_get_product_id_by_sku('H2-SIMPLE'));
    $variable = wc_get_product(wc_get_product_id_by_sku('H2-VARIABLE'));
    $assert($simple && $simple->is_type('simple') && $simple->managing_stock(), 'simple_fixture_exists');
    $assert($variable && $variable->is_type('variable'), 'variable_fixture_exists');
    $available = false;
    $sold_out = false;
    foreach ($variable->get_children() as $id) {
        $variation = wc_get_product($id);
        $available = $available || ($variation && $variation->is_in_stock() && $variation->has_enough_stock(1));
        $sold_out = $sold_out || ($variation && !$variation->is_in_stock() && !$variation->has_enough_stock(1));
    }
    $assert($available && $sold_out, 'available_and_sold_out_variations');

    if ($mode === 'inspect') {
        $technical = wc_get_orders([
            'type' => 'shop_order', 'limit' => 100, 'orderby' => 'id', 'order' => 'DESC',
            'meta_query' => [['key' => '_h2_local_api', 'value' => 'yes']],
        ]);
        $report['technical_orders'] = array_map($order_summary, $technical);
        $report['checkout_orders'] = [];
        $payment_reader = null;
        if (!empty($report['mercado_pago_account']['linked_account_is_test_user'])) {
            // Reuse only the official read method. No notification handler is called.
            $payment_reader = new \MercadoPago\Woocommerce\Notification\IpnNotification(
                $pro, $mp->logs, $mp->orderStatus, $mp->sellerConfig, $mp->storeConfig, $mp->helpers->requester
            );
        }
        $checkout_orders = wc_get_orders([
            'type' => 'shop_order', 'limit' => 100, 'orderby' => 'id', 'order' => 'ASC',
            'payment_method' => 'woo-mercado-pago-basic',
        ]);
        foreach ($checkout_orders as $checkout_order) {
            $items = [];
            $only_h2_fixtures = true;
            foreach ($checkout_order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product || $product->get_meta('_demo_h2_fixture') !== 'demo-woocommerce-h2-v1') {
                    $only_h2_fixtures = false;
                    break;
                }
                $items[] = ['product_id' => $product->get_id(), 'sku' => $product->get_sku(), 'quantity' => $item->get_quantity()];
            }
            if (!$only_h2_fixtures || !$items) {
                continue;
            }
            $paid_at = $checkout_order->get_date_paid();
            $payments = [];
            if ($payment_reader) {
                $payment_ids = preg_split('/\s*,\s*/', (string) $mp->orderMetadata->getPaymentsIdMeta($checkout_order));
                foreach ($payment_ids as $payment_id) {
                    if (!preg_match('/^[0-9]+$/', $payment_id)) {
                        continue;
                    }
                    $payment = $payment_reader->getPaymentInfo($payment_id);
                    $payments[] = [
                        'id' => (string) ($payment['id'] ?? ''),
                        'status' => $payment['status'] ?? '',
                        'status_detail' => $payment['status_detail'] ?? '',
                        'live_mode' => $payment['live_mode'] ?? null,
                        'collector_id' => (string) ($payment['collector_id'] ?? ''),
                        'payer_id' => (string) ($payment['payer']['id'] ?? ''),
                        'currency' => $payment['currency_id'] ?? '',
                        'amount' => $payment['transaction_amount'] ?? null,
                        'refunded_amount' => $payment['transaction_amount_refunded'] ?? null,
                        'payment_type' => $payment['payment_type_id'] ?? '',
                        'external_reference' => $payment['external_reference'] ?? '',
                    ];
                }
            }
            $report['checkout_orders'][] = [
                'id' => $checkout_order->get_id(),
                'status' => $checkout_order->get_status(),
                'guest' => $checkout_order->get_customer_id() === 0,
                'created_via' => $checkout_order->get_created_via(),
                'payment_method' => $checkout_order->get_payment_method(),
                'transaction_id' => $checkout_order->get_transaction_id(),
                'total' => $checkout_order->get_total(),
                'is_paid' => $checkout_order->is_paid(),
                'paid_at_timestamp' => $paid_at ? $paid_at->getTimestamp() : null,
                'refunded_total' => $checkout_order->get_total_refunded(),
                'refund_ids' => array_map(static function ($refund) { return $refund->get_id(); }, $checkout_order->get_refunds()),
                'refunds' => array_map(static function ($refund) {
                    $created_at = $refund->get_date_created();
                    return [
                        'id' => $refund->get_id(),
                        'amount' => $refund->get_amount(),
                        'refunded_payment' => $refund->get_refunded_payment(),
                        'created_at_timestamp' => $created_at ? $created_at->getTimestamp() : null,
                    ];
                }, $checkout_order->get_refunds()),
                'items' => $items,
                'payments' => $payments,
                'metadata_keys' => array_map(static function ($meta) { return $meta->key; }, $checkout_order->get_meta_data()),
            ];
        }
    } else {
        foreach (['wc_create_order', 'wc_create_refund', 'wc_reserve_stock_for_order', 'wc_release_stock_for_order', 'wc_get_held_stock_quantity'] as $function) {
            $assert(function_exists($function), 'api_' . $function);
        }
        $assert(get_option('woocommerce_manage_stock') === 'yes', 'global_stock_enabled');
        $assert((int) get_option('woocommerce_hold_stock_minutes', 60) > 0, 'stock_hold_enabled');
        $run = gmdate('Ymd-His') . '-' . substr(wp_generate_uuid4(), 0, 8);
        $report['run'] = $run;
        $stage = 'probe_product';
        $id = wc_get_product_id_by_sku('H2-STOCK-PROBE');
        if ($id) {
            $probe = wc_get_product($id);
            $assert($probe && $probe->get_meta('_h2_local_api') === 'yes' && $probe->is_type('simple'), 'probe_is_owned_fixture');
            $assert((int) $probe->get_stock_quantity() === 3 && (int) wc_get_held_stock_quantity($probe) === 0, 'probe_has_clean_initial_stock');
            $assert($probe->managing_stock() && !$probe->backorders_allowed() && $probe->get_catalog_visibility() === 'hidden', 'probe_has_expected_stock_settings');
            $probe_owned = true;
        } else {
            $probe = new WC_Product_Simple();
            $probe->set_name('H2 stock API probe - no payment');
            $probe->set_sku('H2-STOCK-PROBE');
            $probe->set_catalog_visibility('hidden');
            $probe->set_regular_price('1000');
            $probe->set_tax_status('none');
            $probe->set_manage_stock(true);
            $probe->set_stock_quantity(3);
            $probe->set_stock_status('instock');
            $probe->set_backorders('no');
            $probe->update_meta_data('_h2_local_api', 'yes');
            $probe->save();
            $probe_owned = true;
        }
        $probe->set_status('publish');
        $probe->save();
        $probe_id = $probe->get_id();
        $report['probe_product_id'] = $probe_id;
        $fresh = static function () use ($probe_id) { return wc_get_product($probe_id); };
        $stock = static function () use ($fresh) { return (int) $fresh()->get_stock_quantity(); };
        $held = static function () use ($fresh) { return (int) wc_get_held_stock_quantity($fresh()); };
        $record = static function ($step, $order = null) use (&$report, $fresh, $stock, $held) {
            $report['inventory_steps'][] = [
                'step' => $step,
                'stock' => $stock(),
                'held_stock' => $held(),
                'stock_status' => $fresh()->get_stock_status(),
                'order_id' => $order ? $order->get_id() : null,
                'order_status' => $order ? wc_get_order($order->get_id())->get_status() : null,
            ];
        };
        $new_order = static function ($case, $qty) use (&$orders, $run, $fresh, $assert) {
            $order = wc_create_order(['status' => 'pending', 'customer_id' => 0, 'created_via' => 'h2-local-api']);
            $assert(!is_wp_error($order), 'create_' . $case);
            $orders[$case] = $order->get_id();
            $order->set_customer_ip_address('');
            $order->set_customer_user_agent('');
            $order->set_payment_method('');
            $order->set_payment_method_title('H2 local API simulation - no gateway or money');
            $order->update_meta_data('_h2_local_api', 'yes');
            $order->update_meta_data('_h2_probe_run', $run);
            $order->update_meta_data('_h2_probe_case', $case);
            $order->update_meta_data('_h2_payment_evidence', 'none; WooCommerce core API simulation only');
            $item_id = $order->add_product($fresh(), $qty);
            $assert($item_id > 0, 'item_' . $case);
            $order->calculate_totals(false);
            $order->add_order_note('Technical H2 fixture. No Mercado Pago request, payment, notification or movement of money.');
            $order->save();
            return $order;
        };
        $blocked_reservation = static function ($order, $expected_code) use ($assert) {
            $code = '';
            try {
                wc_reserve_stock_for_order($order);
            } catch (\Automattic\WooCommerce\Checkout\Helpers\ReserveStockException $exception) {
                $code = $exception->getErrorCode();
            }
            $assert($code === $expected_code, $order->get_meta('_h2_probe_case') . '_reservation_rejected');
            $order->update_status('failed', 'Expected local stock validation rejection; no gateway contacted.');
            wc_release_stock_for_order($order);
            return $code;
        };

        $record('initial');
        $stage = 'pending_reservation';
        $pending = $new_order('pending_reservation', 1);
        wc_reserve_stock_for_order($pending);
        $assert($stock() === 3 && $held() === 1, 'pending_holds_one_without_reducing_stock');
        $record('pending_reservation', $pending);
        $over = $new_order('over_available_stock', 3);
        $report['over_available_stock_error'] = $blocked_reservation($over, 'woocommerce_product_not_enough_stock');
        $assert($stock() === 3 && $held() === 1, 'rejected_reservation_preserves_other_hold');
        $record('over_available_stock_rejected', $over);
        wc_release_stock_for_order($pending);
        $assert($stock() === 3 && $held() === 0, 'explicit_release_restores_availability');
        $record('explicit_reservation_release', $pending);
        $pending->update_status('cancelled', 'End of H2 local reservation test.');

        $stage = 'purchase_and_cancellation';
        $purchase = $new_order('synthetic_purchase_cancel', 1);
        wc_reserve_stock_for_order($purchase);
        $assert($purchase->payment_complete('H2-NO-GATEWAY-' . $run), 'synthetic_payment_complete');
        $purchase = wc_get_order($purchase->get_id());
        $assert($stock() === 2 && $held() === 0 && $purchase->is_paid(), 'one_unit_reduced_and_hold_released');
        $record('synthetic_one_unit_purchase', $purchase);
        $assert($purchase->payment_complete('H2-NO-GATEWAY-' . $run), 'repeat_synthetic_payment_complete');
        $assert($stock() === 2, 'repeat_payment_complete_does_not_reduce_twice');
        $record('repeated_synthetic_payment_complete', $purchase);
        $purchase->update_status('cancelled', 'H2 core cancellation test; no money was paid.');
        $assert($stock() === 3, 'cancellation_restores_stock');
        $record('cancelled_purchase', $purchase);
        $purchase->update_status('cancelled');
        $assert($stock() === 3, 'repeat_cancellation_does_not_restore_twice');

        $stage = 'failed_order';
        $declined = $new_order('pending_to_failed', 1);
        wc_reserve_stock_for_order($declined);
        $declined->update_status('failed', 'Synthetic core decline status; this is not a Mercado Pago rejection.');
        $record('pending_to_failed_before_explicit_release', $declined);
        $assert($stock() === 3 && $held() === 0, 'failed_status_no_longer_counts_pending_hold');
        $report['pending_to_failed_hold_scope'] = 'Observed through wc_get_held_stock_quantity after the status change. Raw reservation rows were not inspected. Explicit release follows for cleanup.';
        wc_release_stock_for_order($declined);
        $assert($stock() === 3 && $held() === 0, 'pending_failed_explicit_release_keeps_stock_and_availability');
        $record('pending_to_failed_after_explicit_release', $declined);
        $on_hold = $new_order('on_hold_to_failed', 1);
        $on_hold->update_status('on-hold', 'H2 core stock reduction test; no gateway contacted.');
        $assert($stock() === 2, 'on_hold_reduces_stock');
        $record('on_hold', $on_hold);
        $on_hold->update_status('failed', 'H2 core failure test; no gateway contacted.');
        $assert($stock() === 3, 'failed_reduced_order_restores_stock');
        $record('on_hold_to_failed', $on_hold);

        $stage = 'manual_refund';
        $refundable = $new_order('manual_refund_no_money', 1);
        $assert($refundable->payment_complete('H2-NO-GATEWAY-REFUND-' . $run), 'synthetic_refundable_order');
        $assert($stock() === 2, 'pre_refund_stock');
        $record('before_manual_refund', $refundable);
        $items = $refundable->get_items();
        $item = reset($items);
        $refund = wc_create_refund([
            'order_id' => $refundable->get_id(),
            'amount' => (float) $refundable->get_total(),
            'reason' => 'H2 manual core refund fixture; no gateway refund and no money moved.',
            'refund_payment' => false,
            'restock_items' => true,
            'line_items' => [$item->get_id() => ['qty' => 1, 'refund_total' => $item->get_total(), 'refund_tax' => []]],
        ]);
        $assert(!is_wp_error($refund), 'manual_refund_created');
        $refund->update_meta_data('_h2_local_api', 'yes');
        $refund->update_meta_data('_h2_probe_run', $run);
        $refund->save();
        $refundable = wc_get_order($refundable->get_id());
        $assert($stock() === 3 && $refundable->has_status('refunded'), 'manual_full_refund_restocks_and_sets_status');
        $assert(!$refund->get_refunded_payment(), 'manual_refund_did_not_call_gateway');
        $report['refund'] = ['id' => $refund->get_id(), 'total' => $refund->get_amount(), 'restock_items' => true, 'refund_payment' => false, 'money_returned' => false];
        $record('manual_refund_with_restock', $refundable);

        $stage = 'exhaustion';
        $exhaust = $new_order('exhaust_stock', 3);
        wc_reserve_stock_for_order($exhaust);
        $assert($exhaust->payment_complete('H2-NO-GATEWAY-EXHAUST-' . $run), 'synthetic_exhaustion');
        $assert($stock() === 0 && !$fresh()->is_in_stock() && !$fresh()->has_enough_stock(1), 'zero_stock_is_sold_out');
        $record('exhausted', $exhaust);
        $after_zero = $new_order('buy_after_zero_stock', 1);
        $report['out_of_stock_error'] = $blocked_reservation($after_zero, 'woocommerce_product_out_of_stock');
        $assert($stock() === 0 && $held() === 0, 'out_of_stock_rejection_keeps_zero');
        $record('out_of_stock_rejected', $after_zero);
        $exhaust->update_status('cancelled', 'End of H2 exhaustion test; no money was paid.');
        $assert($stock() === 3, 'exhaustion_cancellation_restores_three');
        $record('restored', $exhaust);

        $stage = 'hpos_query';
        $queried = wc_get_orders(['type' => 'shop_order', 'limit' => -1, 'return' => 'ids', 'meta_query' => [['key' => '_h2_probe_run', 'value' => $run]]]);
        $queried = array_map('intval', $queried);
        $expected = array_values($orders);
        sort($queried);
        sort($expected);
        $assert($queried === $expected, 'hpos_crud_query_returns_exact_technical_orders');
        $assert($fixture_summary() === $before, 'main_fixtures_unchanged');
    }
} catch (Throwable $exception) {
    $failed = true;
    // Deliberately omit raw exception messages: plugins may include private values in them.
    $report['failure'] = ['stage' => $stage, 'type' => get_class($exception)];
} finally {
    if ($mode === 'inventory' && $probe_owned && $probe) {
        try {
            foreach ($orders as $case => $id) {
                $order = wc_get_order($id);
                if (!$order) {
                    continue;
                }
                wc_release_stock_for_order($order);
                if ($order->has_status(['pending', 'on-hold', 'processing', 'completed'])) {
                    $order->update_status('cancelled', 'H2 local fixture cleanup; no gateway or money involved.');
                }
            }
            // Isolated fixture only. Preserve technical orders/refunds as evidence and hide the probe.
            wc_update_product_stock($probe->get_id(), 3, 'set');
            $probe = wc_get_product($probe->get_id());
            $probe->set_status('draft');
            $probe->save();
            $report['cleanup'] = ['probe_status' => $probe->get_status(), 'probe_stock' => $probe->get_stock_quantity(), 'held_stock' => wc_get_held_stock_quantity($probe), 'orders_preserved' => true];
            $report['technical_orders'] = [];
            foreach ($orders as $id) {
                $report['technical_orders'][] = $order_summary(wc_get_order($id));
            }
        } catch (Throwable $exception) {
            $failed = true;
            $report['cleanup_failure'] = ['type' => get_class($exception)];
        }
    }
}

$report['passed'] = !$failed;
WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if ($failed) {
    WP_CLI::error('H2 local API verification failed. Review the sanitized checks/stage and technical orders.');
}
