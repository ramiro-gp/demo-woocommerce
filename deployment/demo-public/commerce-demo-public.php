<?php
/**
 * Plugin Name: Commerce Public Demo Guard
 * Description: Explicitly opted-in, isolated demonstration environments only. Not part of the commercial distribution.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */
namespace CommerceDemoPublic;

defined('ABSPATH') || exit;

function enabled() {
    return defined('COMMERCE_DEMO_PUBLIC') && COMMERCE_DEMO_PUBLIC === true;
}

// An absent/false flag is a complete no-op: no hooks, options or product changes.
if (!enabled()) { return; }

const NOTICE = 'Tienda de demostración. Los productos y precios son ilustrativos. No se realizan compras reales.';
const BLOCKED = 'Esta tienda de demostración no permite realizar compras, crear cuentas ni recibir solicitudes reales. Podés explorar el catálogo y el carrito. No ingreses datos personales.';

function configuration_valid() {
    if (!defined('COMMERCE_DEMO_ALLOWED_HOST') || !is_string(COMMERCE_DEMO_ALLOWED_HOST)
        || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D', COMMERCE_DEMO_ALLOWED_HOST)
        || !in_array(wp_get_environment_type(), ['local', 'staging'], true)
        || get_option('commerce_demo_public_installation') !== 'public-demo-v1'
        || wp_parse_url(home_url(), PHP_URL_HOST) !== COMMERCE_DEMO_ALLOWED_HOST) { return false; }
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = is_string($_SERVER['HTTP_HOST']) ? wp_parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST) : false;
        if ($host !== COMMERCE_DEMO_ALLOWED_HOST) { return false; }
    }
    return true;
}

function error($configuration = false) {
    return new \WP_Error($configuration ? 'commerce_demo_configuration' : 'commerce_demo_disabled', $configuration ? 'La demostración no está disponible: configuración de seguridad pendiente.' : BLOCKED, ['status' => $configuration ? 503 : 403]);
}

function deny($configuration = false) {
    $error = error($configuration);
    if (defined('WP_CLI') && WP_CLI) { \WP_CLI::error($error->get_error_message()); }
    nocache_headers();
    // XML-RPC's specialized wp_die handler does not reliably set the HTTP code.
    status_header($configuration ? 503 : 403);
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    wp_die(esc_html($error->get_error_message()), 'Tienda de demostración', ['response' => $configuration ? 503 : 403]);
}

