<?php
/** Explicit local demo migration. Never loaded by storefront or plugin activation. */
require_once __DIR__ . '/h7-1-functions.php';

$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'configure', 'verify'], true)) { WP_CLI::error('Use inspect|configure|verify.'); }
if ('11.2.1' !== WC_VERSION) { WP_CLI::error('Review email APIs before changing WooCommerce version.'); }
$fixture = json_decode(file_get_contents('/opt/demo-communications/emails.json'), true, 512, JSON_THROW_ON_ERROR);
$names = array_keys($fixture['options']);
foreach (array_keys($fixture['emails']) as $id) { $names[] = 'woocommerce_' . $id . '_settings'; }
$snapshot = static function () use ($names) {
    $values = [];
    foreach ($names as $name) { $values[$name] = get_option($name, null); }
    return $values;
};
$state = (array) get_option('commerce_h7_2_communications', []);
$before_payments = h7_1_sensitive_snapshot();

if ('configure' === $mode) {
    h7_1_validate_backup($args[1] ?? '');
    if (!is_plugin_active('commerce-communications/commerce-communications.php') || !is_plugin_active('commerce-order-integrity/commerce-order-integrity.php')) { WP_CLI::error('Activate both versioned Commerce plugins first.'); }
    if ('yes' === (get_option('woocommerce_woo-mercado-pago-basic_settings', [])['enabled'] ?? 'no')) { WP_CLI::error('Disable Checkout Pro before local communications QA.'); }
    if ($state && ($state['applied_hash'] ?? '') !== h7_1_hash($snapshot())) { WP_CLI::error('Email settings changed after H7.2; preserve merchant edits. Configure through WooCommerce, do not overwrite.'); }
    if (!$state) {
        // First application is limited to this existing fictitious local store.
        $addresses = [get_option('woocommerce_email_from_address', get_option('admin_email')), get_option('woocommerce_email_reply_to_address', '')];
        foreach ($fixture['emails'] as $id => $settings) {
            $existing = (array) get_option('woocommerce_' . $id . '_settings', []);
            foreach (['recipient', 'cc', 'bcc'] as $field) {
                if (!empty($existing[$field])) { $addresses = array_merge($addresses, explode(',', $existing[$field])); }
            }
        }
        foreach ($addresses as $address) {
            if (trim((string) $address) && !preg_match('/^[^@\s]+@example\.test$/iD', trim($address))) { WP_CLI::error('Non-demo email configuration found; preserve it.'); }
        }
        $state = ['backup_id' => $args[1], 'configured_at_utc' => gmdate('c')];
    }
    // An explicit source-fixture update may migrate this owned demo only while
    // its last applied configuration is intact. Never reset manual edits.
    if (($state['fixture_hash'] ?? '') !== h7_1_hash($fixture)) {
        if (isset($state['applied_hash'])) { $state['previous_applied_hash'] = $state['applied_hash']; }
        foreach ($fixture['options'] as $name => $value) { update_option($name, $value, false); }
        // Leave From-name unset: WC's default follows the WordPress site title.
        foreach ($fixture['emails'] as $id => $values) {
            $name = 'woocommerce_' . $id . '_settings';
            update_option($name, array_replace((array) get_option($name, []), $values, ['email_type' => 'multipart']), false);
        }
        $state['applied_hash'] = h7_1_hash($snapshot());
        $state['fixture_hash'] = h7_1_hash($fixture);
        update_option('commerce_h7_2_communications', $state, false);
    }
}

$features = 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';
$inventory = [];
foreach (WC()->mailer()->get_emails() as $class => $email) {
    $inventory[] = ['id' => $email->id, 'class' => $class, 'enabled' => $email->is_enabled(), 'manual' => $email->is_manual(), 'customer' => $email->is_customer_email(), 'type' => $email->get_email_type()];
}
$checks = [
    'wc_11_2_1' => '11.2.1' === WC_VERSION,
    'hpos' => Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
    'communications_active' => is_plugin_active('commerce-communications/commerce-communications.php'),
    'integrity_active' => is_plugin_active('commerce-order-integrity/commerce-order-integrity.php'),
    'native_stable_email_improvements' => $features::feature_is_enabled('email_improvements'),
    'alpha_editor_disabled' => !$features::feature_is_enabled('block_email_editor'),
    'checkout_pro_disabled' => 'yes' !== (get_option('woocommerce_woo-mercado-pago-basic_settings', [])['enabled'] ?? 'no'),
    'bacs_enabled' => 'yes' === (get_option('woocommerce_bacs_settings', [])['enabled'] ?? 'no'),
    'payments_preserved' => $before_payments === h7_1_sensitive_snapshot(),
    'migration_recorded' => !empty($state),
    'email_configuration_matches_demo' => ($state['applied_hash'] ?? '') === h7_1_hash($snapshot()),
    'source_fixture_current' => ($state['fixture_hash'] ?? '') === h7_1_hash($fixture),
    'native_cart_block' => has_block('woocommerce/cart', get_post_field('post_content', wc_get_page_id('cart'))),
    'native_checkout_block' => has_block('woocommerce/checkout', get_post_field('post_content', wc_get_page_id('checkout'))),
];
// Inspection before configuration may legitimately show unconfigured checks.
$result = 'inspect' === $mode ? 'ready' : (in_array(false, $checks, true) ? 'failed' : 'passed');
h7_1_emit(['result' => $result, 'checks' => $checks, 'emails' => $inventory, 'from_name_source' => 'Native WooCommerce option; default WordPress site title', 'transport' => 'Existing local-only Mailpit MU plugin; no real SMTP', 'secrets_exposed' => false]);
