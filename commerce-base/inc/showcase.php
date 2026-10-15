<?php
/** Development specimen. No database page, commercial content or checkout mutations. */
if (!defined('ABSPATH')) { exit; }
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$specimen_uri = get_theme_file_uri('assets/images/editorial-specimen.webp');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('cb-showcase'); ?>><?php wp_body_open(); ?>
<?php echo commerce_base_header(); ?>
<main id="main-content">
    <section class="cb-hero" aria-labelledby="cb-hero-title">
        <div class="cb-container cb-hero-grid">
            <div class="cb-hero-copy">
                <p class="cb-eyebrow"><span class="cb-index">03 /</span> Commerce Base · Sistema visual</p>
                <h1 class="cb-hero-title" id="cb-hero-title">Lo esencial,<br>con <em>otra</em><br>mirada.</h1>
                <p class="cb-hero-description">Texturas honestas. Ritmo de ciudad.<br>Una forma propia de vestir lo cotidiano.</p>
                <div class="cb-hero-actions"><a class="cb-button" href="#productos" data-cb-anchor>Explorar el sistema <?php echo commerce_base_icon('arrow'); ?></a><a class="cb-link" href="<?php echo esc_url($shop_url); ?>">Ver catálogo <span aria-hidden="true">↗</span></a></div>
            </div>
            <figure class="cb-hero-media"><img src="<?php echo esc_url($specimen_uri); ?>" alt="Sobrecamisa verde oliva y camiseta de algodón crudo bajo luz natural" width="960" height="1200" fetchpriority="high"><figcaption><span>Estudio de materia / 01</span><span>Imagen de muestra</span></figcaption></figure>
        </div>
        <div class="cb-container cb-hero-foot"><span class="cb-eyebrow">Diseñado para lo cotidiano</span><a class="cb-link" href="#fundamentos" data-cb-anchor>Un sistema, muchas identidades <span aria-hidden="true">↓</span></a></div>
    </section>

    <div class="cb-container">
        <div class="cb-showcase-intro"><p class="cb-eyebrow">H3 / Vista de desarrollo</p><p class="cb-reading">Una base visual para recorrer, elegir y comprar con calma. Esta vista reúne patrones y estados; la home y el contenido de marca se construirán en H4.</p></div>
        <nav class="cb-showcase-nav" aria-label="Secciones del sistema"><a class="cb-link" href="#fundamentos" data-cb-anchor>01 Fundamentos</a><a class="cb-link" href="#controles" data-cb-anchor>02 Interacción</a><a class="cb-link" href="#formularios" data-cb-anchor>03 Formularios</a><a class="cb-link" href="#productos" data-cb-anchor>04 Producto</a></nav>

        <section class="cb-section" id="fundamentos" aria-labelledby="cb-foundations-title">
            <div class="cb-section-heading"><div><p class="cb-eyebrow">01 / Fundamentos</p><h2 id="cb-foundations-title">Materia, tinta <em>y aire.</em></h2></div><p class="cb-note">Una paleta que cambia de luz.<br>Una identidad que conserva el carácter.</p></div>
            <div class="cb-color-grid">
                <?php foreach (['background' => 'Fondo', 'surface' => 'Superficie', 'surface-elevated' => 'Elevación', 'text' => 'Tinta', 'text-muted' => 'Tinta secundaria', 'border' => 'Línea', 'accent' => 'Acento'] as $token => $label) : ?>
                <div class="cb-swatch"><span style="background:var(--cb-<?php echo esc_attr($token); ?>)" aria-hidden="true"></span><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($token); ?></small></div>
                <?php endforeach; ?>
            </div>
            <div class="cb-state-row"><span class="cb-badge is-success">✓ Confirmación</span><span class="cb-badge is-warning">! Atención</span><span class="cb-badge is-danger">× Error</span><span class="cb-badge">Información</span></div>
            <div class="cb-specimen-grid cb-type-specimens">
                <div><p class="cb-eyebrow">Newsreader / Editorial · 400</p><p class="cb-type-display">Menos ruido.<br><em>Más intención.</em></p></div>
                <div><p class="cb-eyebrow">Manrope / Interfaz · 400–700</p><h3>La claridad también es diseño.</h3><p class="cb-reading">La información importante se encuentra sin esfuerzo. El texto respira, el precio se reconoce y cada control tiene un lugar. El detalle acompaña a la función.</p><p class="cb-note">Aa Bb Cc Ññ · 0123456789 · $ 12.500</p><a class="cb-link" href="#formularios" data-cb-anchor>Un enlace editorial <?php echo commerce_base_icon('arrow'); ?></a></div>
            </div>
            <div class="cb-spacing-specimen"><p class="cb-eyebrow">Espacio / Una escala compartida</p><div class="cb-spacing-demo"><?php foreach ([1 => 4, 2 => 8, 3 => 12, 4 => 16, 5 => 24, 6 => 32, 7 => 48, 8 => 64] as $step => $pixels) : ?><div><span style="inline-size:var(--cb-space-<?php echo (int) $step; ?>)" aria-hidden="true"></span><small><?php echo (int) $pixels; ?></small></div><?php endforeach; ?></div><p class="cb-note">Del detalle al grupo. Del grupo a la sección. Márgenes fluidos, lectura contenida y composición amplia.</p></div>
        </section>

        <section class="cb-section" id="controles" aria-labelledby="cb-controls-title">
            <div class="cb-section-heading"><div><p class="cb-eyebrow">02 / Interacción</p><h2 id="cb-controls-title">Una respuesta <em>sutil.</em></h2></div><p class="cb-note">Probá hover, foco y teclado.<br>El movimiento acompaña la acción.</p></div>
            <div class="cb-button-row"><a class="cb-button" href="<?php echo esc_url($shop_url); ?>">Ver catálogo <?php echo commerce_base_icon('arrow'); ?></a><a class="cb-button is-secondary" href="#productos" data-cb-anchor>Explorar productos</a><a class="cb-button is-ghost" href="#formularios" data-cb-anchor>Ver detalles <span aria-hidden="true">↗</span></a><button type="button" class="cb-icon-button" aria-label="Abrir menú de muestra" data-cb-menu-open aria-controls="cb-menu" aria-expanded="false"><?php echo commerce_base_icon('menu'); ?></button></div>
            <div class="cb-button-row"><button type="button" class="cb-button" disabled>No disponible</button><button type="button" class="cb-button is-secondary" data-cb-loading-demo><span data-cb-loading-label>Probar estado de carga</span></button><button type="button" class="cb-button is-ghost" data-cb-feedback-trigger aria-controls="cb-feedback" aria-expanded="false">Probar confirmación <?php echo commerce_base_icon('check'); ?></button></div>
            <div class="cb-feedback is-success" id="cb-feedback" data-cb-demo-feedback role="status" hidden>✓ Esta es una confirmación de muestra. No se agregó ningún producto al carrito.</div>
            <div class="cb-specimen-grid cb-motion-specimens"><div><p class="cb-eyebrow">Movimiento / 140 · 240 · 360 ms</p><h3>Entra. Responde. Vuelve.</h3><p class="cb-reading">Controles rápidos, imágenes que se acercan apenas y un menú que vuelve por donde entró. Sin alterar la rueda, el teclado ni el gesto de scroll.</p></div><div><p class="cb-eyebrow">Apariencia / Tu preferencia</p><label for="cb-showcase-color-mode">Elegí cómo recorrer la tienda</label><div class="cb-theme-specimen"><?php echo commerce_base_theme_select('cb-showcase-color-mode'); ?></div><p class="cb-note">Sistema sigue la apariencia del dispositivo. Claro y Oscuro conservan tu selección.</p><a class="cb-link" href="#fundamentos" data-cb-anchor>Volver a la paleta <span aria-hidden="true">↑</span></a></div></div>
        </section>

        <section class="cb-section" id="formularios" aria-labelledby="cb-forms-title">
            <div class="cb-section-heading"><div><p class="cb-eyebrow">03 / Formularios</p><h2 id="cb-forms-title">Todo, en <em>su lugar.</em></h2></div><p class="cb-note">Etiquetas visibles. Foco claro.<br>Estados que también se leen.</p></div>
            <div class="cb-form-grid">
                <div class="cb-field"><label for="cb-name">Nombre</label><input class="cb-input" id="cb-name" autocomplete="given-name" placeholder="Tu nombre"><p class="cb-note">Un campo simple, con espacio para escribir.</p></div>
                <div class="cb-field"><label for="cb-email">Email · ejemplo con error</label><input class="cb-input" id="cb-email" type="email" value="correo incompleto" aria-invalid="true" aria-describedby="cb-email-error"><p class="cb-field-error" id="cb-email-error">× Ingresá un email completo, por ejemplo nombre@dominio.com.</p></div>
                <div class="cb-field"><label for="cb-search">Buscar</label><div class="cb-search-field"><input class="cb-input" id="cb-search" type="search" placeholder="Buscar en el sistema"><span aria-hidden="true"><?php echo commerce_base_icon('search'); ?></span></div></div>
                <div class="cb-field"><label for="cb-size">Seleccionar · muestra visual</label><select class="cb-select" id="cb-size"><option value="">Elegí una opción</option><option>Primera opción</option><option>Segunda opción</option></select></div>
                <div class="cb-field"><label for="cb-confirmed">Campo confirmado</label><input class="cb-input is-success" id="cb-confirmed" value="Información verificada" aria-describedby="cb-success-help"><p class="cb-field-success" id="cb-success-help">✓ Dato válido. Estado de muestra.</p></div>
                <div class="cb-field"><label for="cb-disabled">Campo deshabilitado</label><input class="cb-input" id="cb-disabled" value="No disponible por el momento" disabled></div>
                <div class="cb-field"><label for="cb-message">Mensaje</label><textarea class="cb-input" id="cb-message" rows="3" placeholder="Espacio para un texto más largo"></textarea></div>
                <div class="cb-field"><label for="cb-quantity">Cantidad · muestra</label><div class="cb-quantity" data-cb-quantity><button type="button" class="cb-icon-button" data-cb-quantity-minus aria-label="Disminuir cantidad"><?php echo commerce_base_icon('minus'); ?></button><input id="cb-quantity" type="number" value="1" min="1" max="5" inputmode="numeric"><button type="button" class="cb-icon-button" data-cb-quantity-plus aria-label="Aumentar cantidad"><?php echo commerce_base_icon('plus'); ?></button></div><p class="cb-note">Límite de muestra: 1–5. No modifica el carrito.</p></div>
                <fieldset class="cb-field"><legend>Selección múltiple</legend><label class="cb-check"><input type="checkbox" checked> Opción seleccionada</label><label class="cb-check"><input type="checkbox"> Opción disponible</label><label class="cb-check"><input type="checkbox" disabled> Opción deshabilitada</label></fieldset>
                <fieldset class="cb-field"><legend>Una sola opción</legend><label class="cb-check"><input type="radio" name="cb-choice" value="1" checked> Primera opción</label><label class="cb-check"><input type="radio" name="cb-choice" value="2"> Segunda opción</label></fieldset>
            </div>
            <div class="cb-state-row"><p class="cb-feedback is-warning">! Atención: este es un estado de muestra.</p><p class="cb-feedback is-danger" role="note">× No se pudo completar la acción de muestra.</p></div>
        </section>

        <section class="cb-section" id="productos" aria-labelledby="cb-products-title">
            <div class="cb-section-heading"><div><p class="cb-eyebrow">04 / Producto</p><h2 id="cb-products-title">El objeto, <em>primero.</em></h2></div><a class="cb-link" href="<?php echo esc_url($shop_url); ?>">Ir a WooCommerce <span aria-hidden="true">↗</span></a></div>
            <p class="cb-reading cb-note">Nombres, precios, categorías y disponibilidad reales de los productos técnicos de H2. La fotografía es una muestra visual y no se asigna al catálogo. Las ofertas se muestran cuando el producto tiene un precio promocional nativo.</p>
            <div class="cb-catalog-layout">
                <aside class="cb-filter-panel" aria-label="Integración visual de filtros"><p class="cb-eyebrow">Refinar la selección</p><h3>Espacio para elegir.</h3><p class="cb-note">Este lateral admite los filtros nativos de WooCommerce. Su configuración corresponde a H5.</p><button type="button" class="cb-button is-secondary" data-cb-filter-open aria-controls="cb-filter-dialog" aria-expanded="false">Ver panel mobile <?php echo commerce_base_icon('plus'); ?></button><p class="cb-note">Muestra de apertura y cierre. Sin filtros ficticios.</p></aside>
                <div><?php
                    $placeholder = static function () use ($specimen_uri) { return $specimen_uri; };
                    add_filter('woocommerce_placeholder_img_src', $placeholder);
                    echo commerce_base_product_grid(['limit' => 4]);
                    remove_filter('woocommerce_placeholder_img_src', $placeholder);
                ?></div>
            </div>
            <dialog id="cb-filter-dialog" class="cb-filter-dialog" data-cb-filter-dialog aria-labelledby="cb-filter-dialog-title"><div class="cb-filter-dialog-inner"><div class="cb-section-heading"><h3 id="cb-filter-dialog-title">Refinar la selección</h3><button type="button" class="cb-icon-button" data-cb-filter-close aria-label="Cerrar filtros"><?php echo commerce_base_icon('close'); ?></button></div><p>Los controles nativos de WooCommerce ocuparán este panel en H5.</p><p class="cb-note">Este es el patrón visual mobile: superficie completa, cierre accesible y scroll independiente.</p></div></dialog>
        </section>

        <section class="cb-section cb-endnote"><p class="cb-eyebrow">Commerce Base / H3</p><h2>La base está.<br><em>La historia sigue después.</em></h2><p class="cb-reading">Un theme independiente de la marca. Un sistema listo para revisar antes de construir las páginas de H4.</p><a class="cb-link" href="#main-content" data-cb-anchor>Volver al inicio <span aria-hidden="true">↑</span></a></section>
    </div>
</main>
<?php echo commerce_base_footer(); wp_footer(); ?>
</body></html>
