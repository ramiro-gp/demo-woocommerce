<?php
/** Minimal metadata fallback for stores without an SEO plugin. Core/WC own titles and JSON-LD. */
defined('ABSPATH') || exit;
function commerce_base_metadata_enabled() {
    $provider = defined('WPSEO_VERSION') || defined('SEOPRESS_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('THE_SEO_FRAMEWORK_VERSION');
    return (bool) apply_filters('commerce_base_metadata_enabled', !$provider);
}
add_filter('wp_robots', static function($robots) {
    $private = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
    $filtered = false;
    foreach (array_keys($_GET) as $key) {
        if (str_starts_with($key, 'filter_') || str_starts_with($key, 'query_type_') || in_array($key, ['min_price','max_price','rating_filter','filtering'], true)) { $filtered=true; break; }
    }
    $hidden = false;
    if (function_exists('is_product') && is_product()) { $p=wc_get_product(get_queried_object_id()); $hidden=$p && $p->get_catalog_visibility()==='hidden'; }
    if ($private || $filtered || $hidden) { $robots['noindex']=true; unset($robots['index']); }
    return $robots;
});
add_action('wp_head', static function() {
    if (!commerce_base_metadata_enabled() || is_404() || is_search() || is_preview() || is_feed()
        || (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page()))) { return; }
    $description='';$canonical='';$image='';$type='website';
    if (is_singular()) {
        $post=get_queried_object();
        if (!$post instanceof WP_Post || post_password_required($post)) { return; }
        $description=$post->post_excerpt ?: $post->post_content;
        $canonical=wp_get_canonical_url($post); // Core renders the single canonical tag.
        $image=get_the_post_thumbnail_url($post, 'large') ?: '';
        if (function_exists('is_product') && is_product()) {
            $product=wc_get_product($post->ID);
            if ($product) { $description=$product->get_short_description() ?: $product->get_description();$type='product'; }
        }
    } elseif (is_tax()) {
        $term=get_queried_object();$url=get_term_link($term);
        if(is_wp_error($url)){return;}
        $description=term_description();$canonical=$url;
    } elseif (function_exists('is_shop') && is_shop()) {
        $description=get_post_field('post_excerpt',wc_get_page_id('shop')) ?: get_bloginfo('description');
        $canonical=wc_get_page_permalink('shop');
    } else { return; }
    if (is_paged()) { $canonical=get_pagenum_link(max(1,get_query_var('paged')),false); }
    $description=trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(strip_shortcodes($description))));
    $description=wp_html_excerpt($description,160,'…');
    if($description!==''){echo '<meta name="description" content="'.esc_attr($description).'">'."\n";}
    if(!$canonical){return;}
    if(!is_singular()){echo '<link rel="canonical" href="'.esc_url($canonical).'">'."\n";}
    foreach(['og:type'=>$type,'og:title'=>wp_get_document_title(),'og:site_name'=>get_bloginfo('name'),'og:url'=>$canonical,'og:description'=>$description,'og:image'=>$image] as $property=>$value){
        if($value!==''){echo '<meta property="'.esc_attr($property).'" content="'.esc_attr($value).'">'."\n";}
    }
    echo '<meta name="twitter:card" content="'.($image?'summary_large_image':'summary').'">'."\n";
}, 5);
