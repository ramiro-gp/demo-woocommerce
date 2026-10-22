<?php
/** Native catalog discovery. No parallel product data or search engine. */
if (!defined('ABSPATH')) { exit; }

function commerce_base_category_links($attributes = []) {
    if (!taxonomy_exists('product_cat')) { return ''; }
    $limit = min(8, max(1, absint($attributes['limit'] ?? 4)));
    $ids = array_values(array_filter(array_map('absint', (array) ($attributes['termIds'] ?? []))));
    $query = ['taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => $limit, 'orderby' => 'count', 'order' => 'DESC'];
    // Optional selection remains a taxonomy reference, editable in the block.
    if ($ids) { $query['include'] = $ids; $query['orderby'] = 'include'; $query['order'] = 'ASC'; $query['hide_empty'] = false; }
    $terms = get_terms($query);
    if (is_wp_error($terms)) { return ''; }
    ob_start(); ?>
    <nav class="cb-category-links" aria-label="<?php esc_attr_e('Categorías del catálogo', 'commerce-base'); ?>">
        <?php foreach ($terms as $index => $term) :
            $url = get_term_link($term);
            if (is_wp_error($url)) { continue; }
            $image = absint(get_term_meta($term->term_id, 'thumbnail_id', true)); ?>
        <a class="cb-category-link<?php echo $image ? ' has-image' : ''; ?>" href="<?php echo esc_url($url); ?>">
            <span class="cb-index" aria-hidden="true"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></span>
            <?php if ($image) { echo wp_get_attachment_image($image, 'woocommerce_thumbnail', false, ['class' => 'cb-category-image', 'alt' => '', 'loading' => 'lazy']); } ?>
            <span class="cb-category-name"><?php echo esc_html($term->name); ?></span>
            <?php if (!$term->count) : ?><span class="cb-category-note"><?php esc_html_e('Colección en preparación', 'commerce-base'); ?></span><?php endif; ?>
            <span class="cb-category-arrow" aria-hidden="true">↗</span>
        </a>
        <?php endforeach; ?>
        <?php if (!$terms && function_exists('wc_get_page_permalink')) : ?>
        <a class="cb-category-link" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><span class="cb-index" aria-hidden="true">01</span><span class="cb-category-name"><?php esc_html_e('Todo el catálogo', 'commerce-base'); ?></span><span class="cb-category-arrow" aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </nav>
    <?php return ob_get_clean();
}

function commerce_base_search_tools($attributes = []) {
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    $form = function_exists('get_product_search_form') ? get_product_search_form(false) : get_search_form(false);
    ob_start(); ?>
    <div class="cb-search-tools">
        <?php if (empty($attributes['recovery'])) { echo $form; } ?>
        <div class="cb-recovery-links">
            <?php if (!empty($attributes['recovery'])) : ?><a class="cb-button" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Volver al inicio', 'commerce-base'); ?></a><?php endif; ?>
            <a class="<?php echo !empty($attributes['recovery']) ? 'cb-button is-secondary' : 'cb-link'; ?>" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Ver catálogo', 'commerce-base'); ?> <span aria-hidden="true">↗</span></a>
        </div>
        <?php if (!empty($attributes['recovery'])) { echo $form; } ?>
    </div>
    <?php return ob_get_clean();
}

// Add delivery metadata at render time, keeping core/image fully editable.
add_filter('render_block_core/image', static function ($html, $block) {
    $image = new WP_HTML_Tag_Processor($html);
    if (!$image->next_tag('img')) { return $html; }
    $classes = $block['attrs']['className'] ?? '';
    $hero = str_contains($classes, 'cb-home-hero-media');
    // A replacement uploaded through the CMS keeps the same LCP priority.
    if ($hero) {
        $image->set_attribute('loading', 'eager');
        $image->set_attribute('fetchpriority', 'high');
        $image->set_attribute('decoding', 'async');
    }
    $base = trailingslashit(get_theme_file_uri('assets/images'));
    $source = (string) $image->get_attribute('src');
    if (!str_starts_with($source, $base)) { return $image->get_updated_html(); }
    $file = basename(wp_parse_url($source, PHP_URL_PATH));
    $path = get_theme_file_path('assets/images/' . $file);
    if (!is_file($path) || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['webp', 'jpg', 'png'], true)) { return $html; }
    $size = wp_getimagesize($path);
    if ($size) { $image->set_attribute('width', $size[0]); $image->set_attribute('height', $size[1]); }
    if (!$hero && str_contains($classes, 'cb-story-media')) { $image->set_attribute('loading', 'lazy'); }
    $image->set_attribute('decoding', 'async');
    if ($file === 'editorial-detail.webp') {
        $image->set_attribute('srcset', $base . 'editorial-detail-small.webp 768w, ' . $source . ' 1536w');
        $image->set_attribute('sizes', '(min-width: 832px) 65vw, 100vw');
    }
    if ($file === 'editorial-specimen.webp' && is_file(get_theme_file_path('assets/images/editorial-specimen-small.webp'))) {
        $image->set_attribute('srcset', $base . 'editorial-specimen-small.webp 480w, ' . $source . ' 960w');
        $image->set_attribute('sizes', '(min-width: 1024px) 48vw, 100vw');
    }
    return $image->get_updated_html();
}, 10, 2);