// These remain active even when the opt-in configuration is invalid.
add_filter('pre_wp_mail', '__return_false', PHP_INT_MAX);
add_filter('pre_http_request', static function () { return error(); }, PHP_INT_MAX);
add_filter('woocommerce_create_order', static function () { return error(); }, PHP_INT_MAX);
add_action('woocommerce_before_order_object_save', static function () {
    // WC_Abstract_Order::save catches this before either HPOS or CPT persistence.
    throw new \WC_Data_Exception('commerce_demo_disabled', BLOCKED);
}, -100);
add_action('woocommerce_pre_payment_complete', static function () { throw new \RuntimeException(BLOCKED); }, -100);
add_filter('woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX);
add_filter('woocommerce_payment_gateways', '__return_empty_array', PHP_INT_MAX);
add_filter('xmlrpc_enabled', '__return_false', PHP_INT_MAX);
add_filter('xmlrpc_methods', '__return_empty_array', PHP_INT_MAX);
add_filter('allow_password_reset', '__return_false', PHP_INT_MAX);
add_filter('authenticate', static function () { return error(); }, PHP_INT_MAX);
add_action('validate_password_reset', static function ($errors) { $errors->add('commerce_demo_disabled', BLOCKED); }, PHP_INT_MAX);
add_filter('registration_errors', static function ($errors) { $errors->add('commerce_demo_disabled', BLOCKED); return $errors; }, PHP_INT_MAX);
add_filter('woocommerce_process_registration_errors', static function ($errors) { $errors->add('commerce_demo_disabled', BLOCKED); return $errors; }, PHP_INT_MAX);
foreach (['users_can_register' => '0', 'woocommerce_enable_myaccount_registration' => 'no', 'woocommerce_enable_signup_and_login_from_checkout' => 'no', 'woocommerce_enable_checkout_login_reminder' => 'no', 'blog_public' => '0'] as $option => $value) {
    add_filter('pre_option_' . $option, static function () use ($value) { return $value; }, PHP_INT_MAX);
}

/** Run before WooCommerce form handlers, WC AJAX, WC API and Core legal handlers. */
add_action('init', static function () {
    if (!configuration_valid()) { deny(true); }
    if (array_filter((array) get_option('active_plugins', []), static function ($plugin) { return str_starts_with($plugin, 'woocommerce-mercadopago/'); })
        || array_filter(array_keys((array) get_site_option('active_sitewide_plugins', [])), static function ($plugin) { return str_starts_with($plugin, 'woocommerce-mercadopago/'); })) { deny(true); }
    if (defined('WP_CLI') && WP_CLI) { return; }
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    $path = rawurldecode(wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $entry = $GLOBALS['pagenow'] ?? '';
    if (is_admin() || in_array($entry, ['wp-login.php', 'wp-signup.php', 'wp-activate.php', 'xmlrpc.php', 'wp-cron.php'], true)
        || preg_match('~/(?:wp-login\.php|wp-signup\.php|wp-activate\.php|xmlrpc\.php|wp-cron\.php)(?:/|$)~i', $path)
        || isset($_REQUEST['action']) || isset($_REQUEST['wc-api']) || preg_match('~/wc-api(?:/|$)~i', $path)) { deny(); }
    foreach (['woocommerce_checkout_place_order', 'woocommerce_checkout_update_totals', 'woocommerce_pay', 'woocommerce_add_payment_method', 'register', 'login', 'wc_reset_password', 'save_account_details', 'save_address', 'cancel_order', 'order_again', 'wc-resend-set-password'] as $key) {
        if (isset($_REQUEST[$key])) { deny(); }
    }
    if (isset($_REQUEST['wc-ajax'])) {
        if (!is_string($_REQUEST['wc-ajax']) || !in_array($_REQUEST['wc-ajax'], ['get_refreshed_fragments', 'get_variation', 'get_cart_totals', 'add_to_cart', 'remove_from_cart', 'apply_coupon', 'remove_coupon', 'update_shipping_method'], true)) { deny(); }
        return;
    }
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        $rest = isset($_GET['rest_route']) || preg_match('~/wp-json(?:/|$)~', $path);
        $cart = $method === 'POST' && (isset($_POST['add-to-cart']) || isset($_POST['update_cart']) || isset($_POST['apply_coupon']) || isset($_POST['remove_coupon']));
        if (!$rest && !$cart) { deny(); }
    }
}, -100);

/** A strict allowlist: new WooCommerce endpoints are closed until explicitly reviewed. */
function rest_allowed($route, $method, $body = [], $depth = 0) {
    if (!is_string($route) || $depth > 3 || !is_string($method)) { return false; }
    $route = rtrim(rawurldecode($route), '/');
    $method = strtoupper($method);
    if ($route === '/wc/store/v1/batch' || $route === '/batch/v1') {
        if ($method !== 'POST' || empty($body['requests']) || !is_array($body['requests']) || count($body['requests']) > 25) { return false; }
        foreach ($body['requests'] as $child) {
            // Nested batches and paths with queries/URL authorities are unnecessary for the cart.
            if (!is_array($child) || !is_string($child['path'] ?? null) || strpbrk($child['path'], '?#') !== false
                || str_contains($child['path'], '://') || str_contains($child['path'], 'batch')
                || !rest_allowed($child['path'], $child['method'] ?? 'POST', $child['body'] ?? [], $depth + 1)) { return false; }
        }
        return true;
    }
    if (preg_match('~^/wc/store/v1/cart(?:/items(?:/[a-f0-9]+)?|/coupons(?:/[^/]+)?)?$~D', $route)) {
        return in_array($method, ['GET', 'HEAD'], true);
    }
    if (preg_match('~^/wc/store/v1/cart/(?:add-item|update-item|remove-item|apply-coupon|remove-coupon)$~D', $route)) {
        return $method === 'POST';
    }
    if (in_array($method, ['GET', 'HEAD'], true)) {
        if ($route === '' || $route === '/wc/store/v1' || $route === '/wp/v2') { return true; }
        if (preg_match('~^/wc/store/v1/products(?:/[^/]+)*$~D', $route)) { return true; }
        if (preg_match('~^/wp/v2/(?:pages|posts|media|types|taxonomies|categories|tags|search)(?:/[^/]+)*$~D', $route)) { return true; }
    }
    return false;
}

add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
    if (!configuration_valid()) { return error(true); }
    // WooCommerce's native WP-CLI bridge reads this schema to register its commands.
    // CLI-only OPTIONS is metadata, never a tool invocation or an HTTP exception.
    if (defined('WP_CLI') && WP_CLI && $request->get_method() === 'OPTIONS' && $request->get_route() === '/wc/v2/system_status/tools') { return $result; }
    return rest_allowed($request->get_route(), $request->get_method(), $request->get_params()) ? $result : error();
}, -100, 3);
add_filter('rest_post_dispatch', static function ($response) {
    if ($response instanceof \WP_HTTP_Response) { $response->header('X-Robots-Tag', 'noindex, nofollow, noarchive'); }
    return $response;
}, PHP_INT_MAX);

