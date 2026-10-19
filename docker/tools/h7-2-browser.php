<?php
/** Isolated fixtures and CRUD inspection for real local H7.2 browser QA. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
require_once __DIR__ . '/h7-1-functions.php';
$mode = $args[0] ?? 'inspect';
$emit = static fn($value) => print(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
$require = static function ($condition, $name) { if (!$condition) { throw new RuntimeException($name); } };
$fingerprints = static function ($order_ids, $product_ids) {
    $orders = []; $products = [];
    foreach ($order_ids as $id) {
        $order = wc_get_order($id);
        if (!$order) { $orders[$id] = 'missing'; continue; }
        $data = $order->get_data();
        $data['meta_data'] = array_values(array_filter(array_map(static fn($meta) => $meta->get_data(), $order->get_meta_data()), static fn($meta) => '_debug_log_source_pending_deletion' !== $meta['key']));
        $data['items'] = array_map(static fn($item) => $item->get_data(), $order->get_items(['line_item', 'shipping', 'fee', 'coupon', 'tax']));
        $data['refunds'] = array_map(static fn($refund) => $refund->get_data(), $order->get_refunds());
        $orders[$id] = h7_1_hash($data);
    }
    foreach ($product_ids as $id) {
        $product = 'shop_coupon' === get_post_type($id) ? new WC_Coupon($id) : wc_get_product($id);
        $products[$id] = $product ? h7_1_hash($product->get_data()) : 'missing';
    }
    return ['orders' => h7_1_hash($orders), 'catalog' => h7_1_hash($products), 'sensitive' => h7_1_sensitive_snapshot()];
};
try {
    $require(in_array($mode, ['prepare', 'inspect', 'adopt', 'links', 'cleanup'], true), 'Allowed mode required.');
    $require('11.2.1' === WC_VERSION && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'Exact WooCommerce and HPOS required.');
    $require(is_plugin_active('commerce-order-integrity/commerce-order-integrity.php') && is_plugin_active('commerce-communications/commerce-communications.php'), 'Communications and integrity extensions required.');
    $require('yes' !== (get_option('woocommerce_woo-mercado-pago-basic_settings', [])['enabled'] ?? 'no'), 'Checkout Pro must stay disabled.');
    $bacs = WC()->payment_gateways()->payment_gateways()['bacs'] ?? null;
    $require($bacs instanceof WC_Gateway_BACS && 'yes' === $bacs->enabled && str_contains($bacs->instructions, 'No realizar transferencias reales') && !h7_1_filled(get_option('woocommerce_bacs_accounts', [])), 'Non-operational demo BACS required.');
    $require('yes' === get_option('woocommerce_enable_guest_checkout'), 'Guest checkout required.');
    foreach (WC()->mailer()->get_emails() as $email) {
        if (!$email->is_enabled() || $email->is_customer_email()) { continue; }
        foreach (explode(',', $email->get_recipient()) as $recipient) { $require(is_email(trim($recipient)) && str_ends_with(strtolower(trim($recipient)), '@example.test'), 'Every enabled administrative recipient must be controlled.'); }
    }
    add_filter('pre_http_request', static fn() => new WP_Error('h72_browser_offline', 'External HTTP disabled in fixture tooling.'), PHP_INT_MAX, 3);
    add_filter('pre_wp_mail', static function ($result, $attributes) {
        $recipients = is_array($attributes['to']) ? $attributes['to'] : explode(',', $attributes['to']);
        $headers = is_array($attributes['headers'] ?? '') ? implode("\n", $attributes['headers']) : (string) ($attributes['headers'] ?? '');
        foreach (preg_split('/\r?\n/', $headers) as $line) { if (preg_match('/^(?:Cc|Bcc):\s*(.+)$/i', $line, $match)) { $recipients = array_merge($recipients, explode(',', $match[1])); } }
        foreach ($recipients as $recipient) {
            if (preg_match('/<([^<>]+)>/', $recipient, $match)) { $recipient = $match[1]; }
            if (!is_email(trim($recipient)) || !str_ends_with(strtolower(trim($recipient)), '@example.test')) { return false; }
        }
        return $result;
    }, PHP_INT_MAX, 2);
    add_action('phpmailer_init', static function ($mailer) use ($require) { $require('smtp' === $mailer->Mailer && 'mailpit' === $mailer->Host && 1025 === (int) $mailer->Port && !$mailer->SMTPAuth && !$mailer->SMTPSecure, 'Internal Mailpit transport required.'); }, PHP_INT_MAX);

    if ('prepare' === $mode) {
        $run = str_replace('-', '', wp_generate_uuid4()); $short = substr($run, 0, 10);
        $prior_orders = wc_get_orders(['limit' => -1, 'return' => 'ids', 'type' => 'shop_order']);
        $prior_products = get_posts(['post_type' => ['product', 'product_variation', 'shop_coupon'], 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1]);
        $manifest = ['run' => $run, 'prior_order_ids' => $prior_orders, 'prior_product_ids' => $prior_products, 'baseline' => $fingerprints($prior_orders, $prior_products), 'orders' => []];
        $simple = new WC_Product_Simple();
        $simple->set_props(['name' => 'Prueba browser H7.2 — bolso ' . $short, 'sku' => 'H72B-' . $short . '-S', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'regular_price' => '12000', 'manage_stock' => true, 'stock_quantity' => 20, 'backorders' => 'no', 'tax_status' => 'none']);
        $simple->update_meta_data('_commerce_h72_browser_run', $run); $simple->save();
        $parent = new WC_Product_Variable(); $attributes = [];
        foreach (['Talle' => ['M'], 'Color' => ['Natural']] as $name => $values) { $attribute = new WC_Product_Attribute(); $attribute->set_name($name); $attribute->set_options($values); $attribute->set_visible(true); $attribute->set_variation(true); $attributes[] = $attribute; }
        $parent->set_props(['name' => 'Prueba browser H7.2 — remera ' . $short, 'sku' => 'H72B-' . $short . '-P', 'status' => 'publish', 'catalog_visibility' => 'hidden', 'attributes' => $attributes, 'tax_status' => 'none']);
        $parent->update_meta_data('_commerce_h72_browser_run', $run); $parent->save();
        $variation = new WC_Product_Variation();
        $variation->set_props(['parent_id' => $parent->get_id(), 'sku' => 'H72B-' . $short . '-V', 'status' => 'publish', 'attributes' => ['talle' => 'M', 'color' => 'Natural'], 'regular_price' => '18000', 'manage_stock' => true, 'stock_quantity' => 20, 'backorders' => 'no', 'tax_status' => 'none']);
        $variation->update_meta_data('_commerce_h72_browser_run', $run); $variation->save(); WC_Product_Variable::sync($parent->get_id());
        $coupon = new WC_Coupon(); $coupon->set_props(['code' => 'h72-browser-' . $short, 'discount_type' => 'percent', 'amount' => 10, 'individual_use' => true, 'product_ids' => [$simple->get_id(), $parent->get_id()], 'usage_limit' => 5]);
        $coupon->update_meta_data('_commerce_h72_browser_run', $run); $coupon->save();
        $email = 'h72b-' . $short . '@example.test'; $username = 'h72b-' . $short; $password = wp_generate_password(32, true, true);
        $customer_id = wc_create_new_customer($email, $username, $password, ['first_name' => 'Prueba', 'last_name' => 'H7.2 Browser']);
        $require(!is_wp_error($customer_id), 'A fresh synthetic customer must be created by WooCommerce.');
        $manifest['customer_id'] = $customer_id; $manifest['email'] = $email; $manifest['guest_email'] = 'h72b-' . $short . '-guest@example.test';
        $manifest['products'] = ['simple' => $simple->get_id(), 'parent' => $parent->get_id(), 'variation' => $variation->get_id()]; $manifest['coupon_id'] = $coupon->get_id();
        update_user_meta($customer_id, '_commerce_h72_browser_manifest', $manifest);
        // Explicit private fixture output. The Node runner writes it only under
        // ignored .local, never to its sanitized reports or console.
        $emit(['result' => 'ready', 'run' => $run, 'customer_id' => $customer_id, 'username' => $username, 'password' => $password, 'email' => $email, 'guest_email' => $manifest['guest_email'], 'products' => $manifest['products'], 'simple_url' => get_permalink($simple->get_id()), 'coupon' => $coupon->get_code(), 'checkout' => wc_get_checkout_url(), 'account' => wc_get_page_permalink('myaccount'), 'baseline' => $manifest['baseline']]);
        return;
    }

    $run = $args[1] ?? ''; $require((bool) preg_match('/^[a-f0-9]{32}$/', $run), 'A valid owned browser run is required.');
    $user = get_user_by('login', 'h72b-' . substr($run, 0, 10));
    $manifest = $user ? get_user_meta($user->ID, '_commerce_h72_browser_manifest', true) : null;
    $require(is_array($manifest) && $run === ($manifest['run'] ?? '') && $user->ID === $manifest['customer_id'] && str_ends_with($user->user_email, '@example.test'), 'Fresh owned customer manifest required.');
    $owned = static function ($order) use ($manifest, $run, $require) {
        $require($order instanceof WC_Order && !in_array($order->get_id(), $manifest['prior_order_ids'], true), 'Historical orders cannot be adopted or mutated.');
        $require('store-api' === $order->get_created_via() && 'bacs' === $order->get_payment_method() && !$order->get_transaction_id() && !$order->get_refunds(), 'Only synthetic native BACS orders without monetary operations are owned.');
        $require(in_array($order->get_billing_email(), [$manifest['email'], $manifest['guest_email']], true) && 'Prueba' === $order->get_billing_first_name() && 'H7.2 Browser' === $order->get_billing_last_name(), 'Exact synthetic purchaser required.');
        $require(in_array($order->get_customer_id(), [0, $manifest['customer_id']], true) && count($order->get_items()) > 0, 'Owned guest or customer order required.');
        foreach ($order->get_items() as $item) { $require(in_array($item->get_variation_id() ?: $item->get_product_id(), [$manifest['products']['simple'], $manifest['products']['variation']], true), 'Every line item must be a fresh owned product.'); }
    };
    if ('adopt' === $mode) {
        $id = absint($args[2] ?? 0); $order = wc_get_order($id); $owned($order);
        $require($order->has_status('on-hold') && !$order->is_paid() && !$order->get_date_paid(), 'Adoption happens only immediately after unpaid BACS checkout.');
        $order->update_meta_data('_commerce_h72_browser_run', $run); $order->save();
        if (!in_array($id, $manifest['orders'], true)) { $manifest['orders'][] = $id; update_user_meta($user->ID, '_commerce_h72_browser_manifest', $manifest); }
    }
    $orders = [];
    foreach ($manifest['orders'] as $id) {
        $order = wc_get_order($id); $owned($order); $require($run === $order->get_meta('_commerce_h72_browser_run'), 'Order run marker must match.');
        $items = array_values(array_map(static fn($item) => ['product_id' => $item->get_product_id(), 'variation_id' => $item->get_variation_id(), 'name' => $item->get_name(), 'quantity' => $item->get_quantity(), 'subtotal' => (float) $item->get_subtotal(), 'total' => (float) $item->get_total(), 'variant' => array_values(array_map(static fn($meta) => (string) $meta->value, $item->get_formatted_meta_data()))], $order->get_items()));
        $orders[] = ['id' => $id, 'status' => $order->get_status(), 'customer_id' => $order->get_customer_id(), 'method' => $order->get_payment_method(), 'currency' => $order->get_currency(), 'total' => (float) $order->get_total(), 'shipping' => (float) $order->get_shipping_total(), 'discount' => (float) $order->get_discount_total(), 'is_paid' => $order->is_paid(), 'has_date_paid' => (bool) $order->get_date_paid(), 'stock_reduced' => (bool) $order->get_data_store()->get_stock_reduced($id), 'data_store' => $order->get_data_store()->get_current_class_name(), 'items' => $items];
    }
    if ('links' === $mode) {
        $links = [];
        foreach ($manifest['orders'] as $id) { $order = wc_get_order($id); $owned($order); $links[] = ['id' => $id, 'received' => $order->get_checkout_order_received_url(), 'account' => $order->get_view_order_url(), 'admin' => admin_url('admin.php?page=wc-orders&action=edit&id=' . $id)]; }
        $emit(['run' => $run, 'private_links' => $links]); return;
    }
    if ('cleanup' === $mode) {
        $coupon = new WC_Coupon($manifest['coupon_id']); $require($run === $coupon->get_meta('_commerce_h72_browser_run'), 'Only the fresh owned coupon can be retired.');
        wp_update_post(['ID' => $coupon->get_id(), 'post_status' => 'draft']);
    }
    $current = $fingerprints($manifest['prior_order_ids'], $manifest['prior_product_ids']);
    $stock = []; foreach (['simple', 'variation'] as $type) { $product = wc_get_product($manifest['products'][$type]); $require($product && $run === $product->get_meta('_commerce_h72_browser_run'), 'Only fresh owned stock is inspected.'); $stock[$type] = (int) $product->get_stock_quantity(); }
    $drafts = [];
    foreach ([$manifest['email'], $manifest['guest_email']] as $controlled_email) {
        foreach (wc_get_orders(['limit' => -1, 'status' => 'checkout-draft', 'billing_email' => $controlled_email]) as $draft) {
            if (in_array($draft->get_id(), $manifest['prior_order_ids'], true)) { continue; }
            $drafts[] = ['id' => $draft->get_id(), 'status' => $draft->get_status(), 'stock_reduced' => (bool) $draft->get_data_store()->get_stock_reduced($draft->get_id())];
        }
    }
    $emit(['result' => 'passed', 'run' => $run, 'orders' => $orders, 'stock' => $stock, 'owned_drafts' => $drafts, 'history_unchanged' => $current['orders'] === $manifest['baseline']['orders'], 'catalog_unchanged' => $current['catalog'] === $manifest['baseline']['catalog'], 'sensitive_unchanged' => $current['sensitive'] === $manifest['baseline']['sensitive'], 'checkout_pro_disabled' => true, 'hpos' => true, 'customer_role' => in_array('customer', $user->roles, true), 'coupon_retired' => 'draft' === get_post_status($manifest['coupon_id'])]);
} catch (Throwable $error) {
    $emit(['result' => 'failed', 'mode' => $mode, 'error' => 'Owned local browser fixture guard failed; no private values or provider exception text emitted.']); exit(1);
}
