<?php
/** Explicit, guarded BACS demo configuration. No Mercado Pago credentials write. */
require_once __DIR__ . '/h7-1-functions.php';
require_once __DIR__ . '/h6-2-functions.php';
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'configure', 'verify', 'snapshot-linking', 'record-linking'], true)) { WP_CLI::error('Use inspect|configure|verify|snapshot-linking|record-linking.'); }
$state = (array) get_option('trama_h7_1', []);
$bacs = (array) get_option('woocommerce_bacs_settings', []);
$accounts = get_option('woocommerce_bacs_accounts', []);
$expected = h7_1_demo_bacs();
$conflicts = [];
if ('snapshot-linking' === $mode) {
    h7_1_validate_backup($args[1] ?? '');
    $guard = WP_CLI::runcommand('eval-file /opt/local-tools/h6-2-shipping.php verify --quiet', ['return' => 'all', 'exit_error' => false]);
    if ($guard->return_code || 'complete' !== ($state['phase'] ?? '') || get_option('trama_h7_1_linking_snapshot')) { WP_CLI::error('Need completed H7.1, passing H6 guards and no existing linking snapshot.'); }
    update_option('trama_h7_1_linking_snapshot', ['created_at_utc' => gmdate('c'), 'non_mp_hash' => h7_1_non_mp_environment_hash(), 'h61_hash' => h7_1_hash(get_option('trama_h6_1')), 'backup_id' => $args[1], 'mp_hash' => h7_1_mp_snapshot()], false);
    h7_1_emit(['result' => 'passed', 'snapshot_recorded' => true, 'gateway_or_credential_values_written' => false]); return;
}
if ('record-linking' === $mode) {
    $snapshot = get_option('trama_h7_1_linking_snapshot'); $h61 = get_option('trama_h6_1');
    if ('complete' !== ($state['phase'] ?? '') || !$snapshot || $snapshot['non_mp_hash'] !== h7_1_non_mp_environment_hash() || $snapshot['h61_hash'] !== h7_1_hash($h61)) { WP_CLI::error('Changes exceed the explicit Mercado Pago linking scope; preserve configuration and snapshot.'); }
    $guard = WP_CLI::runcommand('eval-file /opt/local-tools/h5-catalog.php verify --quiet', ['return' => 'all', 'exit_error' => false]);
    if ($guard->return_code) { WP_CLI::error('Catalog guard changed during linking; preserve snapshot.'); }
    $h61['environment_hash'] = h6_2_h61_environment_hash(); update_option('trama_h6_1', $h61, false);
    $state['linking_records'][] = ['at_utc' => gmdate('c'), 'snapshot_at_utc' => $snapshot['created_at_utc'], 'backup_id' => $snapshot['backup_id'], 'mp_configuration_changed' => $snapshot['mp_hash'] !== h7_1_mp_snapshot()];
    update_option('trama_h7_1', $state, false); delete_option('trama_h7_1_linking_snapshot');
    h7_1_emit(['result' => 'passed', 'only_approved_fingerprint_updated' => true, 'gateway_or_credential_values_written' => false, 'mercado_pago' => h7_1_mp_report()]); return;
}
if ('configure' === $mode) {
    // Re-running an older milestone after H7 must never reset payment settings.
    // Require its existing protection, then update only the explicitly changed
    // environment fingerprint after the BACS write, retaining its prior hash.
    foreach (['h5-catalog.php', 'h6-1-cart-checkout.php', 'h6-2-shipping.php'] as $tool) {
        $guard = WP_CLI::runcommand('eval-file /opt/local-tools/' . $tool . ' verify --quiet', ['return' => 'all', 'exit_error' => false]);
        if ($guard->return_code) { $conflicts[] = 'Approved fixture guard failed: ' . $tool . '; preserve and inspect.'; }
    }
    if ($state) {
        if ('complete' !== ($state['phase'] ?? '') || h7_1_hash($bacs) !== ($state['bacs_after_hash'] ?? '') || h7_1_hash($accounts) !== ($state['accounts_hash'] ?? '')) { $conflicts[] = 'BACS was edited after H7.1; preserve merchant configuration.'; }
    } else {
        // No existing banking fields or custom copy is replaced, even if the
        // gateway happens to be disabled. Empty native defaults are allowed.
        $native = new WC_Gateway_BACS();
        foreach ($bacs as $key => $value) {
            if ('enabled' === $key && in_array($value, ['', 'no'], true)) { continue; }
            $default = $native->form_fields[$key]['default'] ?? '';
            if (h7_1_filled($value) && $value !== $default) { $conflicts[] = 'Existing BACS configuration requires manual review; no values disclosed or overwritten.'; break; }
        }
        if ('yes' === ($bacs['enabled'] ?? 'no') || h7_1_filled($accounts)) { $conflicts[] = 'An enabled gateway or banking data already exists; preserve it.'; }
    }
    if ($conflicts) { h7_1_emit(['result' => 'conflict', 'conflicts' => $conflicts]); }
    if (!$state) {
        $backup_id = $args[1] ?? ''; h7_1_validate_backup($backup_id);
        $h61 = get_option('trama_h6_1');
        $mp_before = h7_1_mp_snapshot();
        $state = ['phase' => 'preparing', 'backup_id' => $backup_id, 'created_at_utc' => gmdate('c'), 'bacs_before_hash' => h7_1_hash($bacs), 'accounts_hash' => h7_1_hash($accounts), 'h61_environment_before_hash' => $h61['environment_hash']];
        update_option('trama_h7_1', $state, false);
        update_option('woocommerce_bacs_settings', array_replace($bacs, $expected), false);
        // Never writes banking accounts or any Mercado Pago option.
        if ($mp_before !== h7_1_mp_snapshot() || h7_1_hash(get_option('woocommerce_bacs_accounts', [])) !== $state['accounts_hash']) { WP_CLI::error('Unexpected payment configuration mutation; preserve backup and migration state.'); }
        $h61['environment_hash'] = h6_2_h61_environment_hash();
        $h61['payment_migration'] = ['snapshot_option' => 'trama_h7_1', 'scope' => 'Native BACS demo settings only; Mercado Pago and banking accounts preserved.'];
        update_option('trama_h6_1', $h61, false);
        $state['bacs_after_hash'] = h7_1_hash(get_option('woocommerce_bacs_settings'));
        $state['phase'] = 'complete'; update_option('trama_h7_1', $state, false);
        $bacs = (array) get_option('woocommerce_bacs_settings', []);
    } elseif (($bacs['instructions'] ?? '') === 'TIENDA DE DEMOSTRACIÓN — No realizar transferencias reales. Tu pedido quedó registrado y el pago está pendiente de verificación. Esta tienda no tiene una cuenta bancaria operativa.') {
        // Explicit refinement of this milestone's exact owned copy. Native
        // BACS instructions also appear after manual processing, so their
        // content must remain true for both unpaid and confirmed orders.
        $legacy = array_replace($expected, ['instructions' => $bacs['instructions']]);
        if (array_diff_assoc($legacy, $bacs)) { WP_CLI::error('Legacy copy differs beyond the approved instructions; preserve it.'); }
        h7_1_validate_backup($state['backup_id']);
        $h61 = get_option('trama_h6_1'); $linking = get_option('trama_h7_1_linking_snapshot');
        $can_refine_linking = $linking && $linking['non_mp_hash'] === h7_1_non_mp_environment_hash() && $linking['h61_hash'] === h7_1_hash($h61);
        $mp_before = h7_1_mp_snapshot();
        $before_hash = h7_1_hash($bacs);
        $bacs['instructions'] = $expected['instructions'];
        update_option('woocommerce_bacs_settings', $bacs, false);
        if ($mp_before !== h7_1_mp_snapshot() || h7_1_hash(get_option('woocommerce_bacs_accounts', [])) !== $state['accounts_hash']) { WP_CLI::error('Unexpected mutation; preserve migration evidence.'); }
        $h61['environment_hash'] = h6_2_h61_environment_hash(); update_option('trama_h6_1', $h61, false);
        $state['bacs_after_hash'] = h7_1_hash($bacs);
        $state['refinements'][] = ['at_utc' => gmdate('c'), 'scope' => 'Owned demo instructions become independent of order status.', 'before_hash' => $before_hash, 'after_hash' => $state['bacs_after_hash']];
        update_option('trama_h7_1', $state, false);
        if ($can_refine_linking) {
            $linking['non_mp_hash'] = h7_1_non_mp_environment_hash(); $linking['h61_hash'] = h7_1_hash($h61); $linking['approved_bacs_instructions_refinement'] = true;
            update_option('trama_h7_1_linking_snapshot', $linking, false);
        }
    }
}
// Gateway instances may predate the explicit option migration in this process.
$native = new WC_Gateway_BACS();
$mp = h7_1_mp_report();
$checks = ['hpos' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'guest_checkout' => 'yes' === get_option('woocommerce_enable_guest_checkout'), 'ars' => 'ARS' === get_woocommerce_currency(), 'native_cart_block' => has_block('woocommerce/cart', get_post_field('post_content', wc_get_page_id('cart'))), 'native_checkout_block' => has_block('woocommerce/checkout', get_post_field('post_content', wc_get_page_id('checkout'))), 'official_mp_plugin' => $mp['official_plugin_active'], 'official_checkout_pro_registered' => $mp['official_checkout_pro_registered'], 'mp_blocks_class_present' => $mp['checkout_blocks_class_present'], 'other_mp_checkouts_disabled' => !$mp['other_mp_gateways_enabled']];
if ('inspect' !== $mode) {
    $checks['bacs_migration_complete'] = 'complete' === ($state['phase'] ?? '');
    $checks['native_bacs_enabled'] = 'yes' === $native->enabled;
    $checks['bacs_demo_copy'] = !array_diff_assoc($expected, $bacs);
    $checks['no_operational_bank_details'] = !h7_1_filled($accounts);
}
$result = in_array(false, $checks, true) ? 'failed' : ('inspect' === $mode && !$state ? 'ready' : 'passed');
h7_1_emit(['result' => $result, 'versions' => ['wordpress' => get_bloginfo('version'), 'woocommerce' => WC_VERSION, 'php' => PHP_VERSION], 'checks' => $checks, 'bacs' => ['enabled' => 'yes' === $native->enabled, 'bank_details_present' => h7_1_filled($accounts), 'matches_demo_copy' => !array_diff_assoc($expected, $bacs), 'title_is_demo' => ($bacs['title'] ?? '') === $expected['title'], 'native_gateway_class' => get_class($native)], 'mercado_pago' => $mp, 'https' => is_ssl(), 'migration' => ['phase' => $state['phase'] ?? null, 'backup_id' => $state['backup_id'] ?? null], 'limitations' => ['This tool never links an account, enables Mercado Pago, sends a payment, refund or notification, or validates an external payment.', 'No credential format or mode label alone proves that a seller is a test account. Verify seller and buyer identities separately before external QA.']]);
