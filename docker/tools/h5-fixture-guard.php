<?php
/** Prove that reimport refuses a manual edit; restore the owned demo field. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8080') { exit(1); }
$product = wc_get_product(wc_get_product_id_by_sku('TRM-ACC-001'));
$state = get_option('trama_h5_catalog');
if (!$product || ($state['products']['TRM-ACC-001']['id'] ?? 0) !== $product->get_id()) { WP_CLI::error('Owned H5 fixture required.'); }
$original = $product->get_short_description();
$temporary = $original . ' [H5 guard smoke]';
$baseline = WP_CLI::runcommand('eval-file /opt/local-tools/h5-catalog.php verify --quiet', ['return' => 'all', 'exit_error' => false]);
if ($baseline->return_code !== 0) { WP_CLI::error('Resolve existing fixture differences before running the guard smoke.'); }
try {
    $product->set_short_description($temporary);
    $product->save();
    $result = WP_CLI::runcommand('eval-file /opt/local-tools/h5-catalog.php seed h5-before-catalog --quiet', ['return' => 'all', 'exit_error' => false]);
    $report = json_decode($result->stdout, true);
    if ($result->return_code !== 1 || ($report['result'] ?? '') !== 'conflict' || wc_get_product($product->get_id())->get_short_description() !== $temporary) { throw new RuntimeException('Guard did not preserve the simulated manual edit.'); }
    echo wp_json_encode(['result' => 'passed', 'manual_edit_detected' => true, 'seed_blocked' => true, 'edited_content_preserved' => true, 'restored_in_finally' => true], JSON_PRETTY_PRINT) . "\n";
} finally {
    $product->set_short_description($original);
    $product->save();
}
