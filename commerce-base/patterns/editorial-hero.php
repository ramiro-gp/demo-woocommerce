<?php
/**
 * Title: Hero · composición editorial
 * Slug: commerce-base/editorial-hero
 * Categories: commerce-base
 * Description: Patrón de composición para editar con contenido de cada marca. No es una home.
 */
?>
<!-- wp:group {"tagName":"section","className":"cb-hero","layout":{"type":"default"}} -->
<section class="wp-block-group cb-hero"><!-- wp:group {"className":"cb-container cb-hero-grid","layout":{"type":"default"}} -->
<div class="wp-block-group cb-container cb-hero-grid"><!-- wp:group {"className":"cb-hero-copy","layout":{"type":"default"}} -->
<div class="wp-block-group cb-hero-copy"><!-- wp:paragraph {"className":"cb-eyebrow"} --><p class="cb-eyebrow">Una nueva perspectiva</p><!-- /wp:paragraph --><!-- wp:heading {"level":1,"className":"cb-hero-title"} --><h1 class="wp-block-heading cb-hero-title">Lo esencial,<br>con otra mirada.</h1><!-- /wp:heading --><!-- wp:paragraph {"className":"cb-hero-description"} --><p class="cb-hero-description">Objetos cotidianos. Materiales que se sienten. Una forma propia de habitar la ciudad.</p><!-- /wp:paragraph --><!-- wp:buttons {"className":"cb-hero-actions"} --><div class="wp-block-buttons cb-hero-actions"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>">Ver catálogo <span aria-hidden="true">↗</span></a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->
<!-- wp:group {"className":"cb-hero-media","layout":{"type":"default"}} --><div class="wp-block-group cb-hero-media"><!-- wp:image {"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="<?php echo esc_url(get_theme_file_uri('assets/images/editorial-specimen.webp')); ?>" alt="Detalle de una sobrecamisa verde oliva sobre algodón crudo, frente a una pared cálida" width="960" height="1200"/></figure><!-- /wp:image --></div><!-- /wp:group -->
</div><!-- /wp:group --></section><!-- /wp:group -->
