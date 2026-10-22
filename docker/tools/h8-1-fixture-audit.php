<?php
/** Read-only attribution of legacy H5/H6 hash differences. Never reseeds or replaces a baseline. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url())) { exit(1); }
$origin = json_decode(file_get_contents('/backups/h8-1-fixture-audit-origin.json'), true);
if ('h7-1-before-payments' !== $origin['backup'] || !hash_equals($origin['backup_sha256'], hash_file('sha256', '/backups/h7-1-before-payments/database.sql'))) { WP_CLI::error('Original backup integrity mismatch.'); }
$h5 = get_option('trama_h5_catalog'); $h61 = get_option('trama_h6_1'); $h62 = get_option('trama_h6_2');
$canonical = static function ($product, $stock, $cross, $forced_cross = null) use (&$canonical, $origin) {
    $data = $product->get_data(); $old = $origin['products'][$product->get_id()] ?? null;
    if ($old && $stock && is_numeric($old['stock'])) { $data['stock_quantity'] = (int) $old['stock']; }
    if ($old && $cross) {
        // Native variation reads always pull this property from parent_data.
        $cross_origin = $product->is_type('variation') ? ($origin['products'][$product->get_parent_id()] ?? $old) : $old;
        $data['cross_sell_ids'] = null === $cross_origin['cross_sell_ids'] ? [] : maybe_unserialize($cross_origin['cross_sell_ids']);
    }
    if (null !== $forced_cross) { $data['cross_sell_ids'] = $forced_cross; }
    unset($data['date_created'], $data['date_modified'], $data['sales'], $data['total_sales'], $data['rating_counts'], $data['average_rating'], $data['review_count'], $data['meta_data']);
    $data['attributes'] = array_map(static fn($a) => $a instanceof WC_Product_Attribute ? $a->get_data() : $a, $data['attributes']);
    $data['care'] = $product->get_meta('_cb_care'); $data['guide'] = $product->get_meta('_cb_size_guide'); $data['children'] = [];
    foreach ($product->get_children() as $id) { $data['children'][$id] = $canonical(wc_get_product($id), $stock, $cross, $forced_cross); }
    return hash('sha256', wp_json_encode($data));
};
$rows = []; $counts = ['live_matches' => 0, 'stock_only_matches' => 0, 'cross_only_matches' => 0, 'stock_and_cross_matches' => 0, 'h62_reconstructed_matches' => 0, 'h61_before_reconstructed_matches' => 0];
foreach ($h5['products'] as $sku => $saved) {
    $product = wc_get_product($saved['id']);
    $row = ['sku' => $sku];
    foreach (['live' => [false, false], 'stock_only' => [true, false], 'cross_only' => [false, true], 'stock_and_cross' => [true, true]] as $case => [$stock, $cross]) {
        $row[$case . '_matches'] = $saved['hash'] === $canonical($product, $stock, $cross);
        $counts[$case . '_matches'] += (int) $row[$case . '_matches'];
    }
    $row['h62_reconstructed_matches'] = $h62['products'][$sku]['after_hash'] === $canonical($product, true, true);
    $counts['h62_reconstructed_matches'] += (int) $row['h62_reconstructed_matches'];
    if (isset($h61['products'][$sku])) {
        $snap = $h61['products'][$sku];
        $row['h61_before_reconstructed_matches'] = $snap['before_hash'] === $canonical($product, true, true, $snap['before_ids']);
        $counts['h61_before_reconstructed_matches'] += (int) $row['h61_before_reconstructed_matches'];
    }
    $rows[] = $row;
}
$stock_changes = [];
foreach ($origin['products'] as $id => $old) {
    $p = wc_get_product($id);
    if ($p && is_numeric($old['stock']) && (int) $old['stock'] !== $p->get_stock_quantity()) { $stock_changes[] = ['id' => (int) $id, 'sku' => $p->get_sku(), 'fixture_stock' => (int) $old['stock'], 'current_stock' => $p->get_stock_quantity()]; }
}
$orders = [];
foreach ([454, 455, 456, 457] as $id) {
    $o = wc_get_order($id); if (!$o) { continue; }
    $orders[] = ['id' => $id, 'status' => $o->get_status(), 'created_via' => $o->get_created_via(), 'synthetic_email' => str_ends_with($o->get_billing_email(), '@example.test'), 'manual_review_marker' => '1' === $o->get_meta('_commerce_h72_manual_review'), 'items' => array_values(array_map(static fn($i) => ['product_id' => $i->get_variation_id() ?: $i->get_product_id(), 'quantity' => $i->get_quantity(), 'reduced_stock' => $i->get_meta('_reduced_stock')], $o->get_items()))];
}
$source_matches = ['h5' => $h5['source_hash'] === hash_file('sha256', '/opt/demo-catalog/h5-catalog.json'), 'h62' => $h62['source_hash'] === hash_file('sha256', '/opt/demo-shipping/h6-2-shipping.json')];
$reduced = [];
foreach ($orders as $order) { foreach ($order['items'] as $item) { $reduced[$item['product_id']] = ($reduced[$item['product_id']] ?? 0) + (int) $item['reduced_stock']; } }
$attributed = 7 === count($stock_changes);
foreach ($stock_changes as $change) { $attributed = $attributed && $change['fixture_stock'] - $change['current_stock'] === ($reduced[$change['id']] ?? 0); }
$checks = ['all_16_h5_hashes_reconstructed_using_only_original_stock' => 16 === $counts['stock_only_matches'], 'all_16_h62_hashes_reconstructed' => 16 === $counts['h62_reconstructed_matches'], 'all_16_h61_prior_cross_sell_hashes_reconstructed' => 16 === $counts['h61_before_reconstructed_matches'], 'all_stock_changes_attributed_to_existing_order_items' => $attributed, 'fixture_sources_unchanged' => !in_array(false, $source_matches, true)];
$passed = !in_array(false, $checks, true);
echo wp_json_encode(['result' => $passed ? 'passed' : 'failed', 'checks' => $checks, 'counts' => $counts, 'parents' => $rows, 'stock_changes' => $stock_changes, 'orders' => $orders, 'sources_unchanged' => $source_matches, 'db_writes' => 0, 'original_hashes_replaced' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if (!$passed) { exit(1); }
