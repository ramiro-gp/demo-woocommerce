<?php
/** Real Woo CRUD mutations on one existing simple product; restored in finally. Local only. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080/' !== home_url('/')) { exit(1); }
if (!function_exists('commerce_lookbook_render')) { WP_CLI::error('Commerce Lookbook required.'); }
$state = get_option('commerce_lookbook_h5_1');
$attributes = parse_blocks($state['new_block'])[0]['attrs'];
$checks = [];
$assert = static function ($ok, $name) use (&$checks) { if (!$ok) { throw new RuntimeException($name); } $checks[] = $name; };
$render = static function ($spots) use ($attributes) {
    return render_block(['blockName' => 'commerce-lookbook/shoppable-lookbook', 'attrs' => array_merge($attributes, ['hotspots' => $spots]), 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
};
$spot = static function ($id, $x = 50, $y = 50) { return ['productId' => $id, 'x' => $x, 'y' => $y]; };
$simple = wc_get_product(wc_get_product_id_by_sku('TRM-ACC-001'));
$sale = wc_get_product(wc_get_product_id_by_sku('TRM-ACC-005'));
$sold = wc_get_product(wc_get_product_id_by_sku('TRM-PAN-002'));
$variable = wc_get_product(wc_get_product_id_by_sku('TRM-REM-001'));
$original = ['regular_price' => $simple->get_regular_price(), 'sale_price' => $simple->get_sale_price(),
    'price' => $simple->get_price(), 'slug' => $simple->get_slug(), 'status' => $simple->get_status(),
    'catalog_visibility' => $simple->get_catalog_visibility()];
try {
    $assert(str_contains($render([$spot($simple->get_id())]), wp_kses_post($simple->get_price_html())), 'simple_native_price');
    $sale_html = $render([$spot($sale->get_id())]);
    $assert(str_contains($sale_html, '<del ') && str_contains($sale_html, '<ins '), 'native_sale_markup');
    $sold_html = $render([$spot($sold->get_id())]);
    $assert(str_contains($sold_html, 'Agotado') && str_contains($sold_html, esc_url($sold->get_permalink())), 'exhausted_navigable_despite_catalog_setting');
    $assert(str_contains($render([$spot($variable->get_children()[0])]), esc_url($variable->get_permalink())), 'variation_reference_normalized_to_parent');
    $assert(!str_contains($render([$spot(2147483647)]), 'class="slb-hotspot"'), 'deleted_reference_hidden');
    $simple->set_regular_price('19555'); $simple->set_price('19555'); $simple->save();
    $changed = wc_get_product($simple->get_id());
    $assert(str_contains($render([$spot($simple->get_id())]), wp_kses_post($changed->get_price_html())) && $changed->get_price() === '19555', 'real_price_edit_reflected_without_block_edit');
    $simple->set_slug('h5-1-temporary-permalink'); $simple->save();
    $assert(str_contains($render([$spot($simple->get_id())]), esc_url(wc_get_product($simple->get_id())->get_permalink())), 'real_permalink_edit_reflected');
    $simple->set_status('draft'); $simple->save();
    $assert(!str_contains($render([$spot($simple->get_id())]), 'class="slb-hotspot"'), 'unpublished_reference_hidden');
    $simple->set_status('trash'); $simple->save();
    $assert(!str_contains($render([$spot($simple->get_id())]), 'class="slb-hotspot"'), 'trashed_reference_hidden');
    $simple->set_status('publish'); $simple->set_catalog_visibility('hidden'); $simple->save();
    $assert(!str_contains($render([$spot($simple->get_id())]), 'class="slb-hotspot"'), 'hidden_catalog_reference_hidden');
    $sanitized = commerce_lookbook_hotspots(['hotspots' => [$spot($variable->get_id(), -50, 500), ['productId' => 1, 'x' => 'bad', 'y' => 1]]]);
    $assert(count($sanitized) === 1 && $sanitized[0]['x'] === 0.0 && $sanitized[0]['y'] === 100.0, 'coordinates_validated_and_clamped');
    $assert(count(commerce_lookbook_hotspots(['hotspots' => array_fill(0, 100, $spot($variable->get_id()))])) === 12, 'bounded_hotspot_count');
} finally {
    $simple->set_props($original); $simple->save();
}
$restored = wc_get_product($simple->get_id());
foreach ($original as $key => $value) { $assert($restored->{'get_' . $key}() === $value, 'restored_' . $key); }
$assert(get_post_field('post_content', $state['home_id']) && hash('sha256', get_post_field('post_content', $state['home_id'])) === $state['after_hash'], 'home_never_mutated_by_qa');
$fixture = $render([$spot($variable->get_id(), 1, 1), $spot($sale->get_id(), 99, 1), $spot($sold->get_id(), 99, 99), $spot($simple->get_id(), 1, 99)]);
echo wp_json_encode(['result' => 'passed', 'checks' => $checks, 'count' => count($checks), 'edge_fixture_html' => $fixture], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
