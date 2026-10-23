<?php
/** Commerce presentation. Product values and operations belong to WooCommerce. */
if (!defined('ABSPATH')) { exit; }

/** Options represented by a visible, purchasable, in-stock variation. */
function commerce_base_available_attribute_options($product, $attribute_name) {
    if (!$product->is_type('variable')) { return []; }
    $attribute = $product->get_attributes()[$attribute_name] ?? null;
    if (!$attribute || !$attribute->get_variation()) { return []; }
    $slugs = [];
    foreach ($product->get_available_variations('objects') as $variation) {
        if (!$variation->is_purchasable() || !$variation->is_in_stock()) { continue; }
        $slug = $variation->get_variation_attributes()['attribute_' . sanitize_title($attribute_name)] ?? '';
        // An empty variation attribute means any of the parent's real options.
        foreach ($slug !== '' ? [$slug] : ($product->get_variation_attributes()[$attribute_name] ?? []) as $available) { $slugs[$available] = true; }
    }
    $options = [];
    foreach (array_keys($slugs) as $slug) {
        $term = $attribute->is_taxonomy() ? get_term_by('slug', $slug, $attribute_name) : false;
        if ($term && !is_wp_error($term)) { $options[$slug] = $term->name; }
        elseif (!$attribute->is_taxonomy()) { $options[$slug] = $slug; }
    }
    return $options;
}

/** Optional semantic color preview; other variation attributes need no color. */
function commerce_base_available_colors($product) {
    return commerce_base_available_attribute_options($product, 'pa_color');
}

function commerce_base_card_colors($product) {
    $colors = commerce_base_available_colors($product);
    if (count($colors) < 2) {
        foreach ($product->get_attributes() as $attribute) {
            if (!$attribute->get_variation()) { continue; }
            $options = commerce_base_available_attribute_options($product, $attribute->get_name());
            if (count($options) < 2) { continue; }
            return '<p class="cb-card-options">' . esc_html(wc_attribute_label($attribute->get_name(), $product) . ': ' . implode(' · ', array_values($options))) . '</p>';
        }
        return '';
    }
    $palette = apply_filters('commerce_base_color_swatches', (array) get_option('commerce_base_color_swatches', ['natural' => '#ded5c2', 'crudo' => '#f2ead8', 'negro' => '#242625', 'gris' => '#8a8c88', 'verde' => '#626d4b', 'bordo' => '#713e48']));
    $html = '<ul class="cb-card-colors"><li class="screen-reader-text">' . esc_html(sprintf(__('Disponible en %s', 'commerce-base'), implode(', ', array_values($colors)))) . '</li>';
    foreach ($colors as $slug => $name) {
        $html .= '<li class="cb-color-swatch" title="' . esc_attr($name) . '" aria-hidden="true" style="--cb-swatch:' . esc_attr(sanitize_hex_color($palette[$slug] ?? '') ?: 'var(--cb-text-muted)') . '"></li>';
    }
    return $html . '</ul>';
}

