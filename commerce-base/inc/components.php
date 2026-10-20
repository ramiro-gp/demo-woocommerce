<?php
if (!defined('ABSPATH')) { exit; }

function commerce_base_icon($name) {
    $paths = [
        'menu' => '<path d="M4 8h16M4 16h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'bag' => '<path d="M5 7h14l1 14H4L5 7Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'heart' => '<path d="M20.8 4.6a5.4 5.4 0 0 0-7.6 0L12 5.8l-1.2-1.2a5.4 5.4 0 0 0-7.6 7.6L12 21l8.8-8.8a5.4 5.4 0 0 0 0-7.6Z"/>',
    ];
    return '<svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter">' . ($paths[$name] ?? $paths['arrow']) . '</svg>';
}

function commerce_base_theme_select($id = 'cb-color-mode') {
    return '<label class="screen-reader-text" for="' . esc_attr($id) . '">' . esc_html__('Apariencia', 'commerce-base') . '</label><select class="cb-theme-select" id="' . esc_attr($id) . '" data-cb-theme-select aria-label="' . esc_attr__('Apariencia', 'commerce-base') . '"><option value="system">Sistema</option><option value="light">Claro</option><option value="dark">Oscuro</option></select>';
}

function commerce_base_external_link($url) {
    $target = wp_parse_url($url);
    $site = wp_parse_url(home_url('/'));
    if (!$target || empty($target['host'])) { return false; }
    $scheme = strtolower($target['scheme'] ?? $site['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) { return false; }
    return strtolower($target['host'] ?? '') !== strtolower($site['host'] ?? '') ||
        ($target['port'] ?? ($scheme === 'https' ? 443 : 80)) !== ($site['port'] ?? ($site['scheme'] === 'https' ? 443 : 80));
}

function commerce_base_link_target($url) {
    return commerce_base_external_link($url) ? ' target="_blank" rel="noopener noreferrer"' : '';
}

// Merchant-controlled social navigation only; payment/navigation redirects are untouched.
add_filter('nav_menu_link_attributes', static function ($attributes, $item, $args) {
    if (($args->theme_location ?? '') !== 'footer-social') { return $attributes; }
    if (commerce_base_external_link($attributes['href'] ?? '')) {
        $attributes['target'] = '_blank';
        $attributes['rel'] = trim(($attributes['rel'] ?? '') . ' noopener noreferrer');
    } else { unset($attributes['target']); }
    return $attributes;
}, 10, 3);

function commerce_base_navigation() {
    $locations = get_nav_menu_locations();
    $items = !empty($locations['primary']) ? wp_get_nav_menu_items($locations['primary']) : [];
    $links = [];
    foreach ($items ?: [] as $item) {
        if ((int) $item->menu_item_parent !== 0) { continue; }
        $links[] = ['label' => $item->title, 'url' => $item->url, 'target' => $item->target];
    }
    if (!$links) {
        $links[] = ['label' => __('Catálogo', 'commerce-base'), 'url' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/'), 'target' => ''];
        if (function_exists('wc_get_page_permalink')) {
            $links[] = ['label' => __('Mi cuenta', 'commerce-base'), 'url' => wc_get_page_permalink('myaccount'), 'target' => ''];
            $links[] = ['label' => __('Carrito', 'commerce-base'), 'url' => wc_get_cart_url(), 'target' => ''];
        }
    }
    return apply_filters('commerce_base_navigation_links', $links);
}

function commerce_base_header($attributes = []) {
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    ob_start(); ?>
    <a class="cb-skip-link" href="#main-content" data-cb-anchor><?php esc_html_e('Saltar al contenido', 'commerce-base'); ?></a>
    <header class="cb-header" data-cb-header>
        <div class="cb-header-inner cb-container">
            <a class="cb-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name') . ' — ' . __('Inicio', 'commerce-base')); ?>"><?php
                if (has_custom_logo()) {
                    echo wp_get_attachment_image(get_theme_mod('custom_logo'), 'full', false, ['class' => 'cb-logo', 'alt' => get_bloginfo('name')]);
                } else { echo esc_html(get_bloginfo('name')); }
            ?></a>
            <a class="cb-header-catalog cb-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Explorar catálogo', 'commerce-base'); ?></a>
            <div class="cb-header-actions">
                <?php echo commerce_base_theme_select(); ?>
                <button class="cb-icon-button cb-header-search" type="button" data-cb-menu-open data-cb-menu-search-open aria-controls="cb-menu" aria-expanded="false" aria-label="<?php esc_attr_e('Buscar productos', 'commerce-base'); ?>"><?php echo commerce_base_icon('search'); ?></button>
                <?php if (function_exists('wc_get_page_permalink')) : ?>
                <a class="cb-icon-button cb-header-account" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" aria-label="<?php esc_attr_e('Mi cuenta', 'commerce-base'); ?>"><?php echo commerce_base_icon('user'); ?></a>
                <?php endif; ?>
                <?php if (function_exists('wc_get_cart_url')) : ?>
                <?php if (is_cart() || is_checkout()) : // Mini-Cart deliberately hides itself on these native pages. ?>
                <a class="cb-icon-button cb-header-cart cb-cart-page-link" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo commerce_base_icon('bag'); ?><span class="screen-reader-text"><?php esc_html_e('Carrito', 'commerce-base'); ?></span><span data-cb-cart-count-root data-cb-count-label="<?php esc_attr_e('%d artículos', 'commerce-base'); ?>"><?php $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0; if ($count > 0) { echo '<span class="wc-block-mini-cart__badge">' . esc_html($count) . '</span>'; } ?></span></a>
                <?php else : ?>
                <?php echo do_blocks('<!-- wp:woocommerce/mini-cart {"miniCartIcon":"bag","hasHiddenPrice":true,"productCountVisibility":"greater_than_zero","addToCartBehaviour":"none","className":"cb-header-cart"} /-->'); ?>
                <?php endif; ?>
                <?php endif; ?>
                <button class="cb-icon-button cb-menu-trigger" type="button" data-cb-menu-open aria-controls="cb-menu" aria-expanded="false" aria-label="<?php esc_attr_e('Abrir menú', 'commerce-base'); ?>"><?php echo commerce_base_icon('menu'); ?></button>
            </div>
        </div>
    </header>
    <dialog class="cb-menu" id="cb-menu" data-cb-menu aria-label="<?php esc_attr_e('Navegación principal', 'commerce-base'); ?>">
        <div class="cb-menu-masthead"><div class="cb-menu-top cb-container"><span class="cb-brand"><?php echo esc_html(get_bloginfo('name')); ?></span><span class="cb-eyebrow"><?php esc_html_e('Explorar', 'commerce-base'); ?></span><button class="cb-icon-button" type="button" data-cb-menu-close aria-label="<?php esc_attr_e('Cerrar menú', 'commerce-base'); ?>"><?php echo commerce_base_icon('close'); ?></button></div></div>
        <div class="cb-menu-panel" data-cb-menu-panel><div class="cb-menu-inner cb-container">
            <nav class="cb-menu-nav" aria-label="<?php esc_attr_e('Principal', 'commerce-base'); ?>">
                <?php foreach (commerce_base_navigation() as $index => $link) : ?>
                <a class="cb-menu-link" href="<?php echo esc_url($link['url']); ?>"<?php echo commerce_base_link_target($link['url']); ?>><span class="cb-index"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></span><span><?php echo esc_html($link['label']); ?></span><?php echo commerce_base_icon('arrow'); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="cb-menu-bottom"><p><?php echo esc_html(get_bloginfo('description')); ?></p><form role="search" class="cb-menu-search" action="<?php echo esc_url(home_url('/')); ?>" method="get"><label class="screen-reader-text" for="cb-menu-search"><?php esc_html_e('Buscar productos', 'commerce-base'); ?></label><input class="cb-input" id="cb-menu-search" type="search" name="s" placeholder="<?php esc_attr_e('¿Qué estás buscando?', 'commerce-base'); ?>"><input type="hidden" name="post_type" value="product"><button class="cb-icon-button" aria-label="<?php esc_attr_e('Buscar', 'commerce-base'); ?>"><?php echo commerce_base_icon('search'); ?></button></form></div>
        </div></div>
    </dialog>
    <noscript><nav class="cb-container cb-no-js-nav" aria-label="<?php esc_attr_e('Navegación sin JavaScript', 'commerce-base'); ?>"><?php foreach (commerce_base_navigation() as $link) { echo '<a href="' . esc_url($link['url']) . '"' . commerce_base_link_target($link['url']) . '>' . esc_html($link['label']) . '</a> '; } ?></nav></noscript>
    <?php return ob_get_clean();
}

function commerce_base_footer($attributes = []) {
    $shop_links = [];
    $help_links = [];
    if (function_exists('wc_get_page_permalink')) {
        foreach (['shop' => __('Tienda', 'commerce-base'), 'cart' => __('Carrito', 'commerce-base')] as $page => $label) {
            if ('publish' === get_post_status(wc_get_page_id($page))) { $shop_links[] = ['url' => wc_get_page_permalink($page), 'label' => $label]; }
        }
        if ('publish' === get_post_status(wc_get_page_id('myaccount'))) { $help_links[] = ['url' => wc_get_page_permalink('myaccount'), 'label' => __('Mi cuenta y pedidos', 'commerce-base')]; }
    }
    $about = get_page_by_path('conocenos');
    if ($about && 'publish' === $about->post_status) { $shop_links[] = ['url' => get_permalink($about), 'label' => __('Conocenos', 'commerce-base')]; }
    // Publish links only when the merchant has created and published the pages.
    foreach (['contacto' => 'Contacto', 'preguntas-frecuentes' => 'Preguntas frecuentes', 'envios' => 'Envíos', 'cambios-y-devoluciones' => 'Cambios y devoluciones', 'terminos' => 'Términos', 'boton-de-arrepentimiento' => 'Botón de Arrepentimiento'] as $slug => $label) {
        $page = get_page_by_path($slug);
        if ($page && 'publish' === $page->post_status) { $help_links[] = ['url' => get_permalink($page), 'label' => $label]; }
    }
    if (get_privacy_policy_url()) { $help_links[] = ['url' => get_privacy_policy_url(), 'label' => __('Privacidad', 'commerce-base')]; }
    ob_start(); ?>
    <footer class="cb-footer"><div class="cb-container">
        <div class="cb-footer-inner">
            <div class="cb-footer-brand"><a class="cb-brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a><p><?php echo esc_html(get_bloginfo('description')); ?></p></div>
            <nav class="cb-footer-column" aria-label="<?php esc_attr_e('Tienda', 'commerce-base'); ?>"><h2><?php esc_html_e('Tienda', 'commerce-base'); ?></h2><?php foreach ($shop_links as $link) : ?><a class="cb-link" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a><?php endforeach; ?></nav>
            <nav class="cb-footer-column" aria-label="<?php esc_attr_e('Ayuda e información', 'commerce-base'); ?>"><h2><?php esc_html_e('Ayuda e información', 'commerce-base'); ?></h2><?php foreach ($help_links as $link) : ?><a class="cb-link" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a><?php endforeach; ?></nav>
            <?php if (has_nav_menu('footer-social')) : ?><nav class="cb-footer-column" aria-label="<?php esc_attr_e('Redes sociales', 'commerce-base'); ?>"><h2><?php esc_html_e('Encontranos', 'commerce-base'); ?></h2><?php wp_nav_menu(['theme_location' => 'footer-social', 'container' => false, 'depth' => 1, 'fallback_cb' => false]); ?></nav><?php endif; ?>
        </div>
        <div class="cb-footer-bottom">
            <?php if (function_exists('get_woocommerce_currency') && 'ARS' === get_woocommerce_currency()) : ?><p><?php esc_html_e('Precios expresados en pesos argentinos (ARS).', 'commerce-base'); ?></p><?php endif; ?>
            <p class="cb-footer-credit">Hecho con <span class="cb-credit-heart" role="img" aria-label="amor"><?php echo commerce_base_icon('heart'); ?></span> por <a class="cb-link" href="https://ramita.dev" target="_blank" rel="noopener noreferrer">Ramiro Garcia<span class="screen-reader-text"> (abre en una nueva pestaña)</span></a></p>
            <a class="cb-link cb-back-top" href="#cb-page-top" data-cb-anchor><?php esc_html_e('Volver arriba', 'commerce-base'); ?> <span aria-hidden="true">↑</span></a>
        </div>
    </div></footer>
    <?php return ob_get_clean();
}

function commerce_base_product_grid($attributes = []) {
    if (!function_exists('wc_get_products')) { return '<p>' . esc_html__('El catálogo estará disponible al activar WooCommerce.', 'commerce-base') . '</p>'; }
    // Keep the existing editable Home block/layout. Native Product Collection
    // supplies product context, cart feedback and its accessible notices region.
    $settings = ['queryId' => 91, 'query' => ['woocommerceAttributes' => [], 'woocommerceStockStatus' => ['instock', 'outofstock', 'onbackorder'], 'taxQuery' => (object) [], 'isProductCollectionBlock' => true, 'perPage' => min(24, max(1, absint($attributes['limit'] ?? 4))), 'pages' => 1, 'offset' => 0, 'postType' => 'product', 'order' => 'desc', 'orderBy' => 'date', 'author' => '', 'search' => '', 'exclude' => [], 'sticky' => '', 'inherit' => false], 'displayLayout' => ['type' => 'flex', 'columns' => 4, 'shrinkColumns' => true], 'className' => 'cb-home-collection'];
    return do_blocks('<!-- wp:woocommerce/product-collection ' . wp_json_encode($settings) . ' --><div class="wp-block-woocommerce-product-collection cb-home-collection"><!-- wp:woocommerce/product-template {"className":"cb-product-grid"} --><!-- wp:commerce-base/product-card /--><!-- /wp:woocommerce/product-template --><!-- wp:woocommerce/product-collection-no-results --><!-- wp:paragraph --><p>Todavía no hay productos publicados.</p><!-- /wp:paragraph --><!-- /wp:woocommerce/product-collection-no-results --></div><!-- /wp:woocommerce/product-collection -->');
}
