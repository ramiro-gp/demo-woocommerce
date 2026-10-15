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
