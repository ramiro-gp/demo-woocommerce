<?php
/**
 * Title: Editorial · imagen y relato
 * Slug: commerce-base/editorial-story
 * Categories: commerce-base
 * Description: Imagen amplia, texto corto y acceso al catálogo; todo el contenido se puede editar.
 */
?>
<!-- wp:group {"tagName":"section","className":"cb-story cb-section","layout":{"type":"default"}} -->
<section class="wp-block-group cb-story cb-section">
    <!-- wp:group {"className":"cb-container cb-story-grid","layout":{"type":"default"}} -->
    <div class="wp-block-group cb-container cb-story-grid">
        <!-- wp:image {"sizeSlug":"full","linkDestination":"none","aspectRatio":"3/2","scale":"cover","className":"cb-story-media"} -->
        <figure class="wp-block-image size-full cb-story-media"><img src="<?php echo esc_url(get_theme_file_uri('assets/images/editorial-detail.webp')); ?>" alt="Remera cruda y pantalón oscuro junto a una sobrecamisa oliva, sobre un banco de piedra al sol" style="aspect-ratio:3/2;object-fit:cover"/></figure>
        <!-- /wp:image -->
        <!-- wp:group {"className":"cb-story-copy","layout":{"type":"default"}} -->
        <div class="wp-block-group cb-story-copy">
            <!-- wp:paragraph {"className":"cb-eyebrow"} -->
            <p class="cb-eyebrow">Una mirada de cerca</p>
            <!-- /wp:paragraph -->
            <!-- wp:heading -->
            <h2 class="wp-block-heading">Textura, forma<br>y movimiento.</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph -->
            <p>Una paleta corta. Materiales que se sienten. Detalles que acompañan el uso de todos los días.</p>
            <!-- /wp:paragraph -->
            <!-- wp:buttons -->
            <div class="wp-block-buttons">
                <!-- wp:button -->
                <div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? (wc_get_page_permalink('shop') ?: home_url('/')) : home_url('/')); ?>">Ver catálogo <span aria-hidden="true">↗</span></a></div>
                <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
</section>
<!-- /wp:group -->
