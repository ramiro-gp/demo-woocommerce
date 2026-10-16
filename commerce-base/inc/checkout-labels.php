<?php
if (!defined('ABSPATH')) { exit; }

// Public country locale data is serialized by CartCheckoutUtils for Blocks.
// Preserve requirements, validation, ordering and all other country metadata.
function commerce_base_address_labels($fields) {
    if (!str_starts_with(determine_locale(), 'es')) { return $fields; }
    foreach (['last_name' => 'Apellido', 'country' => 'País', 'state' => 'Provincia'] as $key => $label) {
        $fields[$key]['label'] = $label;
        $fields[$key]['optionalLabel'] = $label . ' (opcional)';
    }
    return $fields;
}
add_filter('woocommerce_get_country_locale_default', 'commerce_base_address_labels', 100);
add_filter('woocommerce_get_country_locale_base', 'commerce_base_address_labels', 100);
add_filter('woocommerce_get_country_locale', static function ($locales) {
    foreach ($locales as $country => $fields) {
        // Province is the Argentine name; keep other countries' own terminology.
        if ('AR' === $country) { $locales[$country] = commerce_base_address_labels($fields); }
        elseif (str_starts_with(determine_locale(), 'es')) {
            $locales[$country]['last_name']['label'] = 'Apellido';
            $locales[$country]['country']['label'] = 'País';
        }
    }
    return $locales;
}, 100);

// WooCommerce 11.2.1's es_AR Checkout Blocks catalog lacks this notice plural.
// Extend the loaded Jed catalog through WordPress i18n, preserving all official
// entries. A future language pack translation takes precedence automatically.
add_filter('load_script_translations', static function ($translations, $file, $handle, $domain) {
    if ($handle !== 'wc-cart-checkout-base' || $domain !== 'woocommerce' || determine_locale() !== 'es_AR') { return $translations; }
    $catalog = json_decode($translations, true);
    if (!isset($catalog['locale_data']['messages'])) { return $translations; }
    $supplement = json_decode(file_get_contents(get_theme_file_path('languages/woocommerce-checkout-es_AR.json')), true);
    foreach ($supplement as $message => $translated) {
        foreach ($translated as $plural => $text) {
            if (empty($catalog['locale_data']['messages'][$message][$plural])) {
                $catalog['locale_data']['messages'][$message][$plural] = $text;
            }
        }
    }
    return wp_json_encode($catalog);
}, 10, 4);

// The native Mini-Cart empty pattern uses this PHP msgid, missing from the
// installed es_AR pack. A future official translation takes precedence.
add_filter('gettext_woocommerce', static function ($translation, $message) {
    if ($message === 'Your shopping cart is empty' && $translation === $message && determine_locale() === 'es_AR') {
        return __('Tu carrito está vacío', 'commerce-base');
    }
    return $translation;
}, 10, 2);
