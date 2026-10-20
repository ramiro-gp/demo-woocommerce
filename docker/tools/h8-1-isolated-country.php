<?php
/** Render a real native form with per-process countries; no settings/customer writes. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080' !== untrailingslashit(home_url()) || 'twentytwentyfive' !== get_stylesheet()) { exit(1); }
$fixture = json_decode(file_get_contents('/backups/h8-1-qa-fixtures.json'), true);
wp_set_current_user($fixture['customers']['complete']['id']);
$customer = new WC_Customer(get_current_user_id());
$before = hash('sha256', wp_json_encode($customer->get_data()));
add_filter('pre_option_woocommerce_allowed_countries', static fn() => 'all');
add_filter('pre_option_woocommerce_ship_to_countries', static fn() => 'all');
add_filter('pre_option_woocommerce_checkout_company_field', static fn() => 'optional');
// Getter filters affect this process only; native user data stays unchanged.
foreach (['country' => 'CL', 'state' => 'CL-RM', 'city' => 'Santiago', 'postcode' => '8320000', 'address_1' => 'Destino aislado 123', 'company' => 'Empresa de prueba'] as $key => $value) {
    add_filter('woocommerce_customer_get_billing_' . $key, static fn() => $value);
}
// Phone is optional in native shipping forms. Simulate an extension exposing
// the compatible native property; never map an email or arbitrary metadata.
add_filter('woocommerce_shipping_fields', static function ($fields) {
    $fields['shipping_phone'] = ['type' => 'tel', 'label' => 'Teléfono', 'required' => false, 'validate' => ['phone']];
    return $fields;
});
do_action('wp_enqueue_scripts');
ob_start();
echo '<!doctype html><html lang="es-AR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="Content-Security-Policy" content="form-action \'none\'"><title>Formulario aislado de QA</title></head><body>';
WC_Shortcode_My_Account::edit_address('shipping');
wp_print_scripts(['jquery', 'wc-country-select', 'wc-address-i18n', 'commerce-core-address-copy']);
echo '</body></html>';
$html = ob_get_clean();
file_put_contents('/backups/h8-1-isolated-country.html', $html);
// edit context bypasses the getter filters used to render the fake source.
$after = hash('sha256', wp_json_encode((new WC_Customer(get_current_user_id()))->get_data()));
$passed = $before === $after && str_contains($html, 'commerce-core-copy-source') && str_contains($html, 'wc-country-select');
echo wp_json_encode(['result' => $passed ? 'passed' : 'failed', 'customer_unchanged' => $before === $after, 'theme' => get_stylesheet(), 'persistent_settings_changed' => false]) . "\n";
if (!$passed) { exit(1); }
