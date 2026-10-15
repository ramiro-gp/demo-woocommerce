<?php
/**
 * Title: Inicio · selección de productos
 * Slug: commerce-base/home-selection
 * Categories: commerce-base
 * Description: Los cuatro productos publicados más recientes de WooCommerce, con acceso al catálogo.
 */
?>
<!-- wp:group {"tagName":"section","className":"cb-home-products cb-section","layout":{"type":"default"}} -->
<section class="wp-block-group cb-home-products cb-section">
    <!-- wp:group {"className":"cb-container","layout":{"type":"default"}} -->
    <div class="wp-block-group cb-container">
        <!-- wp:group {"className":"cb-section-heading","layout":{"type":"default"}} -->
        <div class="wp-block-group cb-section-heading">
            <!-- wp:group {"layout":{"type":"default"}} -->
            <div class="wp-block-group">
                <!-- wp:paragraph {"className":"cb-eyebrow"} -->
                <p class="cb-eyebrow">En el catálogo</p>
                <!-- /wp:paragraph -->
                <!-- wp:heading -->
                <h2 class="wp-block-heading">Lo último en llegar.</h2>
                <!-- /wp:heading -->
            </div>
            <!-- /wp:group -->
            <!-- wp:paragraph -->
            <p><a class="cb-link" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? (wc_get_page_permalink('shop') ?: home_url('/')) : home_url('/')); ?>">Ver todo el catálogo <span aria-hidden="true">↗</span></a></p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
        <!-- wp:commerce-base/product-grid {"limit":4} /-->
    </div>
    <!-- /wp:group -->
</section>
<!-- /wp:group -->