add_action('plugins_loaded', static function () {
    // Disable receipt/processing, not the original legal implementation or its data.
    remove_action('template_redirect', ['CommerceCore\\Legal', 'request'], 1);
    // Remove non-cart form handlers as an additional barrier against mixed form payloads.
    foreach ([
        'wp_loaded' => ['checkout_action', 'process_login', 'process_registration', 'process_lost_password', 'process_reset_password', 'cancel_order'],
        'template_redirect' => ['redirect_reset_password_link', 'resend_set_password', 'save_address', 'save_account_details'],
        'wp' => ['pay_action', 'add_payment_method_action', 'delete_payment_method_action', 'set_default_payment_method_action'],
    ] as $hook => $methods) {
        foreach ($methods as $method) { remove_action($hook, ['WC_Form_Handler', $method], $hook === 'template_redirect' ? 10 : 20); }
    }
}, PHP_INT_MAX);

function message() { return '<section class="cdp-message" aria-label="Demostración"><p>' . esc_html(BLOCKED) . '</p></section>'; }
add_filter('pre_do_shortcode_tag', static function ($output, $tag) {
    return in_array($tag, ['woocommerce_my_account', 'commerce_legal_form', 'commerce_legal_identity'], true) ? message() : $output;
}, PHP_INT_MAX, 2);
add_filter('the_content', static function ($content) {
    if (function_exists('is_account_page') && is_account_page()) { return message(); }
    if (class_exists('CommerceCore\\Legal') && \CommerceCore\Legal::is_page()) { return message() . $content; }
    return $content;
}, 9);
add_filter('get_post_metadata', static function ($value, $post_id, $key, $single) {
    // Core hides historical legal fixtures outside local. Only D1-reviewed public
    // DEMO copy may be visible in staging, while every runtime guard is active.
    // Keep the original metadata in DB: disabling this module restores Core's rule.
    if ($key !== '_commerce_legal_demo' || wp_get_environment_type() !== 'staging' || !configuration_valid()) { return $value; }
    $legal = (array) get_option('commerce_core_legal', []);
    $pages = array_map('absint', array_values((array) ($legal['pages'] ?? [])));
    if (in_array((int) $post_id, $pages, true) && get_post_meta($post_id, '_commerce_public_demo_reviewed', true) === '1') { return $single ? '' : []; }
    return $value;
}, PHP_INT_MAX, 4);

