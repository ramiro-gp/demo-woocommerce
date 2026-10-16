<?php
/** Read-only checks of the approved post-H5 policy and real variation data. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8080') { exit(1); }
$assert = static function ($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
$base = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001'));
$single = wc_get_product(wc_get_product_id_by_sku('TRM-BUZ-003'));
$sold = wc_get_product(wc_get_product_id_by_sku('TRM-PAN-002'));
$assert(get_option('woocommerce_hide_out_of_stock_items') === 'yes', 'Native inventory setting is required for the approved catalog default.');
$assert(count(commerce_base_available_colors($base)) === 2, 'Base must have two available real colors.');
$assert(commerce_base_card_colors($single) === '', 'A single color must not render swatches.');
$assert(commerce_base_available_colors($sold) === [], 'Exhausted colors must be excluded.');
// Exercise Woo's purchasability and stock filters without editing any fixture.
$unavailable = static function ($available, $variation) use ($base) {
    return $variation->get_parent_id() === $base->get_id() && ($variation->get_variation_attributes()['attribute_pa_color'] ?? '') === 'negro' ? false : $available;
};
add_filter('woocommerce_variation_is_purchasable', $unavailable, 10, 2);
try { $assert(array_keys(commerce_base_available_colors($base)) === ['natural'], 'Unpurchasable colors must be excluded.'); }
finally { remove_filter('woocommerce_variation_is_purchasable', $unavailable, 10); }
$stock = static function ($in_stock, $variation) use ($base) {
    return $variation->get_parent_id() === $base->get_id() && ($variation->get_variation_attributes()['attribute_pa_color'] ?? '') === 'negro' ? false : $in_stock;
};
add_filter('woocommerce_product_is_in_stock', $stock, 10, 2);
try { $assert(array_keys(commerce_base_available_colors($base)) === ['natural'], 'Colors with no in-stock variation must be excluded.'); }
finally { remove_filter('woocommerce_product_is_in_stock', $stock, 10); }
$assert(count(commerce_base_available_colors($base)) === 2, 'Temporary QA filters must be removed.');
$assert(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'HPOS must remain enabled.');
echo wp_json_encode(['result' => 'passed', 'checks' => 8, 'native_hide_out_of_stock' => true, 'swatches_real_variations' => true, 'single_color_hidden' => true, 'exhausted_and_unpurchasable_excluded' => true, 'fixtures_written' => false, 'hpos' => true], JSON_PRETTY_PRINT) . "\n";