/** Render the official block with its real product context, also on Home. */
function commerce_base_card_button($product) {
    if (!$product->is_in_stock() || !$product->is_purchasable()) {
        return '<span class="cb-card-unavailable">' . esc_html__('No disponible', 'commerce-base') . '</span>';
    }
    $html = (new WP_Block(['blockName' => 'woocommerce/product-button', 'attrs' => ['className' => 'cb-card-purchase'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], ['postId' => $product->get_id()]))->render();
    if ($product->is_type('variable')) {
        $tag = new WP_HTML_Tag_Processor($html);
        if ($tag->next_tag('a')) { $tag->set_attribute('aria-label', wp_strip_all_tags($product->add_to_cart_text()) . ': ' . $product->get_name()); }
        $html = $tag->get_updated_html();
    }
    return $html;
}

function commerce_base_product_card($product, $level = 2) {
    if (!$product) { return ''; }
    $level = in_array($level, [2, 3], true) ? $level : 2;
    // The first catalog image is above the fold and was the measured LCP.
    // Home has its own hero; related cards stay lazy below product content.
    static $eager_card_used = false;
    $eager = !$eager_card_used && (is_shop() || is_product_taxonomy() || is_search());
    if ($eager) { $eager_card_used = true; }
    $image_args = ['loading' => $eager ? 'eager' : 'lazy', 'sizes' => '(max-width: 359px) calc(100vw - 32px), (max-width: 782px) calc((100vw - 48px) / 2), (min-width: 1800px) 360px, (min-width: 1100px) 24vw, 32vw'];
    if ($eager) { $image_args['fetchpriority'] = 'high'; }
    ob_start(); ?>
    <article class="cb-product-card">
        <figure class="cb-product-image"><a href="<?php echo esc_url($product->get_permalink()); ?>" aria-label="<?php echo esc_attr($product->get_name()); ?>"><?php echo $product->get_image('commerce-card', $image_args); ?></a>
            <?php if (!$product->is_in_stock()) : ?><span class="cb-badge is-sold-out"><?php esc_html_e('Agotado', 'commerce-base'); ?></span>
            <?php elseif ($product->is_on_sale()) : ?><span class="cb-badge"><?php esc_html_e('Oferta', 'commerce-base'); ?></span><?php endif; ?>
        </figure>
        <div class="cb-card-caption"><h<?php echo $level; ?> class="cb-product-title"><a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a></h<?php echo $level; ?>><span class="cb-card-arrow" aria-hidden="true">↗</span></div>
        <div class="cb-product-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
        <?php echo commerce_base_card_colors($product); ?>
        <?php echo commerce_base_card_button($product); ?>
    </article>
    <?php return ob_get_clean();
}

function commerce_base_catalog_filters() {
    $markup = '<!-- wp:woocommerce/product-filters {"overlayMode":"mobile","overlayPosition":"left","className":"cb-filters"} --><div class="wp-block-woocommerce-product-filters cb-filters">';
    $markup .= '<!-- wp:heading {"level":2,"className":"cb-filter-heading"} --><h2 class="wp-block-heading cb-filter-heading">Filtrar</h2><!-- /wp:heading -->';
    $markup .= '<!-- wp:woocommerce/product-filter-active --><div class="wp-block-woocommerce-product-filter-active"><!-- wp:woocommerce/product-filter-removable-chips --><div class="wc-block-product-filter-removable-chips"></div><!-- /wp:woocommerce/product-filter-removable-chips --><!-- wp:woocommerce/product-filter-clear-button --><div class="wp-block-woocommerce-product-filter-clear-button"><button type="button" class="wp-block-button__link">Limpiar filtros</button></div><!-- /wp:woocommerce/product-filter-clear-button --></div><!-- /wp:woocommerce/product-filter-active -->';
    $filters = [['taxonomy', ['taxonomy' => 'product_cat', 'showCounts' => true], 'Categoría']];
    // WooCommerce owns the merchant's attribute labels and sorting. Empty
    // taxonomies are omitted, rather than assuming an apparel catalog.
    foreach (wc_get_attribute_taxonomies() as $attribute) {
        $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 1, 'fields' => 'ids']);
        if (is_wp_error($terms) || !$terms) { continue; }
        $order = in_array($attribute->attribute_orderby, ['menu_order', 'name', 'name_num', 'id'], true) ? $attribute->attribute_orderby : 'name';
        $filters[] = ['attribute', ['attributeId' => (int) $attribute->attribute_id, 'showCounts' => true, 'sortOrder' => $order . '-asc'], $attribute->attribute_label];
    }
    $filters[] = ['price', [], 'Precio'];
    $filters[] = ['status', ['showCounts' => true], 'Disponibilidad'];
    foreach ($filters as [$type, $attrs, $label]) {
        $markup .= '<!-- wp:woocommerce/product-filter-' . $type . ' ' . wp_json_encode((object) $attrs) . ' --><div class="wp-block-woocommerce-product-filter-' . $type . '">';
        $markup .= '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html($label) . '</h3><!-- /wp:heading -->';
        $markup .= $type === 'price' ? '<!-- wp:woocommerce/product-filter-price-slider /-->' : '<!-- wp:woocommerce/product-filter-checkbox-list /-->';
        $markup .= '</div><!-- /wp:woocommerce/product-filter-' . $type . ' -->';
    }
    return $markup . '</div><!-- /wp:woocommerce/product-filters -->';
}

function commerce_base_catalog_heading() {
    $title = is_search() ? sprintf(__('Resultados para “%s”', 'commerce-base'), get_search_query()) : (is_product_taxonomy() ? single_term_title('', false) : __('Tienda', 'commerce-base'));
    $description = is_product_taxonomy() ? term_description() : '';
    return '<header class="cb-catalog-heading"><p class="cb-eyebrow">' . esc_html__('El catálogo', 'commerce-base') . '</p><h1>' . esc_html($title) . '</h1>' . ($description ? '<div class="cb-catalog-intro">' . wp_kses_post($description) . '</div>' : '') . '</header>';
}