add_action('template_redirect', static function () {
    if ((function_exists('is_checkout') && is_checkout()) || (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received')))) { deny(); }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && class_exists('CommerceCore\\Legal') && \CommerceCore\Legal::is_page()) { deny(); }
}, -100);

add_filter('wp_robots', static function () { return ['noindex' => true, 'nofollow' => true, 'noarchive' => true]; }, PHP_INT_MAX);
add_filter('wp_sitemaps_enabled', '__return_false', PHP_INT_MAX);
add_filter('robots_txt', static function () { return "User-agent: *\nDisallow:\n"; }, PHP_INT_MAX);
add_filter('woocommerce_structured_data_type_for_page', '__return_empty_array', PHP_INT_MAX);
add_filter('woocommerce_structured_data_product', '__return_empty_array', PHP_INT_MAX);
add_filter('woocommerce_structured_data_order', '__return_empty_array', PHP_INT_MAX);
// Both Checkout Blocks and classic cart consult this supported server-side switch.
// The demo has no delivery service and does not need visitors' address data.
add_filter('wc_shipping_enabled', '__return_false', PHP_INT_MAX);
add_filter('woocommerce_shipping_show_shipping_calculator', '__return_false', PHP_INT_MAX);
add_filter('woocommerce_shipping_calculator_enable_city', '__return_false', PHP_INT_MAX);
add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false', PHP_INT_MAX);
add_filter('render_block_core/paragraph', static function ($content) {
    return str_replace('Revisá tus productos y seguí con tu compra.', 'Revisá tu selección ilustrativa. Esta demo no permite finalizar compras.', $content);
}, PHP_INT_MAX);
add_filter('render_block_commerce-base/header', static function ($content) {
    return str_replace('<span>Compras a distancia · sin iniciar sesión</span>', '<span>Demostración · sin compras reales</span>', $content);
}, PHP_INT_MAX);
add_filter('render_block_commerce-base/footer', static function ($content) {
    if (class_exists('CommerceCore\\Legal')) {
        return str_replace(\CommerceCore\Legal::identity(), '<p class="cdp-footer-note">Sitio de demostración. No recibe compras, pagos ni solicitudes reales.</p>', $content);
    }
    return $content;
}, PHP_INT_MAX);
remove_action('wp_head', 'wp_generator');
// Native Unicode remains; do not replace it with images from WordPress's CDN.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
add_filter('emoji_svg_url', '__return_false', PHP_INT_MAX);

add_action('wp_head', static function () {
    echo '<style id="commerce-demo-public-style">.cdp-banner{position:fixed;inset:auto 0 0;z-index:80;box-sizing:border-box;padding:.7rem 1rem;background:var(--cb-surface,#f2eee7);color:var(--cb-text,#25231f);border-top:1px solid var(--cb-border,#c8c0b4);font:400 .8125rem/1.45 system-ui,sans-serif;text-align:center}.cdp-banner p{margin:0;max-width:75rem;margin-inline:auto}.wp-site-blocks{padding-bottom:5.5rem}.cdp-message{padding:1rem;border:1px solid currentColor;border-radius:.25rem;margin-block:1rem}.cdp-message p{margin:0}@media(max-width:480px){.cdp-banner{font-size:.75rem;padding:.6rem .8rem}.wp-site-blocks{padding-bottom:6rem}}@media(prefers-color-scheme:dark){.cdp-banner{background:var(--cb-surface,#24231f);color:var(--cb-text,#f2eee7)}}@media print{.cdp-banner{position:static}}</style>';
}, 100);
add_action('wp_body_open', static function () { echo '<aside class="cdp-banner" aria-label="Tienda de demostración"><p>' . esc_html(NOTICE) . '</p></aside>'; }, 1);
