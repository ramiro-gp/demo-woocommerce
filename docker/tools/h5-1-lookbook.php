<?php
/** Narrow, guarded local migration: replace only H4's Lookbook image. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type() || 'http://localhost:8080/' !== home_url('/')) { exit(1); }
if (!function_exists('commerce_lookbook_render')) { WP_CLI::error('Synchronize and activate Commerce Lookbook first.'); }
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['seed', 'inspect', 'verify'], true)) { WP_CLI::error('Use seed|inspect|verify.'); }
$home_id = (int) get_option('page_on_front');
$home = get_post_field('post_content', $home_id);
$state = get_option('commerce_lookbook_h5_1');
$products = [];
foreach (['TRM-REM-001', 'TRM-PAN-003'] as $sku) {
    $product = commerce_lookbook_product(wc_get_product_id_by_sku($sku));
    if (!$product) { WP_CLI::error('Existing approved H5 products required. No new product is created.'); }
    $products[] = $product;
}
if ('seed' === $mode && !$state) {
    $start = strpos($home, 'cb-home-lookbook');
    if (false === $start || !preg_match('~<!-- wp:image\s.*?<!-- /wp:image -->~s', substr($home, $start), $match, PREG_OFFSET_CAPTURE)) { WP_CLI::error('Expected existing H4 lookbook image.'); }
    $old = $match[0][0]; $offset = $start + $match[0][1];
    if (!str_contains($old, 'cb-story-media') || !str_contains($old, 'editorial-detail.webp') || has_block('commerce-lookbook/shoppable-lookbook', $home)) { WP_CLI::error('Home changed; preserve edits and inspect manually.'); }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $source = get_theme_file_path('assets/images/editorial-detail.webp');
    $hash = hash_file('sha256', $source);
    $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_commerce_lookbook_demo_source', 'meta_value' => $hash, 'posts_per_page' => 1]);
    if ($existing) { $image_id = $existing[0]->ID; }
    else {
        $upload = wp_upload_bits('editorial-lookbook.webp', null, file_get_contents($source));
        if ($upload['error']) { WP_CLI::error($upload['error']); }
        $image_id = wp_insert_attachment(['post_title' => 'Editorial · Notas de ciudad 01', 'post_mime_type' => 'image/webp', 'post_status' => 'inherit'], $upload['file'], $home_id, true);
        if (is_wp_error($image_id)) { WP_CLI::error($image_id->get_error_message()); }
        wp_update_attachment_metadata($image_id, wp_generate_attachment_metadata($image_id, $upload['file']));
        update_post_meta($image_id, '_commerce_lookbook_demo_source', $hash);
    }
    $alt = 'Persona con remera cruda y pantalón oscuro sentada sobre piedra, con una sobrecamisa oliva al lado';
    update_post_meta($image_id, '_wp_attachment_image_alt', $alt);
    $metadata = wp_get_attachment_metadata($image_id);
    $attributes = ['imageId' => (int) $image_id, 'imageUrl' => wp_get_attachment_url($image_id), 'imageAlt' => $alt,
        'imageWidth' => (int) $metadata['width'], 'imageHeight' => (int) $metadata['height'],
        'hotspots' => [['productId' => $products[0]->get_id(), 'x' => 62, 'y' => 19], ['productId' => $products[1]->get_id(), 'x' => 64, 'y' => 64]]];
    $fallback = '<div class="wp-block-commerce-lookbook-shoppable-lookbook"><img src="' . esc_url($attributes['imageUrl']) . '" alt="' . esc_attr($alt) . '" width="' . $attributes['imageWidth'] . '" height="' . $attributes['imageHeight'] . '"/></div>';
    $new = '<!-- wp:commerce-lookbook/shoppable-lookbook ' . wp_json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' -->' . $fallback . '<!-- /wp:commerce-lookbook/shoppable-lookbook -->';
    $updated = substr_replace($home, $new, $offset, strlen($old));
    $result = wp_update_post(['ID' => $home_id, 'post_content' => wp_slash($updated)], true);
    if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
    $state = ['home_id' => $home_id, 'image_id' => (int) $image_id, 'old_block' => $old, 'new_block' => $new,
        'before_hash' => hash('sha256', $home), 'after_hash' => hash('sha256', $updated)];
    update_option('commerce_lookbook_h5_1', $state, false);
    $home = $updated;
}
$checks = [];
$check = static function ($ok, $name) use (&$checks) { $checks[$name] = (bool) $ok; };
$check(WP_Block_Type_Registry::get_instance()->is_registered('commerce-lookbook/shoppable-lookbook'), 'native_block_registered');
$check($state && (int) $state['home_id'] === $home_id && has_block('commerce-lookbook/shoppable-lookbook', $home), 'home_uses_block');
if ($state) {
    $check(hash('sha256', str_replace($state['new_block'], $state['old_block'], $home)) === $state['before_hash'], 'only_lookbook_image_changed');
    $check(wp_attachment_is_image($state['image_id']), 'image_in_media_library');
}
$check(str_contains($home, 'cb-home-lookbook') && substr_count($home, '<!-- wp:commerce-lookbook/shoppable-lookbook ') === 1, 'one_lookbook_section');
$check(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'hpos_preserved');
$rendered = do_blocks($home);
$check(substr_count($rendered, 'class="slb-hotspot"') === 2, 'two_real_product_links');
foreach ($products as $product) {
    $check(str_contains($rendered, esc_url($product->get_permalink())) && str_contains($rendered, esc_html($product->get_name())) && str_contains($rendered, wp_kses_post($product->get_price_html())), 'live_' . $product->get_sku());
}
echo wp_json_encode(['result' => !in_array(false, $checks, true) ? 'passed' : 'failed', 'checks' => $checks,
    'url' => home_url('/'), 'edit_url' => admin_url('post.php?post=' . $home_id . '&action=edit'),
    'image_id' => $state['image_id'] ?? null,
    'products' => array_map(static function ($p) { return ['name' => $p->get_name(), 'type' => $p->get_type(), 'url' => $p->get_permalink()]; }, $products)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