function commerce_base_product_information($product, $only_guide = false) {
    if (!$product) { return ''; }
    $guide_id = $product->get_meta('_cb_size_guide');
    $config = get_option('commerce_base_size_guides', []);
    $config = apply_filters('commerce_base_size_guides', $config, $product);
    $guide = $config['guides'][$guide_id] ?? null;
    $care = $product->get_meta('_cb_care');
    ob_start(); ?>
    <div class="cb-product-information">
    <?php if ($guide && $only_guide) : ?>
        <details class="cb-size-guide" id="cb-size-guide"><summary><?php echo esc_html($guide['summary'] ?? __('Guía de talles', 'commerce-base')); ?></summary><div class="cb-size-guide-content"><h2><?php echo esc_html($guide['title']); ?></h2><p><?php echo esc_html($config['notice'] ?? ''); ?></p><div class="cb-table-scroll" role="region" aria-label="<?php echo esc_attr($guide['table_label'] ?? __('Medidas por talle', 'commerce-base')); ?>" tabindex="0"><table><caption class="screen-reader-text"><?php echo esc_html($guide['title']); ?></caption><thead><tr><?php foreach ($guide['columns'] as $column) { echo '<th scope="col">' . esc_html($column) . '</th>'; } ?></tr></thead><tbody><?php foreach ($guide['rows'] as $row) { echo '<tr>'; foreach ($row as $i => $value) { echo $i === 0 ? '<th scope="row">' . esc_html($value) . '</th>' : '<td>' . esc_html($value) . '</td>'; } echo '</tr>'; } ?></tbody></table></div></div></details>
    <?php endif; ?>
    <?php if (!$only_guide) : ?>
    <p class="cb-product-reference"><?php esc_html_e('Referencia', 'commerce-base'); ?> <span><?php echo esc_html($product->get_sku()); ?></span></p>
    <div class="cb-product-disclosures">
        <details><summary><?php esc_html_e('Detalles del producto', 'commerce-base'); ?></summary><div><?php echo wp_kses_post(wpautop($product->get_description())); ?><?php wc_display_product_attributes($product); ?></div></details>
        <?php if ($care) : ?><details><summary><?php esc_html_e('Composición y cuidados', 'commerce-base'); ?></summary><div><p><?php echo esc_html($care); ?></p></div></details><?php endif; ?>
        <details><summary><?php esc_html_e('Envíos y cambios', 'commerce-base'); ?></summary><div><p><?php esc_html_e('Los costos y opciones disponibles se muestran al ingresar tu dirección en el checkout.', 'commerce-base'); ?></p><p><?php esc_html_e('Consultá las condiciones de envío y cambios de la tienda antes de confirmar.', 'commerce-base'); ?></p><?php
            if (class_exists('CommerceCore\\Legal')) {
                foreach (['shipping' => __('Envíos', 'commerce-base'), 'returns' => __('Cambios y devoluciones', 'commerce-base')] as $key => $label) {
                    if ($url = \CommerceCore\Legal::url($key)) { echo '<a class="cb-link" href="' . esc_url($url) . '">' . esc_html($label) . '</a> '; }
                }
            }
            $faq = get_page_by_path('preguntas-frecuentes'); if ($faq && $faq->post_status === 'publish') { echo '<a class="cb-link" href="' . esc_url(get_permalink($faq)) . '">' . esc_html__('Preguntas frecuentes', 'commerce-base') . '</a>'; }
        ?></div></details>
    </div>
    <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

add_action('init', static function () {
    foreach (['catalog-heading' => 'commerce_base_catalog_heading'] as $name => $callback) {
        register_block_type('commerce-base/' . $name, ['api_version' => 3, 'render_callback' => $callback, 'supports' => ['html' => false], 'editor_script' => 'commerce-base-editor']);
    }
    foreach (['product-card', 'product-information'] as $name) {
        register_block_type('commerce-base/' . $name, ['api_version' => 3, 'uses_context' => ['postId'], 'supports' => ['html' => false, 'interactivity' => ['clientNavigation' => true]], 'editor_script' => 'commerce-base-editor', 'render_callback' => static function ($attrs, $content, $block) use ($name) {
            $product = wc_get_product($block->context['postId'] ?? get_the_ID());
            return $name === 'product-card' ? commerce_base_product_card($product, is_front_page() ? 3 : 2) : commerce_base_product_information($product);
        }]);
    }
}, 20);

// A real, objective stock message, without scarcity claims.
add_filter('woocommerce_get_stock_html', static function ($html, $product) {
    if (!is_product()) { return $html; }
    $text = $product->is_in_stock() ? __('En stock', 'commerce-base') : __('Agotado', 'commerce-base');
    return '<p class="stock ' . ($product->is_in_stock() ? 'in-stock' : 'out-of-stock') . '">' . esc_html($text) . '</p>';
}, 10, 2);

add_filter('woocommerce_currency_symbol', static function ($symbol, $currency) { return $currency === 'ARS' ? '$' : $symbol; }, 10, 2);
// The native archive query is canonical with plain permalinks. A shop page_id
// redirects to it and drops filtering arguments in WooCommerce's page redirect.
add_filter('woocommerce_get_shop_page_permalink', static function ($url) { return get_option('permalink_structure') === '' ? get_post_type_archive_link('product') : $url; });
add_filter('woocommerce_get_breadcrumb', static function ($crumbs) { if (is_shop() && $crumbs) { $crumbs[count($crumbs) - 1][0] = __('Tienda', 'commerce-base'); } return $crumbs; });
add_filter('render_block_woocommerce/breadcrumbs', static function ($html) { $tags = new WP_HTML_Tag_Processor($html); if ($tags->next_tag('nav')) { $tags->set_attribute('aria-label', __('Ruta de navegación', 'commerce-base')); } return $tags->get_updated_html(); });
add_filter('woocommerce_get_image_size_thumbnail', static function ($size) { return ['width' => 600, 'height' => 750, 'crop' => 1]; });
add_filter('woocommerce_get_image_size_gallery_thumbnail', static function ($size) { return ['width' => 80, 'height' => 100, 'crop' => 1]; });
add_filter('woocommerce_gallery_image_html_attachment_image_params', static function ($params, $id, $size, $main) {
    $params['sizes'] = '(min-width: 1800px) 950px, (min-width: 783px) 55vw, 100vw';
    $params['loading'] = $main ? 'eager' : 'lazy';
    $params['fetchpriority'] = $main ? 'high' : 'low';
    return $params;
}, 10, 4);
add_action('woocommerce_after_variations_table', static function () { global $product; echo commerce_base_product_information($product, true); });
add_action('woocommerce_before_variations_form', static function () {
    global $product;
    $labels = array_map(static function ($name) use ($product) { return wc_attribute_label($name, $product); }, array_keys($product->get_variation_attributes()));
    $instruction = $labels ? sprintf(__('Elegí las opciones de %s para consultar disponibilidad.', 'commerce-base'), implode(', ', $labels)) : __('Elegí las opciones para consultar disponibilidad.', 'commerce-base');
    echo $product->is_in_stock() ? '<p class="cb-variant-instruction">' . esc_html($instruction) . '</p>' : '<p class="stock out-of-stock">' . esc_html__('Agotado', 'commerce-base') . '</p>';
});
add_filter('woocommerce_catalog_orderby', static function ($options) {
    $labels = ['menu_order' => __('Orden de colección', 'commerce-base'), 'date' => __('Más recientes', 'commerce-base'), 'price' => __('Precio: menor a mayor', 'commerce-base'), 'price-desc' => __('Precio: mayor a menor', 'commerce-base')];
    return array_intersect_key($labels, $options);
});
add_filter('render_block_woocommerce/catalog-sorting', static function ($html) {
    $tags = new WP_HTML_Tag_Processor($html);
    if ($tags->next_tag('select')) { $tags->set_attribute('aria-label', __('Ordenar productos', 'commerce-base')); }
    return $tags->get_updated_html();
});
// WooCommerce 11.2's no-results reset URL omits a nonstandard local port.
// Keep a native archive destination, including the port and taxonomy context.
add_filter('render_block_woocommerce/product-collection-no-results', static function ($html) {
    $reset = is_product_taxonomy() ? get_term_link(get_queried_object()) : wc_get_page_permalink('shop');
    if (is_wp_error($reset)) { $reset = wc_get_page_permalink('shop'); }
    $tags = new WP_HTML_Tag_Processor($html);
    while ($tags->next_tag('a')) { if ($tags->has_class('wc-link-clear-any-filters')) { $tags->set_attribute('href', $reset); } }
    return $tags->get_updated_html();
});
