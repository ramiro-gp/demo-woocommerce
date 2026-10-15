<?php
/** Inspect instances; conservatively trash only untouched WordPress sample content. */
if (!defined('WP_CLI') || !WP_CLI || 'local' !== wp_get_environment_type()) { exit(1); }
$mode = $args[0] ?? 'inspect';
if (!in_array($mode, ['inspect', 'clean'], true)) { WP_CLI::error('Use inspect|clean.'); }
if ('clean' === $mode && (($args[1] ?? '') !== home_url('/') || 'http://localhost:8080/' !== home_url('/'))) { WP_CLI::error('Cleanup is limited to the original demo at 8080; pass its exact home URL as confirmation.'); }
$protected = array_filter(array_map('intval', [get_option('page_on_front'), get_option('page_for_posts'), get_option('wp_page_for_privacy_policy'), get_option('woocommerce_shop_page_id'), get_option('woocommerce_cart_page_id'), get_option('woocommerce_checkout_page_id'), get_option('woocommerce_myaccount_page_id'), get_option('woocommerce_terms_page_id')]));
$posts = get_posts(['post_type' => ['post', 'page'], 'post_status' => ['publish', 'draft', 'trash'], 'numberposts' => -1]);
$samples = [];
foreach ($posts as $post) {
    if (in_array($post->ID, $protected, true) || 'trash' === $post->post_status || $post->post_modified !== $post->post_date) { continue; }
    $text = trim(wp_strip_all_tags($post->post_content));
    $hello = 'post' === $post->post_type && in_array($post->post_name, ['hello-world', 'hola-mundo'], true)
        && in_array($post->post_title, ['Hello world!', '¡Hola, mundo!'], true)
        && in_array($text, ['Welcome to WordPress. This is your first post. Edit or delete it, then start writing!', 'Bienvenido a WordPress. Esta es tu primera entrada. Edítala o bórrala, ¡y comienza a escribir!'], true);
    $sample = 'page' === $post->post_type && in_array($post->post_name, ['sample-page', 'pagina-ejemplo'], true)
        && in_array($post->post_title, ['Sample Page', 'Página de ejemplo'], true)
        && (str_starts_with($text, "This is an example page. It's different from a blog post because it will stay in one place") || str_starts_with($text, 'Esta es una página de ejemplo.'))
        && (str_contains($text, 'XYZ Doohickey Company') || str_contains($text, 'empresa XYZ'));
    if (!$hello && !$sample) { continue; }
    $samples[] = ['id' => $post->ID, 'title' => $post->post_title, 'slug' => $post->post_name];
    if ('clean' === $mode) {
        foreach (get_comments(['post_id' => $post->ID, 'status' => 'all']) as $comment) {
            if (in_array($comment->comment_author, ['A WordPress Commenter', 'Un comentarista de WordPress'], true)
                && (str_starts_with($comment->comment_content, 'Hi, this is a comment.') || str_starts_with($comment->comment_content, 'Hola, esto es un comentario.'))) {
                wp_trash_comment($comment->comment_ID);
            }
        }
        if (!wp_trash_post($post->ID)) { WP_CLI::error('Could not trash identified sample.'); }
    }
}
$products = [];
if (function_exists('wc_get_products')) {
    foreach (wc_get_products(['limit' => -1]) as $product) { $products[] = ['id' => $product->get_id(), 'type' => $product->get_type(), 'url' => $product->get_permalink(), 'attributes' => $product->get_attributes()]; }
}
echo wp_json_encode(['home' => home_url('/'), 'theme' => get_stylesheet(), 'name' => get_bloginfo('name'), 'front' => get_option('show_on_front'), 'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null, 'sample_candidates' => $samples, 'action' => $mode, 'products' => $products, 'pages' => array_map(static function ($post) { return ['id' => $post->ID, 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => get_post_status($post->ID)]; }, $posts)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
