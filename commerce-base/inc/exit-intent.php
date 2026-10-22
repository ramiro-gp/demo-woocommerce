<?php
/** Optional presentation reminder; WooCommerce remains the cart authority. */
if (!defined('ABSPATH')) { exit; }
function commerce_base_exit_intent_enabled() {
    if (class_exists('CommerceCore\\Legal') && \CommerceCore\Legal::is_page()) { return false; }
    return function_exists('WC') && !is_admin() && !is_checkout() && !is_account_page() && !is_wc_endpoint_url('order-pay') && !is_wc_endpoint_url('order-received') && !is_customize_preview();
}
add_action('wp_enqueue_scripts', static function () {
    if (!commerce_base_exit_intent_enabled()) { return; }
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('commerce-base-exit-intent', get_theme_file_uri('assets/css/exit-intent.css'), ['commerce-base'], $version);
    wp_enqueue_script('commerce-base-exit-intent', get_theme_file_uri('assets/js/exit-intent.js'), ['wp-data', 'wc-blocks-data-store'], $version, true);
});
add_action('wp_footer', static function () {
    if (!commerce_base_exit_intent_enabled()) { return; }
    $config = apply_filters('commerce_base_exit_intent_config', [
        'enabled' => true, 'minimumSeconds' => 15, 'cooldownHours' => 24,
        'title' => __('¿Te vas sin tus cosas?', 'commerce-base'),
        'text' => __('Tu carrito sigue acá.', 'commerce-base'),
        'primary' => is_cart() ? __('Seguir con mi compra', 'commerce-base') : __('Ver carrito', 'commerce-base'),
        'secondary' => __('Seguir comprando', 'commerce-base'),
    ]);
    if (empty($config['enabled'])) { return; }
    ?>
    <dialog class="cb-exit-intent" data-cb-exit-intent data-minimum-seconds="<?php echo esc_attr(max(15, (float) $config['minimumSeconds'])); ?>" data-cooldown-hours="<?php echo esc_attr(max(24, (float) $config['cooldownHours'])); ?>" aria-modal="true" aria-labelledby="cb-exit-title" aria-describedby="cb-exit-text">
      <button type="button" class="cb-exit-close" data-exit-close aria-label="<?php esc_attr_e('Cerrar recordatorio', 'commerce-base'); ?>">×</button>
      <h2 id="cb-exit-title" tabindex="-1"><?php echo esc_html($config['title']); ?></h2>
      <p id="cb-exit-text"><?php echo esc_html($config['text']); ?></p>
      <div class="cb-exit-actions">
        <?php if (is_cart()) : ?><button type="button" class="cb-exit-primary" data-exit-close><?php echo esc_html($config['primary']); ?></button>
        <?php else : ?><a class="cb-exit-primary" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo esc_html($config['primary']); ?></a><button type="button" class="cb-exit-secondary" data-exit-close><?php echo esc_html($config['secondary']); ?></button><?php endif; ?>
      </div>
    </dialog>
    <?php
}, 5);
