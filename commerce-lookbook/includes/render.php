<?php
if (!defined('ABSPATH')) { exit; }

function commerce_lookbook_render($attributes, $content = '') {
    $image_id = absint($attributes['imageId'] ?? 0);
    $metadata = $image_id ? wp_get_attachment_metadata($image_id) : [];
    $width = absint($metadata['width'] ?? $attributes['imageWidth'] ?? 0);
    $height = absint($metadata['height'] ?? $attributes['imageHeight'] ?? 0);
    $ratio = $width && $height ? 'aspect-ratio:' . $width . '/' . $height . ';' : '';
    $image = $image_id ? wp_get_attachment_image($image_id, 'large', false, [
        'class' => 'slb-image', 'alt' => $attributes['imageAlt'] ?? '', 'loading' => 'lazy',
        'sizes' => '(min-width: 832px) 61vw, 100vw', 'style' => $ratio,
    ]) : '';
    // Saved image is a portable fallback if its attachment is missing or the plugin is deactivated.
    if (!$image && !empty($attributes['imageUrl'])) {
        $image = '<img class="slb-image" src="' . esc_url($attributes['imageUrl']) . '" alt="' . esc_attr($attributes['imageAlt'] ?? '') . '" loading="lazy" width="' . $width . '" height="' . $height . '" style="' . esc_attr($ratio) . '" />';
    }
    if (!$image) { return $content; }
    $spots = commerce_lookbook_hotspots($attributes);
    $ids = array_values(array_unique(array_column($spots, 'productId')));
    if ($ids && function_exists('wc_get_product')) {
        get_posts(['post_type' => ['product', 'product_variation'], 'post_status' => 'publish',
            'post__in' => $ids, 'posts_per_page' => count($ids), 'suppress_filters' => false]);
    }
    $instance = wp_unique_id('slb-');
    $links = ''; $fallback = '';
    foreach ($spots as $i => $spot) {
        $product = commerce_lookbook_product($spot['productId']);
        if (!$product) { continue; }
        $name = $product->get_name();
        $price = $product->get_price_html(); // Native currency, taxes, sale markup and variable ranges.
        $stock = $product->is_in_stock() ? '' : __('Agotado', 'commerce-lookbook');
        $label = sprintf(__('Ver %s', 'commerce-lookbook'), $name);
        if ($price) { $label .= ', ' . commerce_lookbook_price_text($price); }
        if ($stock) { $label .= ', ' . $stock; }
        $href = esc_url($product->get_permalink());
        $pill_id = $instance . '-product-' . $i;
        $links .= '<a class="slb-hotspot" tabindex="0" href="' . $href . '" data-side="' . ($spot['x'] >= 50 ? 'left' : 'right') . '" data-vertical="' . ($spot['y'] >= 50 ? 'above' : 'below') . '" style="--slb-x:' . $spot['x'] . '%;--slb-y:' . $spot['y'] . '%" aria-label="' . esc_attr($label) . '" aria-controls="' . esc_attr($pill_id) . '">';
        $links .= '<span class="slb-dot" aria-hidden="true"></span><span class="slb-pill" id="' . esc_attr($pill_id) . '"><span class="slb-product-name">' . esc_html($name) . '</span>';
        if ($price) { $links .= '<span class="slb-price">' . wp_kses_post($price) . '</span>'; }
        if ($stock) { $links .= '<span class="slb-stock">' . esc_html($stock) . '</span>'; }
        $links .= '<span class="slb-arrow" aria-hidden="true">↗</span></span></a>';
        $fallback .= '<li><a href="' . $href . '">' . esc_html($name) . '</a> · ' . wp_kses_post($price) . ($stock ? ' · ' . esc_html($stock) : '') . '</li>';
    }
    $wrapper = get_block_wrapper_attributes(['class' => 'slb-lookbook']);
    return '<div ' . $wrapper . '><div class="slb-stage">' . $image . $links . '</div>' .
        ($links ? '<p class="slb-hint">' . esc_html__('Explorá los puntos de la imagen para ver los productos.', 'commerce-lookbook') . '</p><noscript><ul class="slb-fallback">' . $fallback . '</ul></noscript>' : '') . '</div>';
}
