<?php
/** Local H4 content seed. Native editable blocks; preserves products, orders and commercial settings. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type()) { exit(1); }
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'seed', 'verify'], true)) { WP_CLI::error('Use inspect|seed|verify.'); }
if ('http://localhost:8080/' !== home_url('/') || 'http://localhost:8080/' !== site_url('/')) {
    WP_CLI::error('H4 content is limited to the original local demo at http://localhost:8080/.');
}
if ('seed' === $mode && ($args[1] ?? '') !== home_url('/')) { WP_CLI::error('Pass the exact local home URL to seed H4.'); }
if ('commerce-base' !== get_stylesheet() || !function_exists('wc_get_page_permalink')) {
    WP_CLI::error('The existing Commerce Base / WooCommerce demo must already be active.');
}

const H4_DEMO_OPTION = 'commerce_base_h4_demo_content';
const H4_DEMO_MARKER = '_commerce_base_h4_demo_page';

function h4_demo_hash($value) {
    return hash('sha256', wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function h4_demo_page_state($id) {
    $post = get_post($id);
    if (!$post) { return null; }
    return [
        'id' => (int) $post->ID, 'type' => $post->post_type, 'title' => $post->post_title,
        'slug' => $post->post_name, 'status' => $post->post_status, 'content' => $post->post_content,
        'excerpt' => $post->post_excerpt, 'parent' => (int) $post->post_parent,
        'template' => get_page_template_slug($id), 'marker' => get_post_meta($id, H4_DEMO_MARKER, true),
    ];
}
function h4_demo_front_state() {
    return ['show_on_front' => get_option('show_on_front'), 'page_on_front' => (int) get_option('page_on_front')];
}
function h4_demo_identity_state() {
    return ['name' => get_option('blogname'), 'description' => get_option('blogdescription')];
}
function h4_demo_menu_state($id) {
    $menu = wp_get_nav_menu_object($id);
    if (!$menu) { return null; }
    $items = [];
    foreach ((array) wp_get_nav_menu_items($id, ['post_status' => 'any']) as $item) {
        $items[] = [
            'id' => (int) $item->ID, 'title' => $item->title, 'url' => $item->url,
            'type' => $item->type, 'object' => $item->object, 'object_id' => (int) $item->object_id,
            'parent' => (int) $item->menu_item_parent, 'order' => (int) $item->menu_order,
            'target' => $item->target, 'xfn' => $item->xfn, 'classes' => $item->classes,
            'description' => $item->description, 'attr_title' => $item->attr_title,
            'status' => $item->post_status,
        ];
    }
    $locations = get_nav_menu_locations();
    return ['id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug,
        'primary' => (int) ($locations['primary'] ?? 0), 'items' => $items];
}
function h4_demo_menu_entries($pages) {
    return [['title' => 'Inicio', 'id' => $pages['home']], ['title' => 'Tienda', 'id' => wc_get_page_id('shop')],
        ['title' => 'Conocenos', 'id' => $pages['conocenos']], ['title' => 'Contacto', 'id' => $pages['contacto']]];
}
function h4_demo_menu_issues($menu_id, $entries, $allow_missing = false) {
    $state = h4_demo_menu_state($menu_id);
    if (!$state) { return ['The managed primary menu is missing.']; }
    $issues = [];
    $recognized = [];
    foreach ($entries as $index => $entry) {
        $matches = array_values(array_filter($state['items'], static function ($item) use ($entry) {
            return 'post_type' === $item['type'] && 'page' === $item['object'] && (int) $entry['id'] === $item['object_id'] && $entry['title'] === $item['title'];
        }));
        if (!$matches) {
            if (!$allow_missing) { $issues[] = "The primary menu is missing {$entry['title']}."; }
            continue;
        }
        if (count($matches) !== 1) { $issues[] = "The primary menu duplicates {$entry['title']}."; continue; }
        $item = $matches[0];
        $recognized[] = $item['id'];
        if ($item['order'] !== $index + 1 || 0 !== $item['parent'] || 'publish' !== $item['status'] || '' !== $item['target'] || $item['url'] !== get_permalink($entry['id'])) {
            $issues[] = "The primary menu item {$entry['title']} does not match its native page link.";
        }
    }
    if (count($recognized) !== count($state['items'])) { $issues[] = 'The primary menu contains unexpected or duplicate items.'; }
    if (!$allow_missing && $state['primary'] !== (int) $menu_id) { $issues[] = 'The managed menu is not assigned to primary.'; }
    return $issues;
}
function h4_demo_h1_count($blocks) {
    $count = 0;
    foreach ($blocks as $block) {
        if ('core/heading' === ($block['blockName'] ?? '') && 1 === (int) ($block['attrs']['level'] ?? 2)) { $count++; }
        $count += h4_demo_h1_count($block['innerBlocks'] ?? []);
    }
    return $count;
}
function h4_demo_group($classes, $content, $tag = 'div') {
    $attrs = ['tagName' => $tag, 'className' => $classes, 'layout' => ['type' => 'default']];
    return '<!-- wp:group ' . wp_json_encode($attrs) . ' -->' . "\n" . '<' . $tag . ' class="wp-block-group ' . esc_attr($classes) . '">' . $content . '</' . $tag . '>' . "\n<!-- /wp:group -->\n";
}
function h4_demo_p($text, $classes = '') {
    $attr = $classes ? ' ' . wp_json_encode(['className' => $classes]) : '';
    return '<!-- wp:paragraph' . $attr . ' --><p' . ($classes ? ' class="' . esc_attr($classes) . '"' : '') . '>' . $text . '</p><!-- /wp:paragraph -->' . "\n";
}
function h4_demo_heading($text, $level = 2, $classes = '') {
    $attrs = ['level' => $level];
    if ($classes) { $attrs['className'] = $classes; }
    return '<!-- wp:heading ' . wp_json_encode($attrs) . ' --><h' . $level . ' class="wp-block-heading' . ($classes ? ' ' . esc_attr($classes) : '') . '">' . $text . '</h' . $level . '><!-- /wp:heading -->' . "\n";
}
function h4_demo_buttons($links, $classes = '') {
    $content = '';
    foreach ($links as $link) {
        $outline = !empty($link['secondary']);
        $attr = $outline ? ' {"className":"is-style-outline"}' : '';
        $content .= '<!-- wp:button' . $attr . ' --><div class="wp-block-button' . ($outline ? ' is-style-outline' : '') . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></div><!-- /wp:button -->' . "\n";
    }
    $attr = $classes ? ' ' . wp_json_encode(['className' => $classes]) : '';
    return '<!-- wp:buttons' . $attr . ' --><div class="wp-block-buttons' . ($classes ? ' ' . esc_attr($classes) : '') . '">' . $content . '</div><!-- /wp:buttons -->' . "\n";
}
function h4_demo_image($file, $alt, $classes = '') {
    $ratio = 'editorial-specimen.webp' === $file ? '4/5' : '3/2';
    $attrs = ['sizeSlug' => 'full', 'linkDestination' => 'none', 'aspectRatio' => $ratio, 'scale' => 'cover'];
    if ($classes) { $attrs['className'] = $classes; }
    return '<!-- wp:image ' . wp_json_encode($attrs) . ' --><figure class="wp-block-image size-full' . ($classes ? ' ' . esc_attr($classes) : '') . '"><img src="' . esc_url(get_theme_file_uri('assets/images/' . $file)) . '" alt="' . esc_attr($alt) . '" style="aspect-ratio:' . $ratio . ';object-fit:cover"/></figure><!-- /wp:image -->' . "\n";
}
function h4_demo_intro($eyebrow, $title, $lead) {
    return h4_demo_group('cb-page-intro', h4_demo_group('cb-container',
        h4_demo_p(esc_html($eyebrow), 'cb-eyebrow') . h4_demo_heading(esc_html($title), 1) . h4_demo_p(esc_html($lead), 'cb-page-lead')), 'section');
}
function h4_demo_contents($urls, $term_ids) {
    $hero = h4_demo_group('cb-home-hero cb-hero', h4_demo_group('cb-container cb-home-hero-grid',
        h4_demo_group('cb-home-hero-copy cb-hero-copy',
            h4_demo_p('TRAMA / Ropa para todos los días', 'cb-eyebrow') .
            h4_demo_heading('Vestir lo<br>cotidiano.', 1, 'cb-hero-title') .
            h4_demo_p('Prendas simples, texturas que se sienten y espacio para moverte. Ropa para tu manera de habitar la ciudad.', 'cb-hero-description')) .
        h4_demo_image('editorial-specimen.webp', 'Sobrecamisa verde oliva sobre una remera cruda, junto a una pared iluminada por el sol', 'cb-home-hero-media cb-hero-media') .
        h4_demo_buttons([['url' => $urls['shop'], 'label' => 'Ver catálogo'], ['url' => $urls['conocenos'], 'label' => 'Conocé TRAMA', 'secondary' => true]], 'cb-home-hero-actions cb-hero-actions')), 'section');
    $products = h4_demo_group('cb-home-products cb-section', h4_demo_group('cb-container',
        h4_demo_group('cb-section-heading', h4_demo_group('cb-section-heading-copy', h4_demo_p('Para empezar', 'cb-eyebrow') . h4_demo_heading('A mano, todos los días.')) .
            h4_demo_p('Una selección del catálogo, lista para recorrer.')) . '<!-- wp:commerce-base/product-grid {"limit":4} /-->' . "\n" .
        h4_demo_buttons([['url' => $urls['shop'], 'label' => 'Ver todo el catálogo', 'secondary' => true]], 'cb-section-actions')), 'section');
    $categories = h4_demo_group('cb-category-section cb-section', h4_demo_group('cb-container',
        h4_demo_group('cb-section-heading', h4_demo_group('cb-section-heading-copy', h4_demo_p('Encontrá tu punto de partida', 'cb-eyebrow') . h4_demo_heading('Por categoría.'))) .
        '<!-- wp:commerce-base/category-links ' . wp_json_encode(['termIds' => array_map('intval', $term_ids), 'limit' => 4]) . ' /-->' . "\n"), 'section');
    $story = h4_demo_group('cb-story cb-section cb-home-lookbook', h4_demo_group('cb-container cb-story-grid',
        h4_demo_image('editorial-detail.webp', 'Persona con remera cruda y pantalón oscuro sentada sobre piedra, con una sobrecamisa oliva al lado', 'cb-story-media') .
        h4_demo_group('cb-story-copy', h4_demo_p('Notas de ciudad / 01', 'cb-eyebrow') . h4_demo_heading('El detalle<br>cambia el ritmo.') .
            h4_demo_p('Una trama, un pliegue, la luz de la tarde. Nos gustan las prendas que dejan lugar a lo que pasa alrededor.') .
            h4_demo_buttons([['url' => $urls['shop'], 'label' => 'Recorrer la colección', 'secondary' => true]]))), 'section');
    $about = h4_demo_group('cb-home-about cb-section', h4_demo_group('cb-container cb-home-about-grid',
        h4_demo_p('Conocé TRAMA', 'cb-eyebrow') . h4_demo_heading('Menos vueltas.<br>Más uso.') .
        h4_demo_group('cb-home-about-copy', h4_demo_p('Pensamos ropa para repetir, combinar y hacer propia. Una mirada urbana, de cerca: proporciones cómodas, tonos que conviven y atención a las texturas.') .
            h4_demo_buttons([['url' => $urls['conocenos'], 'label' => 'Nuestra forma de mirar', 'secondary' => true]]))), 'section');
    $benefits = h4_demo_group('cb-home-benefits cb-section', h4_demo_group('cb-container cb-benefits-grid',
        h4_demo_group('cb-benefit', h4_demo_p('01', 'cb-index') . h4_demo_heading('Elegí con información.', 2) . h4_demo_p('Consultá los talles, las variantes y la disponibilidad en cada producto.')) .
        h4_demo_group('cb-benefit', h4_demo_p('02', 'cb-index') . h4_demo_heading('Revisá antes de confirmar.', 2) . h4_demo_p('El carrito reúne tus productos y el checkout muestra las opciones disponibles para tu pedido.')) .
        h4_demo_group('cb-benefit', h4_demo_p('03', 'cb-index') . h4_demo_heading('Tus dudas tienen lugar.', 2) . h4_demo_p('Encontrá respuestas sobre la compra en nuestras <a class="cb-link" href="' . esc_url($urls['faq']) . '">preguntas frecuentes</a>.'))), 'section');

    $about_page = h4_demo_intro('Conocenos / TRAMA', 'Ropa para el ritmo de todos los días.', 'Nos interesa lo que una prenda puede acompañar: el viaje, una charla larga, los planes que aparecen sobre la marcha.') .
        h4_demo_group('cb-story cb-section', h4_demo_group('cb-container cb-story-grid',
            h4_demo_image('editorial-specimen.webp', 'Una sobrecamisa de textura marcada en verde oliva, combinada con algodón crudo', 'cb-story-media') .
            h4_demo_group('cb-story-copy', h4_demo_p('Una mirada cercana', 'cb-eyebrow') . h4_demo_heading('La ciudad como punto de partida.') .
                h4_demo_p('TRAMA es una marca ficticia que nace de una idea sencilla: vestir lo cotidiano sin quitarle carácter. Miramos los colores de la calle, las superficies gastadas y las formas de moverse por la ciudad.') .
                h4_demo_p('Nos quedamos con siluetas simples y combinaciones abiertas. El estilo aparece cuando cada persona las hace propias.'))), 'section') .
        h4_demo_group('cb-about-principles cb-section', h4_demo_group('cb-container',
            h4_demo_group('cb-section-heading', h4_demo_p('Lo que guía la propuesta', 'cb-eyebrow') . h4_demo_heading('Forma, textura, movimiento.')) .
            h4_demo_group('cb-benefits-grid',
                h4_demo_group('cb-benefit', h4_demo_heading('Formas abiertas.', 3) . h4_demo_p('Prendas que conversan entre sí y dejan espacio para combinarlas a tu manera.')) .
                h4_demo_group('cb-benefit', h4_demo_heading('Textura a la vista.', 3) . h4_demo_p('Una paleta tranquila y detalles que se aprecian de cerca.')) .
                h4_demo_group('cb-benefit', h4_demo_heading('Movimiento cotidiano.', 3) . h4_demo_p('La comodidad como parte de la composición, desde el primer boceto.'))) .
            h4_demo_buttons([['url' => $urls['shop'], 'label' => 'Ver catálogo']], 'cb-section-actions')), 'section');

    $contact_page = h4_demo_intro('Contacto / TRAMA', 'Hablemos.', 'Si tenés una pregunta sobre una prenda o tu compra, este es el lugar para encontrar cómo acercarte.') .
        h4_demo_group('cb-contact-section cb-section', h4_demo_group('cb-container cb-contact-layout',
            h4_demo_group('cb-contact-note cb-copy', h4_demo_heading('Los canales están en preparación.') .
                h4_demo_p('TRAMA es una tienda de demostración. Los datos de contacto todavía no están habilitados; por eso no vas a encontrar teléfonos, perfiles ni direcciones de terceros.') .
                h4_demo_p('Mientras tanto, podés consultar las <a class="cb-link" href="' . esc_url($urls['faq']) . '">preguntas frecuentes</a> para conocer cómo está organizada la compra.')) .
            h4_demo_group('cb-contact-channels',
                h4_demo_group('cb-contact-channel', h4_demo_heading('WhatsApp', 2) . h4_demo_p('Canal de atención a configurar.')) .
                h4_demo_group('cb-contact-channel', h4_demo_heading('Instagram', 2) . h4_demo_p('Perfil de la marca a configurar.')) .
                h4_demo_group('cb-contact-channel', h4_demo_heading('Email', 2) . h4_demo_p('Dirección de contacto a configurar.')) .
                h4_demo_group('cb-contact-channel', h4_demo_heading('Horario y ubicación', 2) . h4_demo_p('La atención y una eventual ubicación se informarán cuando estén definidas.')))), 'section');

    $faq_items = [
        ['¿Cómo elijo mi talle?', 'Revisá las opciones de talle y la información de la ficha del producto. Si falta una medida, esperá a contar con esa información antes de elegir. La guía de talles se completará junto con el catálogo.'],
        ['¿Qué medios de pago aceptan?', 'Esta tienda está en modo demostración y por ahora no recibe pagos. En la tienda publicada, los medios habilitados se mostrarán en el checkout antes de confirmar el pedido.'],
        ['¿Hacen envíos?', 'El checkout muestra las opciones disponibles para la dirección que ingreses. La cobertura y las condiciones de envío se informarán cuando estén definidas para la tienda publicada.'],
        ['¿Cuánto demora el envío?', 'El plazo depende del destino y del servicio disponible. No publicamos una promesa de entrega para esta demo. Consultá la información de envío de la tienda antes de confirmar una compra.'],
        ['¿Puedo cambiar un producto?', 'Las condiciones y el procedimiento de cambios se informarán antes de que la tienda reciba compras. Esta respuesta orientativa no reemplaza la futura política de cambios ni los derechos que correspondan.'],
        ['¿Necesito una cuenta para comprar?', 'Podés avanzar como invitado. Si elegís crear una cuenta, vas a poder consultar tus pedidos desde Mi cuenta. La cuenta no es un requisito para recorrer el catálogo.'],
        ['¿Cómo reviso mi pedido?', 'Si compraste con una cuenta, entrá a <a class="cb-link" href="' . esc_url($urls['account']) . '">Mi cuenta</a> para consultar tus pedidos. Como invitado, conservá el correo de confirmación y el número de pedido. Si necesitás ayuda, consultá los canales disponibles en <a class="cb-link" href="' . esc_url($urls['contacto']) . '">Contacto</a>.'],
    ];
    $details = '';
    foreach ($faq_items as [$question, $answer]) {
        $details .= '<!-- wp:details {"className":"cb-faq-item"} --><details class="wp-block-details cb-faq-item"><summary>' . esc_html($question) . '</summary>' . h4_demo_p($answer) . '</details><!-- /wp:details -->' . "\n";
    }
    $faq_page = h4_demo_intro('Ayuda / TRAMA', 'Preguntas frecuentes.', 'Respuestas breves para orientarte antes de elegir y al revisar tu pedido.') .
        h4_demo_group('cb-faq-section cb-section', h4_demo_group('cb-container cb-faq-layout',
            h4_demo_group('cb-faq-note cb-copy', h4_demo_p('Sobre esta tienda', 'cb-eyebrow') . h4_demo_heading('Antes de comprar.') .
                h4_demo_p('Esta es una demo. La información de envíos, pagos y cambios se completará antes de recibir compras. Acá podés conocer la estructura de la experiencia.')) .
            h4_demo_group('cb-faq-list', $details)), 'section');
    return ['home' => $hero . $products . $categories . $story . $about . $benefits,
        'conocenos' => $about_page, 'contacto' => $contact_page, 'faq' => $faq_page];
}

$definitions = [
    'home' => ['slug' => 'inicio', 'title' => 'Inicio', 'template' => ''],
    'conocenos' => ['slug' => 'conocenos', 'title' => 'Conocenos', 'template' => 'page-institutional'],
    'contacto' => ['slug' => 'contacto', 'title' => 'Contacto', 'template' => 'page-institutional'],
    'faq' => ['slug' => 'preguntas-frecuentes', 'title' => 'Preguntas frecuentes', 'template' => 'page-institutional'],
];
$manifest = get_option(H4_DEMO_OPTION, []);
if ($manifest && (!is_array($manifest) || 1 !== ($manifest['format'] ?? null))) { WP_CLI::error('Unknown H4 content manifest; inspect it before continuing.'); }
$protected = array_filter(array_map('intval', [get_option('woocommerce_shop_page_id'), get_option('woocommerce_cart_page_id'),
    get_option('woocommerce_checkout_page_id'), get_option('woocommerce_myaccount_page_id'), get_option('woocommerce_terms_page_id'),
    get_option('wp_page_for_privacy_policy'), get_option('page_for_posts')]));
$found = [];
$conflicts = [];
$category_definitions = ['remeras' => 'Remeras', 'buzos' => 'Buzos', 'pantalones' => 'Pantalones', 'accesorios' => 'Accesorios'];
$category_ids = [];
foreach ($category_definitions as $slug => $name) {
    $stored_id = (int) ($manifest['categories'][$slug] ?? 0);
    $term = $stored_id ? get_term($stored_id, 'product_cat') : get_term_by('slug', $slug, 'product_cat');
    if ($stored_id && (!$term || is_wp_error($term))) { $conflicts[] = "A structural category ($slug) was removed; inspect it before seeding."; }
    $category_ids[$slug] = $term && !is_wp_error($term) ? (int) $term->term_id : 0;
}
foreach ($definitions as $key => $definition) {
    $entry = $manifest['pages'][$key] ?? null;
    $post = $entry ? get_post((int) $entry['id']) : get_page_by_path($definition['slug'], OBJECT, 'page');
    if ($entry && !$post) { $conflicts[] = "Managed page $key is missing."; }
    if ($post && in_array((int) $post->ID, $protected, true)) { $conflicts[] = "Page $key overlaps a protected WordPress/WooCommerce page."; }
    if ($post && (!$entry || $post->post_name !== $definition['slug'] || get_post_meta($post->ID, H4_DEMO_MARKER, true) !== $key)) {
        $conflicts[] = "Slug {$definition['slug']} already exists or its management marker changed; it will not be replaced.";
    }
    if ($entry && $post && !hash_equals((string) $entry['hash'], h4_demo_hash(h4_demo_page_state($post->ID)))) {
        $conflicts[] = "Managed page $key has editor changes; the seed will not overwrite them.";
    }
    $found[$key] = $post ? (int) $post->ID : 0;
}
if ($manifest) {
    if (isset($manifest['front_hash']) && !hash_equals($manifest['front_hash'], h4_demo_hash(h4_demo_front_state()))) { $conflicts[] = 'The front-page selection was edited.'; }
    if (isset($manifest['identity_hash']) && !hash_equals($manifest['identity_hash'], h4_demo_hash(h4_demo_identity_state()))) { $conflicts[] = 'The WordPress brand identity was edited.'; }
    if (isset($manifest['menu']) && !hash_equals($manifest['menu']['hash'], h4_demo_hash(h4_demo_menu_state($manifest['menu']['id'])))) { $conflicts[] = 'The managed primary menu or its assignment was edited.'; }
    if (!empty($manifest['menu']['id'])) { $conflicts = array_merge($conflicts, h4_demo_menu_issues($manifest['menu']['id'], h4_demo_menu_entries($found), true)); }
} else {
    if ('posts' !== get_option('show_on_front') || (int) get_option('page_on_front') !== 0) { $conflicts[] = 'A front page is already configured; inspect it instead of replacing it.'; }
    $locations = get_nav_menu_locations();
    if (!empty($locations['primary'])) { $conflicts[] = 'A primary navigation menu is already assigned; it will not be replaced.'; }
    if (wp_get_nav_menu_object('trama-principal')) { $conflicts[] = 'The demo menu slug already exists without an H4 manifest.'; }
}

if ('seed' === $mode) {
    if ($conflicts) { WP_CLI::error(implode(' ', $conflicts) . ' No H4 content was changed.'); }
    foreach (['editorial-specimen.webp', 'editorial-detail.webp'] as $image) {
        if (!is_file(get_theme_file_path('assets/images/' . $image))) { WP_CLI::error("Missing local editorial image: $image. Synchronize the complete theme before seeding."); }
    }
    if (!$manifest) {
        $manifest = ['format' => 1, 'pages' => [], 'created_at_utc' => gmdate('c'),
            'front_hash' => h4_demo_hash(h4_demo_front_state()), 'identity_hash' => h4_demo_hash(h4_demo_identity_state())];
        update_option(H4_DEMO_OPTION, $manifest, false);
    }
    // Structural navigation terms only: no fixture is assigned or changed.
    foreach ($category_definitions as $slug => $name) {
        if (!$category_ids[$slug]) {
            $created = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
            if (is_wp_error($created)) { WP_CLI::error('Could not create an empty structural category: ' . $created->get_error_message()); }
            $category_ids[$slug] = (int) $created['term_id'];
        }
        $manifest['categories'][$slug] = $category_ids[$slug];
        update_option(H4_DEMO_OPTION, $manifest, false);
    }
    // Create only new draft pages first so all links can use their real native permalinks.
    foreach ($definitions as $key => $definition) {
        if ($found[$key]) { continue; }
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_name' => $definition['slug'], 'post_title' => $definition['title'],
            'comment_status' => 'closed', 'ping_status' => 'closed', 'meta_input' => [H4_DEMO_MARKER => $key]], true);
        if (is_wp_error($id)) { WP_CLI::error('Could not create an H4 draft: ' . $id->get_error_message()); }
        $found[$key] = (int) $id;
        $manifest['pages'][$key] = ['id' => (int) $id, 'hash' => h4_demo_hash(h4_demo_page_state($id))];
        update_option(H4_DEMO_OPTION, $manifest, false);
    }
    $urls = ['shop' => wc_get_page_permalink('shop'), 'account' => wc_get_page_permalink('myaccount')];
    foreach ($found as $key => $id) { $urls[$key] = get_permalink($id); }
    $contents = h4_demo_contents($urls, array_values($category_ids));
    foreach ($definitions as $key => $definition) {
        $id = $found[$key];
        $post = get_post($id);
        if ($post->post_content !== $contents[$key] || $post->post_status !== 'publish' || $post->post_title !== $definition['title'] || get_page_template_slug($id) !== $definition['template']) {
            $saved = wp_update_post(wp_slash(['ID' => $id, 'post_title' => $definition['title'], 'post_content' => $contents[$key], 'post_status' => 'publish']), true);
            if (is_wp_error($saved)) { WP_CLI::error('Could not save H4 content: ' . $saved->get_error_message()); }
            update_post_meta($id, '_wp_page_template', $definition['template'] ?: 'default');
        }
        $manifest['pages'][$key] = ['id' => $id, 'hash' => h4_demo_hash(h4_demo_page_state($id))];
        update_option(H4_DEMO_OPTION, $manifest, false);
    }
    update_option('show_on_front', 'page');
    update_option('page_on_front', $found['home']);
    update_option('blogname', 'TRAMA');
    update_option('blogdescription', 'Ropa para todos los días.');
    $manifest['front_hash'] = h4_demo_hash(h4_demo_front_state());
    $manifest['identity_hash'] = h4_demo_hash(h4_demo_identity_state());
    update_option(H4_DEMO_OPTION, $manifest, false);
    $menu_id = (int) ($manifest['menu']['id'] ?? 0);
    if (!$menu_id) {
        $created = wp_create_nav_menu('TRAMA · principal');
        if (is_wp_error($created)) { WP_CLI::error('Could not create the demo navigation: ' . $created->get_error_message()); }
        $menu_id = (int) $created;
        $renamed = wp_update_term($menu_id, 'nav_menu', ['slug' => 'trama-principal']);
        if (is_wp_error($renamed)) { WP_CLI::error('Could not assign the demo navigation slug.'); }
        $manifest['menu'] = ['id' => $menu_id, 'hash' => h4_demo_hash(h4_demo_menu_state($menu_id))];
        update_option(H4_DEMO_OPTION, $manifest, false);
    }
    // A stored partial menu can resume after a failed seed. Existing native items
    // are matched by object, page ID and title; only missing items are inserted.
    $menu_entries = h4_demo_menu_entries($found);
    $menu_items = (array) wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);
    foreach ($menu_entries as $index => $entry) {
        $matches = array_values(array_filter($menu_items, static function ($item) use ($entry) {
            return 'post_type' === $item->type && 'page' === $item->object && (int) $entry['id'] === (int) $item->object_id && $entry['title'] === $item->title;
        }));
        if ($matches) { continue; }
        $item = wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => $entry['title'], 'menu-item-object' => 'page',
            'menu-item-object-id' => $entry['id'], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $index + 1]);
        if (is_wp_error($item)) { WP_CLI::error('Could not create a demo navigation item.'); }
        $manifest['menu']['hash'] = h4_demo_hash(h4_demo_menu_state($menu_id));
        update_option(H4_DEMO_OPTION, $manifest, false);
    }
    $locations = get_nav_menu_locations();
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
    $manifest['menu'] = ['id' => $menu_id, 'hash' => h4_demo_hash(h4_demo_menu_state($menu_id))];
    $manifest['front_hash'] = h4_demo_hash(h4_demo_front_state());
    $manifest['identity_hash'] = h4_demo_hash(h4_demo_identity_state());
    $manifest['updated_at_utc'] = gmdate('c');
    update_option(H4_DEMO_OPTION, $manifest, false);
}

$pages = [];
foreach ($found as $key => $id) { $pages[$key] = ['id' => $id, 'url' => $id ? get_permalink($id) : null]; }
$urls = ['home' => home_url('/')];
foreach ($pages as $key => $page) { if ('home' !== $key) { $urls[$key] = $page['url']; } }
$urls['shop'] = wc_get_page_permalink('shop');
$urls['search'] = add_query_arg(['s' => 'h2', 'post_type' => 'product'], home_url('/'));
$urls['notFound'] = home_url('/pagina-inexistente/');
$pending = [];
foreach ($definitions as $key => $definition) {
    $post = $found[$key] ? get_post($found[$key]) : null;
    if (!$post || 'publish' !== $post->post_status || get_page_template_slug($found[$key]) !== $definition['template'] || 1 !== h4_demo_h1_count(parse_blocks($post->post_content))) {
        $pending[] = "Page $key must be published, use its intended template and contain exactly one native H1.";
    }
}
if ('page' !== get_option('show_on_front') || !$found['home'] || (int) get_option('page_on_front') !== $found['home']) { $pending[] = 'The native static front page is not the managed Home.'; }
if (empty($manifest['menu']['id'])) { $pending[] = 'The managed four-link primary menu is not complete.'; }
else { $pending = array_merge($pending, h4_demo_menu_issues($manifest['menu']['id'], h4_demo_menu_entries($found))); }
$complete = $manifest && !$pending && count(array_filter($found)) === count($definitions) && count(array_filter($category_ids)) === count($category_definitions);
if ('verify' === $mode && (!$complete || $conflicts)) { WP_CLI::error('H4 content verification failed. ' . implode(' ', array_merge($conflicts, $pending))); }
echo wp_json_encode(['result' => $conflicts ? 'conflict' : ($complete ? 'passed' : 'ready'), 'mode' => $mode,
    'instance' => 'local', 'pages' => $pages, 'urls' => $urls, 'conflicts' => $conflicts, 'pending' => $pending,
    'menu_id' => (int) ($manifest['menu']['id'] ?? 0), 'front_page_id' => (int) get_option('page_on_front'), 'categories' => $category_ids,
    'native_blocks' => true, 'products_orders_settings_changed' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
