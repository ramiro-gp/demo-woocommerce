<?php
/**
 * Read-only H7.1 seller preflight. Does not enable gateways, create a preference,
 * invoke a callback, submit a payment, refund, or import any credential.
 * Usage: wp eval-file /opt/local-tools/h7-mp-seller-check.php inspect|verify-linked-test-seller [expected-seller]
 * Only the explicit second mode uses the official plugin's account reader.
 * Output is an allowlist of booleans/public version metadata, never raw identity.
 */
if (!defined('WP_CLI') || !WP_CLI || !function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'local') {
    if (defined('STDERR')) {
        fwrite(STDERR, "H7.1 seller preflight requires local WP-CLI.\n");
    }
    exit(1);
}

$mode = $args[0] ?? 'inspect';
$expectedSeller = $args[1] ?? '';
if (!in_array($mode, ['inspect', 'verify-linked-test-seller'], true)) {
    WP_CLI::error('Usage: h7-mp-seller-check.php inspect|verify-linked-test-seller');
}

$report = [
    'scope' => 'read_only_official_plugin_seller_preflight',
    'mode' => $mode,
    'payment_submission' => false,
    'webhook_delivery' => false,
    'gateway_configuration_changed' => false,
    'money_movement_requested' => false,
    'credential_values_in_output' => false,
    'identity_lookup_attempted' => false,
    'identity_lookup_ok' => false,
    'linked_account_is_test_user' => false,
    'linked_account_is_argentina' => false,
    'linked_account_matches_plugin_collector' => false,
    'expected_seller_matches' => false,
    'safe_to_start_test_checkout' => false,
];

try {
    $mp = $GLOBALS['mercadopago'] ?? null;
    if (!$mp || !class_exists('WooCommerce')) {
        throw new RuntimeException('Official plugins unavailable.');
    }

    $gateways = WC()->payment_gateways()->payment_gateways();
    $pro = $gateways['woo-mercado-pago-basic'] ?? null;
    $report['official_pro_registered'] = $pro instanceof \MercadoPago\Woocommerce\Gateways\BasicGateway;
    $report['plugin_sales_mode'] = $mp->storeConfig->isProductionMode();
    $report['native_pro_credentials_present'] = trim($mp->sellerConfig->getCredentialsAccessTokenProd()) !== ''
        && trim($mp->sellerConfig->getCredentialsPublicKeyProd()) !== '';
    $report['pro_enabled'] = $pro && $pro->enabled === 'yes';
    $report['pro_available'] = $pro && $pro->is_available();

    $site = wp_parse_url(home_url('/'));
    $host = strtolower((string) ($site['host'] ?? ''));
    $report['public_https_origin'] = ($site['scheme'] ?? '') === 'https'
        && $host !== ''
        && !in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
        && !filter_var($host, FILTER_VALIDATE_IP)
        && !str_ends_with($host, '.localhost')
        && !str_ends_with($host, '.local')
        && !str_ends_with($host, '.invalid');

    if ($mode === 'verify-linked-test-seller' && $report['native_pro_credentials_present']) {
        $report['identity_lookup_attempted'] = true;
        // Same supported reader used by H2. The official plugin may use its cache.
        // Do not print IDs, email, application data, tokens, response bodies or errors.
        $identity = $mp->sellerConfig->getSellerInfo($mp->sellerConfig->getCredentialsAccessTokenProd());
        $data = is_array($identity['data'] ?? null) ? $identity['data'] : [];
        $report['identity_lookup_ok'] = ($identity['status'] ?? 0) === 200;
        $report['linked_account_is_test_user'] = $report['identity_lookup_ok']
            && in_array('test_user', (array) ($data['tags'] ?? []), true);
        $report['linked_account_is_argentina'] = $report['identity_lookup_ok']
            && ($data['site_id'] ?? '') === 'MLA';
        // 8.9.6's current onboarding does not populate the legacy collector
        // option. Authenticate /users/me and compare the explicitly expected
        // seller instead of making that unused metadata a false blocker.
        $collector = trim($mp->sellerConfig->getCollectorId());
        $report['legacy_collector_present'] = $collector !== '';
        $report['linked_account_matches_plugin_collector'] = $collector === '' ? null
            : ($report['identity_lookup_ok'] && (string) ($data['id'] ?? '') === $collector);
        $report['expected_seller_matches'] = $expectedSeller !== '' && $report['identity_lookup_ok']
            && strcasecmp(trim((string) ($data['nickname'] ?? '')), $expectedSeller) === 0;
        unset($identity, $data);
    }

    $report['safe_to_start_test_checkout'] = $report['official_pro_registered']
        && $report['pro_enabled']
        && $report['pro_available']
        && $report['plugin_sales_mode']
        && $report['native_pro_credentials_present']
        && $report['public_https_origin']
        && $report['identity_lookup_ok']
        && $report['linked_account_is_test_user']
        && $report['linked_account_is_argentina']
        && $report['expected_seller_matches']
        && $report['linked_account_matches_plugin_collector'] !== false;
} catch (Throwable $error) {
    $report['preflight_failed'] = true;
    // Exception messages and traces may include credentials. Never emit them.
}

WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if ($mode === 'verify-linked-test-seller' && !$report['safe_to_start_test_checkout']) {
    WP_CLI::error('Test checkout remains blocked: seller identity, mode, or HTTPS preflight failed.');
}
