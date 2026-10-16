<?php
if (!defined('ABSPATH')) { exit; }

/** Explicit editorial links retain exhausted products even when the catalog hides them. */
function commerce_lookbook_product($id) {
    if (!function_exists('wc_get_product')) { return false; }
    $product = wc_get_product(absint($id));
    if ($product && $product->is_type('variation')) { $product = wc_get_product($product->get_parent_id()); }
    if (!$product || 'publish' !== $product->get_status() || post_password_required($product->get_id()) ||
        '' !== (string) get_post_field('post_password', $product->get_id()) || 'hidden' === $product->get_catalog_visibility()) { return false; }
    return $product;
}

function commerce_lookbook_hotspots($attributes) {
    $hotspots = [];
    foreach (array_slice((array) ($attributes['hotspots'] ?? []), 0, 12) as $spot) {
        if (!is_array($spot) || empty($spot['productId']) || !is_numeric($spot['x'] ?? null) || !is_numeric($spot['y'] ?? null)) { continue; }
        $hotspots[] = ['productId' => absint($spot['productId']),
            'x' => round(max(0, min(100, (float) $spot['x'])), 2),
            'y' => round(max(0, min(100, (float) $spot['y'])), 2)];
    }
    return $hotspots;
}

function commerce_lookbook_price_text($html) {
    // The link label announces the current price once; Woo's visual sale markup
    // also contains a deleted amount and duplicate screen-reader sentences.
    $html = preg_replace('/<del\b[^>]*>.*?<\/del>/is', '', $html);
    $html = preg_replace('/<span\b[^>]*class=["\'][^"\']*screen-reader-text[^"\']*["\'][^>]*>.*?<\/span>/is', '', $html);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
}
