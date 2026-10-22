<?php
/** Read-only preservation baseline for the focused final H8.1 audit. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
$mode = $args[0] ?? 'verify';
$path = '/backups/h8-1-final-audit-20261021.json';
$hash = static fn($value) => hash('sha256', wp_json_encode($value));
$require = static function ($ok) { if (!$ok) { WP_CLI::error('Final audit guard failed; no private values printed.'); } };
$require(in_array($mode, ['snapshot', 'verify'], true));
$fixture = json_decode(file_get_contents('/backups/h8-1-qa-fixtures.json'), true);
$qa_ids = array_column($fixture['customers'], 'id');
foreach ($fixture['customers'] as $case => $entry) {
    $require($case === get_user_meta($entry['id'], '_commerce_h81_qa', true) && ['customer'] === get_userdata($entry['id'])->roles);
}
$capture = static function ($ids) use ($hash) {
    $out = [];
    foreach ($ids['orders'] as $id) {
        $order = wc_get_order($id);
        $data = $order ? $order->get_data() : null;
        if ($order) {
            $data['meta_data'] = array_map(static fn($m) => $m->get_data(), $order->get_meta_data());
            $data['items'] = array_map(static fn($i) => $i->get_data(), $order->get_items(['line_item', 'shipping', 'fee', 'coupon', 'tax']));
            $data['notes'] = array_map(static fn($n) => [$n->id, $n->content], wc_get_order_notes(['order_id' => $id]));
        }
        $out['orders'][$id] = $hash($data);
    }
    foreach ($ids['catalog'] as $id) {
        $object = 'shop_coupon' === get_post_type($id) ? new WC_Coupon($id) : wc_get_product($id);
        $data = $object ? $object->get_data() : null;
        if ($data && isset($data['attributes'])) { $data['attributes'] = array_map(static fn($a) => $a instanceof WC_Product_Attribute ? $a->get_data() : $a, $data['attributes']); }
        $out['catalog'][$id] = $hash($data);
    }
    foreach ($ids['users'] as $id) {
        $user = get_userdata($id);
        $addresses = array_filter(get_user_meta($id), static fn($key) => str_starts_with($key, 'billing_') || str_starts_with($key, 'shipping_'), ARRAY_FILTER_USE_KEY);
        $out['users'][$id] = $hash([$user->user_email, $user->display_name, $user->first_name, $user->last_name, $user->roles, $addresses]);
    }
    $settings = [];
    foreach (['active_plugins', 'stylesheet', 'template', 'trama_h5_catalog', 'trama_h6_1', 'trama_h6_2', 'woocommerce_bacs_settings', 'woocommerce_bacs_accounts', 'woocommerce_woo-mercado-pago-basic_settings', 'woocommerce_allowed_countries', 'woocommerce_specific_allowed_countries', 'woocommerce_ship_to_countries', 'woocommerce_specific_ship_to_countries', 'commerce_account_show_downloads'] as $name) { $settings[$name] = get_option($name); }
    foreach (WC()->mailer()->get_emails() as $email) { $settings['email_' . $email->id] = get_option('woocommerce_' . $email->id . '_settings'); }
    $out['settings'] = $hash($settings);
    $out['pages'] = $hash(array_map(static fn($p) => [$p->ID, $p->post_status, $p->post_content], get_posts(['post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC'])));
    return $out;
};
if ('snapshot' === $mode) {
    $require(!file_exists($path));
    $ids = [
        'orders' => wc_get_orders(['limit' => -1, 'return' => 'ids', 'type' => ['shop_order', 'shop_order_refund'], 'status' => array_merge(array_keys(wc_get_order_statuses()), ['wc-checkout-draft'])]),
        'catalog' => get_posts(['post_type' => ['product', 'product_variation', 'shop_coupon'], 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']),
        'users' => array_values(array_diff(get_users(['fields' => 'ID']), $qa_ids)),
    ];
    file_put_contents($path, wp_json_encode(['ids' => $ids, 'hashes' => $capture($ids)]));
    echo wp_json_encode(['result' => 'passed', 'baseline_counts' => array_map('count', $ids), 'qa_users_only_excluded' => count($qa_ids)]) . "\n"; return;
}
$baseline = json_decode(file_get_contents($path), true);
$after = $capture($baseline['ids']); $checks = [];
foreach ($baseline['hashes'] as $key => $before) { $checks[$key . '_strictly_preserved'] = $before === $after[$key]; }
$checks['commerce_core_active'] = is_plugin_active('commerce-core/commerce-core.php');
$checks['integrity_active'] = is_plugin_active('commerce-order-integrity/commerce-order-integrity.php');
$checks['communications_active'] = is_plugin_active('commerce-communications/commerce-communications.php');
$checks['checkout_pro_disabled'] = 'yes' !== (get_option('woocommerce_woo-mercado-pago-basic_settings')['enabled'] ?? '');
$checks['bacs_available'] = 'yes' === (get_option('woocommerce_bacs_settings')['enabled'] ?? '');
$passed = !in_array(false, $checks, true);
echo wp_json_encode(['result' => $passed ? 'passed' : 'failed', 'checks' => $checks, 'baseline_counts' => array_map('count', $baseline['ids']), 'normalizations_or_exceptions' => false], JSON_PRETTY_PRINT) . "\n";
if (!$passed) { exit(1); }
