<?php
/** Guarded local content migration. Commerce behavior stays in WooCommerce. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'configure', 'verify', 'recover', 'refine'], true) || !class_exists('WooCommerce')) { WP_CLI::error('Use inspect|configure|verify|recover|refine with the existing WooCommerce demo.'); }
$source = '/opt/demo-cart-checkout/';
$relations = json_decode(file_get_contents($source . 'cross-sells.json'), true, 512, JSON_THROW_ON_ERROR);
$cart_source = str_replace(["\r\n", "\r"], "\n", file_get_contents($source . 'cart.html'));
$cart_content = str_replace('href="/?post_type=product"', 'href="' . esc_url(wc_get_page_permalink('shop')) . '"', trim($cart_source));
$source_hash = hash('sha256', $cart_content . wp_json_encode($relations));
$state = get_option('trama_h6_1', []);
$h5 = get_option('trama_h5_catalog', []);
$cart_id = wc_get_page_id('cart'); $checkout_id = wc_get_page_id('checkout');

// Exactly the approved H5 fingerprint, retained for a deliberate cross-sell-only migration.
function h6_1_product_hash($product, $original_cross_sells = null) {
    if (!$product) { return ''; }
    $data = $product->get_data();
    // Variations inherit cross-sells from the stored parent. Resetting a parent
    // clone alone cannot reconstruct their original view; normalize this one
    // explicitly migrated field throughout its inherited variation tree.
    if (null !== $original_cross_sells) { $data['cross_sell_ids'] = $original_cross_sells; }
    unset($data['date_created'], $data['date_modified'], $data['sales'], $data['total_sales'], $data['rating_counts'], $data['average_rating'], $data['review_count'], $data['meta_data']);
    $data['attributes'] = array_map(static fn($a) => $a instanceof WC_Product_Attribute ? $a->get_data() : $a, $data['attributes']);
    $data['care'] = $product->get_meta('_cb_care'); $data['guide'] = $product->get_meta('_cb_size_guide'); $data['children'] = [];
    foreach ($product->get_children() as $id) { $data['children'][$id] = h6_1_product_hash(wc_get_product($id), $original_cross_sells); }
    return hash('sha256', wp_json_encode($data));
}
function h6_1_environment_hash() {
    global $wpdb;
    $options = [];
    foreach (['woocommerce_enable_guest_checkout', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_registration_generate_password', 'woocommerce_checkout_phone_field', 'woocommerce_checkout_company_field', 'woocommerce_checkout_address_2_field', 'woocommerce_ship_to_destination', 'woocommerce_hide_out_of_stock_items', 'woocommerce_custom_orders_table_enabled', 'woocommerce_bacs_settings', 'woocommerce_cheque_settings', 'woocommerce_cod_settings'] as $name) { $options[$name] = get_option($name); }
    // Hash, never return gateway settings (which may contain credentials).
    $options['gateway_options'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_woo-mercado-pago%' ORDER BY option_name", ARRAY_A);
    $options['zones'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones ORDER BY zone_id", ARRAY_A);
    $options['methods'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods ORDER BY instance_id", ARRAY_A);
    $options['shipping_settings'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name REGEXP '^woocommerce_(flat_rate|free_shipping|local_pickup)_[0-9]+_settings$' ORDER BY option_name", ARRAY_A);
    return hash('sha256', wp_json_encode($options));
}
function h6_1_checkout_actions($blocks, $cart_id, &$count) {
    foreach ($blocks as &$block) {
        if ('woocommerce/checkout-actions-block' === $block['blockName']) { $block['attrs']['showReturnToCart'] = true; $block['attrs']['cartPageId'] = $cart_id; ++$count; }
        if ($block['innerBlocks']) { $block['innerBlocks'] = h6_1_checkout_actions($block['innerBlocks'], $cart_id, $count); }
    }
    return $blocks;
}
// Recovery only before any page migration, only if every original commercial
// fingerprint can be reconstructed by changing cross_sell_ids alone.
if ('recover' === $mode) {
    if (($state['phase'] ?? '') !== 'preparing') { WP_CLI::error('Only an interrupted preparing migration can be recovered.'); }
    foreach ($state['pages'] as $id => $page) {
        if (get_post_field('post_content', $id) !== $page['before_content'] || get_the_title($id) !== $page['before_title']) { WP_CLI::error('Pages changed; preserve and review manually.'); }
    }
    foreach ($state['products'] as $snapshot) {
        $product = wc_get_product($snapshot['id']);
        if (!in_array($product->get_cross_sell_ids(), [$snapshot['before_ids'], $snapshot['after_ids']], true) || h6_1_product_hash($product, $snapshot['before_ids']) !== $snapshot['before_hash']) { WP_CLI::error('Product changed beyond this migration; preserve and review manually.'); }
    }
    foreach ($state['products'] as $snapshot) {
        $product = wc_get_product($snapshot['id']);
        if ($product->get_cross_sell_ids() !== $snapshot['before_ids']) { $product->set_cross_sell_ids($snapshot['before_ids']); $product->save(); }
        if (h6_1_product_hash(wc_get_product($snapshot['id']), $snapshot['before_ids']) !== $snapshot['before_hash']) { WP_CLI::error('Original fingerprint not restored; snapshot preserved.'); }
    }
    delete_option('trama_h6_1');
    echo wp_json_encode(['result' => 'passed', 'recovered' => 'Only pending cross-sell relationships; original commercial fingerprints restored.']) . "\n";
    return;
}
$conflicts = [];
foreach ($h5['products'] ?? [] as $sku => $saved) {
    if (h6_1_product_hash(wc_get_product($saved['id'])) !== $saved['hash']) { $conflicts[] = 'Approved product changed: ' . $sku; }
}
if (count($h5['products'] ?? []) !== 16) { $conflicts[] = 'Expected the approved 16-product H5 catalog.'; }
$resolved = [];
foreach ($relations as $sku => $targets) {
    $id = wc_get_product_id_by_sku($sku);
    if (!$id || ($h5['products'][$sku]['id'] ?? 0) !== $id) { $conflicts[] = 'Unmanaged SKU: ' . $sku; continue; }
    foreach ($targets as $target) {
        $target_id = wc_get_product_id_by_sku($target);
        if (!$target_id || ($h5['products'][$target]['id'] ?? 0) !== $target_id || $target_id === $id) { $conflicts[] = 'Invalid cross-sell target: ' . $target; }
        $resolved[$sku][] = $target_id;
    }
}
if (!$state) {
    foreach ([$cart_id => '2122f473ab3497680baf0341591a19f409bfa52713a7d49404c9bffe5149227e', $checkout_id => '5beaa3e7a0b206e553928e70dbb82b0d03aea5d0ef911d06e496a106eb4bcf62'] as $id => $hash) {
        if (hash('sha256', (string) get_post_field('post_content', $id)) !== $hash) { $conflicts[] = 'Cart/checkout content was edited; preserve it and prepare a reviewed migration: ' . $id; }
    }
    if (!in_array(get_the_title($cart_id), ['Cart', 'Carrito'], true) || !in_array(get_the_title($checkout_id), ['Checkout', 'Finalizar compra'], true)) { $conflicts[] = 'Commerce page titles were edited.'; }
} else {
    if (($state['phase'] ?? '') !== 'complete' || (($state['source_hash'] ?? '') !== $source_hash && 'refine' !== $mode)) { $conflicts[] = 'H6.1 migration incomplete or fixture source changed; preserve and inspect.'; }
    foreach ($state['pages'] as $id => $page) {
        if (hash('sha256', get_post_field('post_content', $id)) !== $page['after_hash'] || get_the_title($id) !== $page['after_title']) { $conflicts[] = 'Migrated commerce page edited: ' . $id; }
    }
    if (hash('sha256', get_post_field('post_content', get_option('page_on_front'))) !== $state['home_hash']) { $conflicts[] = 'Home content changed after H6.1.'; }
    if (h6_1_environment_hash() !== $state['environment_hash']) { $conflicts[] = 'Checkout/shipping/payment settings changed after H6.1.'; }
    foreach ($state['products'] as $sku => $snapshot) {
        $product = wc_get_product($snapshot['id']);
        if ($product->get_cross_sell_ids() !== $snapshot['after_ids']) { $conflicts[] = 'Cross-sells edited: ' . $sku; }
        if (h6_1_product_hash($product, $snapshot['before_ids']) !== $snapshot['before_hash']) { $conflicts[] = 'Product changed beyond cross-sells: ' . $sku; }
    }
}
if ($conflicts) { echo wp_json_encode(['result' => 'conflict', 'conflicts' => $conflicts], JSON_PRETTY_PRINT) . "\n"; exit(1); }
// Deliberate content-only refinement while H6.1 is under development. It must
// never reapply product relationships or overwrite manually changed pages.
if ('refine' === $mode) {
    if (!$state || ($state['source_hash'] ?? '') === $source_hash) { WP_CLI::error('Refine requires a complete migration and a changed source.'); }
    foreach ($resolved as $sku => $ids) { if ($ids !== $state['products'][$sku]['after_ids']) { WP_CLI::error('Refine cannot change product relationships.'); } }
    $state['refinements'][] = ['source_hash' => $state['source_hash'], 'cart_content' => get_post_field('post_content', $cart_id), 'cart_hash' => $state['pages'][$cart_id]['after_hash']];
    $result = wp_update_post(wp_slash(['ID' => $cart_id, 'post_content' => $cart_content]), true);
    if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
    $state['pages'][$cart_id]['after_hash'] = hash('sha256', $cart_content);
    $state['source_hash'] = $source_hash; update_option('trama_h6_1', $state, false);
}
if ('configure' === $mode && !$state) {
    $count = 0; $checkout_before = get_post_field('post_content', $checkout_id);
    $checkout_after = serialize_blocks(h6_1_checkout_actions(parse_blocks($checkout_before), $cart_id, $count));
    if (1 !== $count) { WP_CLI::error('Expected exactly one native Checkout Actions block.'); }
    $state = ['phase' => 'preparing', 'source_hash' => $source_hash, 'backup_id' => $args[1] ?? '', 'home_hash' => hash('sha256', get_post_field('post_content', get_option('page_on_front'))), 'environment_hash' => h6_1_environment_hash(), 'pages' => [], 'products' => []];
    foreach ([$cart_id => [$cart_content, 'Carrito'], $checkout_id => [$checkout_after, 'Finalizar compra']] as $id => [$after, $title]) {
        $state['pages'][$id] = ['before_content' => get_post_field('post_content', $id), 'before_title' => get_the_title($id), 'after_hash' => hash('sha256', $after), 'after_title' => $title];
    }
    foreach ($resolved as $sku => $ids) {
        $product = wc_get_product($h5['products'][$sku]['id']);
        $state['products'][$sku] = ['id' => $product->get_id(), 'before_ids' => $product->get_cross_sell_ids(), 'after_ids' => $ids, 'before_hash' => h6_1_product_hash($product)];
    }
    update_option('trama_h6_1', $state, false);
    foreach ($resolved as $sku => $ids) {
        $product = wc_get_product($state['products'][$sku]['id']); $product->set_cross_sell_ids($ids); $product->save();
        if (h6_1_product_hash(wc_get_product($product->get_id()), $state['products'][$sku]['before_ids']) !== $state['products'][$sku]['before_hash']) { WP_CLI::error('Unexpected mutation; migration snapshot and backup preserved.'); }
        // The variation datastore retains parent_data for this request. Record
        // the explicit new inherited view, then verify it in a fresh CLI run.
        $h5['products'][$sku]['hash'] = h6_1_product_hash(wc_get_product($product->get_id()), $ids);
        $state['products'][$sku]['after_hash'] = $h5['products'][$sku]['hash'];
    }
    foreach ([$cart_id => [$cart_content, 'Carrito'], $checkout_id => [$checkout_after, 'Finalizar compra']] as $id => [$content, $title]) {
        $result = wp_update_post(wp_slash(['ID' => $id, 'post_title' => $title, 'post_content' => $content]), true);
        if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
    }
    // Only the explicitly migrated rows receive new expected H5 fingerprints.
    update_option('trama_h5_catalog', $h5, false);
    $state['phase'] = 'complete'; update_option('trama_h6_1', $state, false);
}
$checks = [];
$checks['guest_checkout'] = 'yes' === get_option('woocommerce_enable_guest_checkout');
$checks['account_optional'] = 'yes' === get_option('woocommerce_enable_signup_and_login_from_checkout');
$checks['hpos'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$checks['native_cart'] = has_block('woocommerce/cart', get_post_field('post_content', $cart_id));
$checks['native_checkout'] = has_block('woocommerce/checkout', get_post_field('post_content', $checkout_id));
$checks['migration_complete'] = ($state['phase'] ?? '') === 'complete';
$checks['cross_sells_collection'] = str_contains(get_post_field('post_content', $cart_id), 'woocommerce/product-collection/cross-sells');
$checks['carousel'] = str_contains(get_post_field('post_content', $cart_id), '"type":"carousel"');
$checks['native_return_to_cart'] = str_contains(get_post_field('post_content', $checkout_id), '"showReturnToCart":true');
$checks['home_preserved'] = !$state || hash('sha256', get_post_field('post_content', get_option('page_on_front'))) === $state['home_hash'];
$checks['settings_preserved'] = !$state || h6_1_environment_hash() === $state['environment_hash'];
$report_relations = [];
foreach ($relations as $sku => $targets) {
    $product = wc_get_product(wc_get_product_id_by_sku($sku));
    $checks['cross_sells_' . $sku] = $product->get_cross_sell_ids() === $resolved[$sku];
    $report_relations[$product->get_name()] = array_map(static fn($target) => wc_get_product(wc_get_product_id_by_sku($target))->get_name(), $targets);
}
$report = ['result' => !$state && 'inspect' === $mode ? 'ready' : (!in_array(false, $checks, true) ? 'passed' : 'failed'), 'checks' => $checks, 'cross_sells' => $report_relations, 'urls' => ['cart' => wc_get_cart_url(), 'checkout' => wc_get_checkout_url(), 'account' => wc_get_page_permalink('myaccount')]];
echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if ('failed' === $report['result']) { exit(1); }
