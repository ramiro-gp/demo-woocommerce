<?php
/** Explicit local TRAMA presentation settings; never runs on plugin activation. */
require_once __DIR__ . '/h7-1-functions.php';
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'configure', 'verify'], true)) { WP_CLI::error('Use inspect|configure|verify.'); }
$english = 'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [privacy_policy].';
$spanish = 'Usamos tus datos personales para gestionar el acceso a tu cuenta y acompañar tu experiencia en esta tienda, según lo explicado en nuestra [privacy_policy].';
$account_id = wc_get_page_id('myaccount');
if ('configure' === $mode) {
    h7_1_validate_backup($args[1] ?? '');
    if ('es_AR' !== get_locale() || 'TRAMA' !== get_bloginfo('name') || !is_plugin_active('commerce-account-navigation/commerce-account-navigation.php')) { WP_CLI::error('Configure only the existing local TRAMA store with the navigation extension active.'); }
    $current = get_option('woocommerce_registration_privacy_policy_text', null);
    if (null !== $current && !in_array($current, [$english, $spanish], true)) { WP_CLI::error('Preserve merchant privacy copy; configure it through WooCommerce.'); }
    // Only the known persisted English default is migrated. Native placeholder
    // handles the policy link when the merchant publishes its policy in H8.
    if ($current !== $spanish) { update_option('woocommerce_registration_privacy_policy_text', $spanish, false); }
    if ('My account' === get_post_field('post_title', $account_id)) { wp_update_post(['ID' => $account_id, 'post_title' => 'Mi cuenta']); }
    // Initial physical-store decision only. Subsequent merchant edits survive.
    add_option('commerce_account_show_downloads', 'no', '', false);
}
$checks = [
    'navigation_plugin_active' => is_plugin_active('commerce-account-navigation/commerce-account-navigation.php'),
    'physical_demo_hides_downloads' => 'no' === get_option('commerce_account_show_downloads'),
    'registration_copy_spanish' => $spanish === get_option('woocommerce_registration_privacy_policy_text'),
    'native_policy_placeholder' => str_contains(wc_get_privacy_policy_text('registration'), '[privacy_policy]'),
    'account_title_spanish' => 'Mi cuenta' === get_post_field('post_title', $account_id),
    'downloads_endpoint_preserved' => '' !== get_option('woocommerce_myaccount_downloads_endpoint', 'downloads'),
    'checkout_pro_disabled' => 'yes' !== (get_option('woocommerce_woo-mercado-pago-basic_settings', [])['enabled'] ?? 'no'),
];
if ('verify' === $mode) {
    $checks['physical_menu_hidden'] = !isset(wc_get_account_menu_items()['downloads']);
    $digital_store = static function () { return 'yes'; };
    add_filter('pre_option_commerce_account_show_downloads', $digital_store);
    $checks['digital_menu_available'] = isset(wc_get_account_menu_items()['downloads']);
    remove_filter('pre_option_commerce_account_show_downloads', $digital_store);
    $checks['native_download_handler'] = false !== has_action('woocommerce_account_downloads_endpoint', 'woocommerce_account_downloads');
    $settings = apply_filters('woocommerce_account_settings', []);
    $field = array_values(array_filter($settings, static fn($s) => 'commerce_account_show_downloads' === ($s['id'] ?? '')));
    $checks['native_settings_field_default_visible'] = 1 === count($field) && 'yes' === $field[0]['default'] && 'checkbox' === $field[0]['type'];
}
echo wp_json_encode(['result' => 'inspect' === $mode ? 'ready' : (in_array(false, $checks, true) ? 'failed' : 'passed'), 'checks' => $checks, 'policy_published' => (bool) get_privacy_policy_url(), 'policy_publication' => 'Deferred to H8; no legal content or page status changed.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
