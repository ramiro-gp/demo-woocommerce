<?php
/** Local, guarded WooCommerce catalog fixture. The theme never reads fixture data. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'seed', 'verify'], true)) { WP_CLI::error('Use inspect, seed or verify.'); }
if (wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8080') { WP_CLI::error('H5 is restricted to the existing local demo at 8080.'); }
if (!class_exists('WooCommerce')) { WP_CLI::error('WooCommerce must already be installed.'); }
$root = '/opt/demo-catalog/';
$source = json_decode(file_get_contents($root . 'h5-catalog.json'), true, 512, JSON_THROW_ON_ERROR);
$guide_source = json_decode(file_get_contents($root . 'size-guides.json'), true, 512, JSON_THROW_ON_ERROR);
$source_hash = hash_file('sha256', $root . 'h5-catalog.json');
$GLOBALS['trama_h5_state'] = array_replace(['products' => [], 'media' => [], 'terms' => [], 'retired' => []], (array) get_option('trama_h5_catalog', []));
$GLOBALS['trama_h5_root'] = $root;
$state =& $GLOBALS['trama_h5_state'];
$conflicts = [];

function h5_fingerprint($product) {
    if (!$product) { return ''; }
    $data = $product->get_data();
    unset($data['date_created'], $data['date_modified'], $data['sales'], $data['total_sales'], $data['rating_counts'], $data['average_rating'], $data['review_count'], $data['meta_data']);
    $data['attributes'] = array_map(static fn($attribute) => $attribute instanceof WC_Product_Attribute ? $attribute->get_data() : $attribute, $data['attributes']);
    $data['care'] = $product->get_meta('_cb_care');
    $data['guide'] = $product->get_meta('_cb_size_guide');
    $data['children'] = [];
    foreach ($product->get_children() as $id) { $data['children'][$id] = h5_fingerprint(wc_get_product($id)); }
    return hash('sha256', wp_json_encode($data));
}
foreach ($source['products'] as $row) {
    $id = wc_get_product_id_by_sku($row['sku']);
    $saved = $state['products'][$row['sku']] ?? null;
    if ($saved && (!$id || $id !== $saved['id'] || h5_fingerprint(wc_get_product($id)) !== $saved['hash'])) { $conflicts[] = $row['sku'] . ': product, stock or variation changed; preserve and review manually.'; }
    if ($id && !$saved) { $conflicts[] = $row['sku'] . ': SKU already belongs to unmanaged content.'; }
    if (!$id && get_page_by_path($row['slug'], OBJECT, 'product')) { $conflicts[] = $row['slug'] . ': product slug already exists.'; }
    foreach ($row['images'] as $file) {
        if (!is_file($root . 'images/' . $file)) { $conflicts[] = 'Missing source image: ' . $file; }
    }
}
foreach ($state['media'] as $file => $item) {
    $path = get_attached_file($item['id']);
    if (!$path || !is_file($path) || hash_file('sha256', $path) !== $item['hash'] || get_post_meta($item['id'], '_wp_attachment_image_alt', true) !== $item['alt']) { $conflicts[] = 'Managed image changed: ' . $file; }
    if (is_file($root . 'images/' . $file) && hash_file('sha256', $root . 'images/' . $file) !== $item['hash']) { $conflicts[] = 'Source image changed; prepare an explicit media migration: ' . $file; }
}
foreach ($state['terms'] as $key => $item) {
    $term = get_term($item['id'], $item['taxonomy']);
    if (!$term || is_wp_error($term) || hash('sha256', wp_json_encode([$term->name, $term->slug, $term->description, get_term_meta($term->term_id, 'thumbnail_id', true)])) !== $item['hash']) { $conflicts[] = 'Managed term changed: ' . $key; }
}
if (!empty($state['source_hash']) && $state['source_hash'] !== $source_hash) { $conflicts[] = 'Fixture source changed; prepare an explicit migration instead of overwriting the store.'; }
foreach ($state['retired'] as $id => $hash) { if (h5_fingerprint(wc_get_product($id)) !== $hash) { $conflicts[] = 'Retired H2 fixture changed: ' . $id; } }
foreach ($state['size_order'] ?? [] as $id => $order) { if ((int) get_term_meta($id, 'order', true) !== $order) { $conflicts[] = 'Size order was edited; preserve merchant configuration.'; } }
if ($state['products'] && get_option('commerce_base_size_guides') !== ($state['guides'] ?? null)) { $conflicts[] = 'Size guides were edited; preserve the merchant configuration.'; }
if ($state['products'] && $guide_source !== ($state['guides'] ?? null)) { $conflicts[] = 'Size-guide source changed; prepare an explicit configuration migration.'; }
if (!empty($state['index_ready']) && get_option('woocommerce_attribute_lookup_enabled') !== 'yes') { $conflicts[] = 'Native attribute index was disabled after setup; review this setting before importing.'; }

function h5_save() { update_option('trama_h5_catalog', $GLOBALS['trama_h5_state'], false); }
function h5_term($taxonomy, $name, $slug, $description = '') {
    $state =& $GLOBALS['trama_h5_state'];
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term) {
        $created = wp_insert_term($name, $taxonomy, ['slug' => $slug, 'description' => $description]);
        if (is_wp_error($created)) { WP_CLI::error($created->get_error_message()); }
        $term = get_term($created['term_id'], $taxonomy);
    }
    // Existing taxonomy content is preserved; no wholesale category updates.
    $state['terms'][$taxonomy . '/' . $slug] = ['id' => $term->term_id, 'taxonomy' => $taxonomy, 'hash' => hash('sha256', wp_json_encode([$term->name, $term->slug, $term->description, get_term_meta($term->term_id, 'thumbnail_id', true)]))];
    h5_save();
    return (int) $term->term_id;
}
function h5_media($file, $alt) {
    $root = $GLOBALS['trama_h5_root'];
    $state =& $GLOBALS['trama_h5_state'];
    if (isset($state['media'][$file])) { return $state['media'][$file]['id']; }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = wp_tempnam($file);
    copy($root . 'images/' . $file, $tmp);
    $id = media_handle_sideload(['name' => $file, 'tmp_name' => $tmp], 0, $alt);
    if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
    update_post_meta($id, '_wp_attachment_image_alt', $alt);
    update_post_meta($id, '_trama_h5_asset', $file);
    $state['media'][$file] = ['id' => $id, 'hash' => hash_file('sha256', get_attached_file($id)), 'alt' => $alt];
    h5_save();
    return $id;
}
if ($mode === 'seed' && !$conflicts) {
    $backup = '/backups/' . ($args[1] ?? '') . '/manifest.json';
    $manifest = is_file($backup) ? json_decode(file_get_contents($backup), true) : null;
    if (!$manifest || $manifest['project'] !== 'demo-woocommerce-local') { WP_CLI::error('A verified local backup is required. Use scripts/h5.ps1 seed.'); }
    if (get_option('woocommerce_currency') !== 'ARS') { WP_CLI::error('Preserve commerce options; this catalog requires the existing ARS configuration.'); }
    foreach (['talle' => 'Talle', 'color' => 'Color'] as $slug => $label) {
        if (!wc_attribute_taxonomy_id_by_name($slug)) {
            $id = wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
            if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
        }
        if (!taxonomy_exists('pa_' . $slug)) { register_taxonomy('pa_' . $slug, ['product'], ['hierarchical' => false]); }
    }
    delete_transient('wc_attribute_taxonomies');
    $categories = [];
    foreach ($source['categories'] as $category) { $categories[$category['name']] = h5_term('product_cat', $category['name'], $category['slug'], $category['description']); }
    $collection = h5_term('product_tag', $source['collection']['name'], $source['collection']['slug'], $source['collection']['description']);
    $sizes = [];
    foreach (['S', 'M', 'L', 'XL'] as $order => $size) {
        $sizes[$size] = h5_term('pa_talle', $size, strtolower($size));
        if ((int) get_term_meta($sizes[$size], 'order', true) !== $order) { wc_set_term_order($sizes[$size], $order, 'pa_talle'); }
        $state['size_order'][$sizes[$size]] = $order;
    }
    $colors = [];
    foreach ($source['color_codes'] as $color => $code) { $colors[$color] = h5_term('pa_color', $color, sanitize_title($color)); }
    $guides = $guide_source;
    if (!$state['products']) {
        $existing_guides = get_option('commerce_base_size_guides', null);
        if ($existing_guides !== null && $existing_guides !== $guides) { WP_CLI::error('Size guides already exist; do not replace merchant data.'); }
        if ($existing_guides === null) { update_option('commerce_base_size_guides', $guides, false); }
        $state['guides'] = $guides;
        h5_save();
    }
    foreach ($source['products'] as $index => $row) {
        if (isset($state['products'][$row['sku']])) { continue; }
        $images = [];
        foreach ($row['images'] as $n => $file) { $images[] = h5_media($file, $row['name'] . ' — ' . ($row['colors'][$n] ?? 'detalle del tejido y terminaciones')); }
        $product = $row['type'] === 'variable' ? new WC_Product_Variable() : new WC_Product_Simple();
        $product->set_name($row['name']);
        $product->set_slug($row['slug']);
        $product->set_status('draft');
        $product->set_sku($row['sku']);
        $product->set_short_description($row['short']);
        $product->set_description('<p>' . esc_html($row['description']) . '</p>');
        $product->set_category_ids([$categories[$row['category']]]);
        $product->set_tag_ids($row['collection'] ? [$collection] : []);
        $product->set_featured($row['featured']);
        $product->set_image_id($images[0]);
        $product->set_gallery_image_ids(array_slice($images, 1));
        $product->set_backorders('no');
        $product->set_menu_order($index);
        $product->update_meta_data('_trama_h5_owned', 1);
        $product->update_meta_data('_cb_care', $row['care']);
        $product->update_meta_data('_cb_size_guide', $row['guide']);
        $attributes = [];
        foreach (['color' => array_map(static fn($color) => $colors[$color], $row['colors']), 'talle' => array_map(static fn($size) => $sizes[$size], $row['sizes'])] as $slug => $terms) {
            if (!$terms) { continue; }
            $attr = new WC_Product_Attribute();
            $attr->set_id(wc_attribute_taxonomy_id_by_name($slug));
            $attr->set_name('pa_' . $slug);
            $attr->set_options($terms);
            $attr->set_position($slug === 'color' ? 0 : 1);
            $attr->set_visible(true);
            $attr->set_variation($row['type'] === 'variable');
            $attributes[] = $attr;
        }
        $product->set_attributes($attributes);
        if ($row['type'] === 'simple') {
            $product->set_regular_price((string) $row['price']);
            $product->set_sale_price($row['sale'] ? (string) $row['sale'] : '');
            $product->set_manage_stock(true);
            $product->set_stock_quantity($row['stock']);
            $product->set_stock_status('instock');
        }
        $id = $product->save();
        if ($row['type'] === 'variable') {
            foreach ($row['colors'] as $color_index => $color) {
                foreach ($row['sizes'] as $size_index => $size) {
                    $variant = new WC_Product_Variation();
                    $variant->set_parent_id($id);
                    $variant->set_status('publish');
                    $variant->set_sku($row['sku'] . '-' . $source['color_codes'][$color] . '-' . $size);
                    $variant->set_attributes(['pa_color' => sanitize_title($color), 'pa_talle' => strtolower($size)]);
                    $variant->set_regular_price((string) $row['price']);
                    $variant->set_sale_price($row['sale'] ? (string) $row['sale'] : '');
                    $variant->set_image_id($images[$color_index]);
                    $variant->set_manage_stock(true);
                    $stock = $row['out_of_stock'] || ($index === 0 && $color_index === 0 && $size === 'XL') || ($index === 4 && $color === 'Negro' && $size === 'M') || ($index === 7 && $color === 'Verde' && $size === 'S') ? 0 : 3 + (($index + $size_index + $color_index) % 9);
                    $variant->set_stock_quantity($stock);
                    $variant->set_stock_status($stock ? 'instock' : 'outofstock');
                    $variant->set_backorders('no');
                    $variant->save();
                }
            }
            WC_Product_Variable::sync($id);
        }
        $product = wc_get_product($id);
        $product->set_status('publish');
        $product->save();
        wc_delete_product_transients($id);
        $state['products'][$row['sku']] = ['id' => $id, 'hash' => h5_fingerprint(wc_get_product($id))];
        h5_save();
    }
    // Technical H2 examples remain intact and addressable, outside the commercial catalog.
    foreach (['H2-SIMPLE', 'H2-VARIABLE'] as $sku) {
        $id = wc_get_product_id_by_sku($sku);
        if ($id && !isset($state['retired'][$id])) {
            $p = wc_get_product($id);
            if ($p->get_catalog_visibility() !== 'hidden') {
                $p->set_catalog_visibility('hidden'); $p->save();
            }
            $state['retired'][$id] = h5_fingerprint(wc_get_product($id));
        }
    }
    if (empty($state['index_ready'])) {
        $index_result = WP_CLI::runcommand('wc palt regenerate --force --batch-size=50', ['return' => 'all', 'exit_error' => false]);
        if ($index_result->return_code !== 0) { WP_CLI::error('Native attribute lookup regeneration failed. Products are preserved; inspect WooCommerce logs.'); }
        update_option('woocommerce_attribute_lookup_enabled', 'yes');
        $state['index_ready'] = true;
    }
    $state['source_hash'] = $source_hash;
    // Preflight verified all existing values before the native term-order update.
    foreach ($state['products'] as &$item) { $item['hash'] = h5_fingerprint(wc_get_product($item['id'])); }
    unset($item);
    h5_save();
}
$products = [];
$counts = ['simple' => 0, 'variable' => 0, 'variations' => 0, 'sale' => 0, 'featured' => 0, 'out_of_stock' => 0];
foreach ($source['products'] as $row) {
    $p = wc_get_product(wc_get_product_id_by_sku($row['sku']));
    if (!$p) { continue; }
    $counts[$p->get_type()]++;
    $counts['sale'] += (int) $p->is_on_sale();
    $counts['featured'] += (int) $p->is_featured();
    $counts['out_of_stock'] += (int) !$p->is_in_stock();
    $variants = [];
    foreach ($p->get_children() as $id) {
        $v = wc_get_product($id); $counts['variations']++;
        if (!$v->is_in_stock()) { $variants[] = $v->get_sku(); }
    }
    $products[] = ['id' => $p->get_id(), 'sku' => $p->get_sku(), 'name' => $p->get_name(), 'type' => $p->get_type(), 'url' => $p->get_permalink(), 'sale' => $p->is_on_sale(), 'featured' => $p->is_featured(), 'stock' => $p->get_stock_status(), 'images' => array_merge([$p->get_image_id()], $p->get_gallery_image_ids()), 'exhausted_variations' => $variants];
}
$categories = [];
foreach ($source['categories'] as $row) {
    $term = get_term_by('slug', $row['slug'], 'product_cat');
    if ($term) { $categories[] = ['name' => $term->name, 'count' => $term->count, 'url' => get_term_link($term)]; }
}
$result = $conflicts ? 'conflict' : ($mode === 'inspect' ? 'ready' : (count($products) === 16 ? 'passed' : 'incomplete'));
echo wp_json_encode(['result' => $result, 'conflicts' => $conflicts, 'counts' => $counts, 'products' => $products, 'categories' => $categories, 'attributes' => ['talle' => wc_attribute_taxonomy_id_by_name('talle'), 'color' => wc_attribute_taxonomy_id_by_name('color')], 'collection' => get_term_link($source['collection']['slug'], 'product_tag'), 'shop' => wc_get_page_permalink('shop'), 'cart' => wc_get_cart_url(), 'checkout' => wc_get_checkout_url(), 'hpos' => get_option('woocommerce_custom_orders_table_enabled')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if (in_array($result, ['conflict', 'incomplete'], true)) { WP_CLI::halt(1); }
