<?php
/** Protected, idempotent local shipping migration; all runtime behavior is Core. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
require_once __DIR__ . '/h6-2-functions.php';
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'seed', 'verify'], true) || !class_exists('WooCommerce')) { WP_CLI::error('Use inspect|seed|verify with the existing local WooCommerce demo.'); }
$source_file = '/opt/demo-shipping/h6-2-shipping.json';
$source = json_decode(file_get_contents($source_file), true, 512, JSON_THROW_ON_ERROR);
$source_hash = hash_file('sha256', $source_file);
$state = get_option('trama_h6_2', []);
$h5 = get_option('trama_h5_catalog', []); $h61 = get_option('trama_h6_1', []);
$checkout_id = wc_get_page_id('checkout');
$conflicts = [];
// Existing guards must pass before accepting any new expected fingerprint.
foreach (['h5-catalog.php', 'h6-1-cart-checkout.php'] as $tool) {
    $result = WP_CLI::runcommand('eval-file /opt/local-tools/' . $tool . ' verify --quiet', ['return' => 'all', 'exit_error' => false]);
    if (0 !== $result->return_code) { $conflicts[] = 'Approved fixture guard failed: ' . $tool . '; ' . trim($result->stdout); }
}
if (count($h5['products'] ?? []) !== 16 || count($source['products']) !== 16 || ($h61['phase'] ?? '') !== 'complete') { $conflicts[] = 'Approved H5/H6.1 and all 16 physical product rows are required.'; }
if ('ARS' !== get_option('woocommerce_currency') || version_compare(WC_VERSION, '9.9', '<')) { $conflicts[] = 'Expected existing ARS and native hide-paid-rates support.'; }
if (!class_exists('\Automattic\WooCommerce\Blocks\Shipping\PickupLocation')) { $conflicts[] = 'Modern Blocks Pickup must be provided by installed WooCommerce.'; }
$physical_before = [];
foreach ($source['products'] as $sku => $physical) {
    $p = wc_get_product(wc_get_product_id_by_sku($sku));
    if (!$p || ($h5['products'][$sku]['id'] ?? 0) !== $p->get_id() || $p->is_virtual()) { $conflicts[] = 'Unmanaged/nonphysical product: ' . $sku; continue; }
    $physical_before[$sku] = h6_2_physical($p);
    if (!$state && array_filter($physical_before[$sku], static fn($value) => '' !== $value)) { $conflicts[] = 'Existing merchant physical data must be reviewed: ' . $sku; }
    foreach ($p->get_children() as $id) {
        $v = wc_get_product($id);
        if (array_filter(h6_2_physical($v), static fn($value) => '' !== $value)) { $conflicts[] = 'Variation overrides must be preserved and reviewed: ' . $v->get_sku(); }
    }
}
$shipping_before = h6_2_shipping_snapshot();
if ($state) {
    if (($state['phase'] ?? '') !== 'complete' || ($state['source_hash'] ?? '') !== $source_hash) { $conflicts[] = 'H6.2 source changed or migration incomplete; preserve and prepare an explicit migration.'; }
    if (h6_2_hash($shipping_before) !== ($state['shipping_after_hash'] ?? '')) { $conflicts[] = 'Shipping zones, methods, units or pickup were edited; preserve merchant configuration.'; }
    foreach ($state['products'] ?? [] as $sku => $snapshot) {
        if (h6_2_product_hash(wc_get_product($snapshot['id'])) !== $snapshot['after_hash']) { $conflicts[] = 'Product edited after H6.2: ' . $sku; }
    }
    if (hash('sha256', get_post_field('post_content', $checkout_id)) !== ($state['checkout_after_hash'] ?? '')) { $conflicts[] = 'Checkout edited after shipping migration.'; }
} else {
    // Known approved H2 demo only. No wildcard adoption of merchant zones/methods.
    $expected_zones = [['zone_id' => '1', 'zone_name' => 'H2 — Argentina', 'zone_order' => '0']];
    $expected_methods = [['zone_id' => '1', 'instance_id' => '1', 'method_id' => 'flat_rate', 'method_order' => '1', 'is_enabled' => '1'], ['zone_id' => '1', 'instance_id' => '2', 'method_id' => 'local_pickup', 'method_order' => '2', 'is_enabled' => '1']];
    $zone = new WC_Shipping_Zone(1);
    if ($shipping_before['zones'] !== $expected_zones || $shipping_before['methods'] !== $expected_methods || wp_json_encode($zone->get_zone_locations()) !== '[{"code":"AR","type":"country"}]') { $conflicts[] = 'Expected exactly the approved H2 Argentina zone; unexpected configuration is preserved.'; }
    if (get_option('woocommerce_flat_rate_1_settings') !== ['title' => 'H2 — Tarifa fija', 'cost' => '500', 'tax_status' => 'none'] || get_option('woocommerce_local_pickup_2_settings') !== ['title' => 'H2 — Retiro local', 'cost' => '0', 'tax_status' => 'none']) { $conflicts[] = 'Approved H2 method settings changed.'; }
    if (get_option('woocommerce_pickup_location_settings') !== ['enabled' => 'no', 'title' => '', 'cost' => '', 'tax_status' => 'taxable'] || get_option('pickup_location_pickup_locations') !== [] || 'no' !== get_option('woocommerce_shipping_hide_rates_when_free')) { $conflicts[] = 'Native modern pickup/free-shipping configuration already exists; preserve it.'; }
    if ('lbs' !== get_option('woocommerce_weight_unit') || 'in' !== get_option('woocommerce_dimension_unit') || 'specific' !== get_option('woocommerce_ship_to_countries') || ['AR'] !== get_option('woocommerce_specific_ship_to_countries')) { $conflicts[] = 'Expected untouched approved H2 shipping/unit defaults.'; }
    // Changing units must not reinterpret physical data on unrelated products.
    $all_product_ids = get_posts(['post_type' => ['product', 'product_variation'], 'post_status' => array_keys(get_post_stati()), 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]);
    foreach ($all_product_ids as $product_id) {
        $p = wc_get_product($product_id);
        if (!$p) { $conflicts[] = 'Unknown product type prevents a safe global unit migration: ' . $product_id; continue; }
        if (array_filter(h6_2_physical($p), static fn($value) => '' !== $value)) { $conflicts[] = 'Unit migration would reinterpret existing physical data: ' . $p->get_sku(); }
    }
}
if ($conflicts) { echo wp_json_encode(['result' => 'conflict', 'conflicts' => $conflicts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"; exit(1); }
if ('seed' === $mode && !$state) {
    $backup_id = $args[1] ?? '';
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/', $backup_id)) { WP_CLI::error('Valid backup ID required.'); }
    $backup_root = '/backups/' . $backup_id . '/';
    $manifest = is_file($backup_root . 'manifest.json') ? json_decode(file_get_contents($backup_root . 'manifest.json'), true) : null;
    if (!$manifest || ($manifest['project'] ?? '') !== 'demo-woocommerce-local' || ($manifest['backupId'] ?? '') !== $backup_id) { WP_CLI::error('Verified preventive local backup required.'); }
    foreach (['database.sql', 'wordpress.tar.gz'] as $file) {
        $entries = array_values(array_filter($manifest['files'], static fn($row) => $row['name'] === $file));
        if (count($entries) !== 1 || !is_file($backup_root . $file) || hash_file('sha256', $backup_root . $file) !== $entries[0]['sha256']) { WP_CLI::error('Backup integrity failed: ' . $file); }
    }
    $count = 0; $checkout_before = get_post_field('post_content', $checkout_id);
    $checkout_after = serialize_blocks(h6_2_checkout_attrs(parse_blocks($checkout_before), $source['checkout_shipping_method'], $count));
    if (1 !== $count) { WP_CLI::error('Expected one native Delivery selector block.'); }
    $state = ['phase' => 'preparing', 'source_hash' => $source_hash, 'backup_id' => $backup_id, 'created_at_utc' => gmdate('c'), 'shipping_before' => $shipping_before, 'shipping_before_hash' => h6_2_hash($shipping_before), 'h5_before' => $h5, 'h61_before' => $h61, 'checkout_before' => $checkout_before, 'checkout_before_hash' => hash('sha256', $checkout_before), 'products' => []];
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    try {
        foreach ($source['products'] as $sku => $physical) {
            $p = wc_get_product($h5['products'][$sku]['id']);
            $snapshot = ['id' => $p->get_id(), 'physical_before' => $physical_before[$sku], 'physical_after' => $physical, 'before_hash' => $h5['products'][$sku]['hash'], 'cross_sell_ids' => $p->get_cross_sell_ids()];
            $p->set_props($physical); $p->save(); wc_delete_product_transients($p->get_id());
            $fresh = wc_get_product($p->get_id());
            if (h6_2_product_hash($fresh, $snapshot['cross_sell_ids'], $snapshot['physical_before']) !== $snapshot['before_hash']) { throw new RuntimeException('Product mutated beyond physical fields: ' . $sku); }
            $snapshot['after_hash'] = h6_2_product_hash($fresh, $snapshot['cross_sell_ids']);
            $h5['products'][$sku]['hash'] = $snapshot['after_hash'];
            if (isset($h61['products'][$sku])) {
                $prior = $h61['products'][$sku];
                if (h6_2_product_hash($fresh, $prior['before_ids'], $snapshot['physical_before']) !== $prior['before_hash']) { throw new RuntimeException('H6.1 original cross-sell view cannot be reconstructed: ' . $sku); }
                $h61['products'][$sku]['before_hash'] = h6_2_product_hash($fresh, $prior['before_ids']);
                $h61['products'][$sku]['after_hash'] = $snapshot['after_hash'];
            }
            $state['products'][$sku] = $snapshot;
        }
        $zone = new WC_Shipping_Zone(1); $zone->set_zone_name($source['zone']['name']); $zone->save();
        update_option('woocommerce_flat_rate_1_settings', $source['flat_rate'], false);
        // Remove the known legacy zone method, replacing it with Blocks pickup.
        $zone->delete_shipping_method(2); delete_option('woocommerce_local_pickup_2_settings');
        $free_id = $zone->add_shipping_method('free_shipping');
        if (!$free_id) { throw new RuntimeException('Native Free Shipping could not be added.'); }
        update_option('woocommerce_free_shipping_' . $free_id . '_settings', $source['free_shipping'], false);
        update_option('woocommerce_weight_unit', $source['weight_unit']); update_option('woocommerce_dimension_unit', $source['dimension_unit']);
        update_option('woocommerce_shipping_hide_rates_when_free', $source['hide_paid_when_free']);
        // Shipping destinations can be consulted globally; selling stays AR.
        // Only the AR zone has delivery rates. No worldwide delivery is created.
        update_option('woocommerce_ship_to_countries', $source['shipping_countries']);
        update_option('woocommerce_pickup_location_settings', $source['pickup_settings'], false);
        update_option('pickup_location_pickup_locations', $source['pickup_locations'], false);
        $updated = wp_update_post(wp_slash(['ID' => $checkout_id, 'post_content' => $checkout_after]), true);
        if (is_wp_error($updated)) { throw new RuntimeException($updated->get_error_message()); }
        $state['checkout_after_hash'] = hash('sha256', $checkout_after);
        $h61['pages'][$checkout_id]['after_hash'] = $state['checkout_after_hash'];
        $h61['environment_hash'] = h6_2_h61_environment_hash();
        $h61['shipping_migration'] = ['source_hash' => $source_hash, 'snapshot_option' => 'trama_h6_2'];
        update_option('trama_h5_catalog', $h5, false); update_option('trama_h6_1', $h61, false);
        $state['zone_id'] = 1; $state['flat_rate_instance_id'] = 1; $state['free_shipping_instance_id'] = $free_id;
        $state['shipping_after'] = h6_2_shipping_snapshot(); $state['shipping_after_hash'] = h6_2_hash($state['shipping_after']);
        $state['phase'] = 'complete'; update_option('trama_h6_2', $state, false);
        $wpdb->query('COMMIT'); WC_Cache_Helper::get_transient_version('shipping', true);
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK'); wp_cache_flush(); WP_CLI::error('Migration rolled back; backup preserved. ' . $e->getMessage());
    }
    // Native variation/product datastores can retain parent_data in this
    // request. Certify only a fresh request after commit, using real getters.
    $fresh_verify = WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php verify --quiet', ['return' => 'all', 'exit_error' => false]);
    echo $fresh_verify->stdout;
    if ($fresh_verify->return_code) { WP_CLI::warning('Migration committed; fresh verification requires inspection. Backup and before/after snapshots are preserved.'); WP_CLI::halt($fresh_verify->return_code); }
    return;
}
$checks = []; $products = []; $inherited = 0;
foreach ($source['products'] as $sku => $physical) {
    $p = wc_get_product($h5['products'][$sku]['id']);
    $checks['physical_' . $sku] = !array_filter(array_keys($physical), static fn($key) => (float) $p->{'get_' . $key}('edit') !== (float) $physical[$key]);
    foreach ($p->get_children() as $id) {
        $v = wc_get_product($id); $valid = true;
        foreach ($physical as $field => $value) { $valid = $valid && '' === $v->{'get_' . $field}('edit') && (float) $v->{'get_' . $field}() === (float) $value; }
        $checks['inherits_' . $v->get_sku()] = $valid; $inherited += (int) $valid;
    }
    $products[$sku] = ['name' => $p->get_name(), 'physical' => h6_2_physical($p), 'variations' => count($p->get_children()), 'cross_sells' => $p->get_cross_sell_ids()];
}
$checks['migration_complete'] = ($state['phase'] ?? '') === 'complete';
$checks['units'] = 'kg' === get_option('woocommerce_weight_unit') && 'cm' === get_option('woocommerce_dimension_unit');
$checks['modern_pickup'] = \Automattic\WooCommerce\StoreApi\Utilities\LocalPickupUtils::is_local_pickup_enabled();
$checks['native_free_hides_paid'] = 'yes' === get_option('woocommerce_shipping_hide_rates_when_free');
$checks['selling_stays_argentina'] = 'specific' === get_option('woocommerce_allowed_countries') && ['AR'] === get_option('woocommerce_specific_allowed_countries');
$checks['out_of_zone_no_delivery'] = count((new WC_Shipping_Zone(0))->get_shipping_methods()) === 0;
$result = !$state && 'inspect' === $mode ? 'ready' : (!in_array(false, $checks, true) ? 'passed' : 'failed');
echo wp_json_encode(['result' => $result, 'notice' => $source['notice'], 'source_hash' => $source_hash, 'source' => $source, 'checks' => $checks, 'inherited_variations' => $inherited, 'products' => $products, 'snapshot' => ['option' => 'trama_h6_2', 'backup_id' => $state['backup_id'] ?? null, 'before_hash' => $state['shipping_before_hash'] ?? null, 'after_hash' => $state['shipping_after_hash'] ?? null], 'shipping' => h6_2_shipping_snapshot(), 'urls' => ['cart' => wc_get_cart_url(), 'checkout' => wc_get_checkout_url()]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if ('failed' === $result) { exit(1); }
