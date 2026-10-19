<?php
/**
 * Plugin Name: Commerce Order Integrity
 * Description: Isolates a pending Checkout Pro order from later cart/form interactions in Checkout Blocks.
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class Commerce_Order_Integrity {
    private const PRO = 'woo-mercado-pago-basic';
    private static $request = null;

    public static function register() {
        add_filter('rest_request_before_callbacks', [self::class, 'before_request'], 10, 3);
        add_filter('rest_request_after_callbacks', [self::class, 'after_request'], 10, 3);
        add_filter('woocommerce_hydration_dispatch_request', [self::class, 'before_hydration'], 10, 4);
        add_filter('woocommerce_hydration_request_after_callbacks', [self::class, 'after_request'], 10, 3);
        // Store API chooses its cookie/Cart-Token session before this hook fires.
        add_action('woocommerce_load_cart_from_session', [self::class, 'detach_pending_order']);
    }

    public static function before_request($response, $handler, $request) {
        if (null !== $response || !preg_match('#^/wc/store/v[0-9]+/(?:checkout/?|cart(?:/.*)?)$#D', $request->get_route())) {
            return $response;
        }
        // Do not mutate even a session reference on an unauthorized request.
        $valid_nonce = wp_verify_nonce($request->get_header('Nonce'), 'wc_store_api');
        $tokens = 'Automattic\\WooCommerce\\StoreApi\\Utilities\\CartTokenUtils';
        $valid_token = class_exists($tokens) && $tokens::validate_cart_token($tokens::get_request_cart_token());
        if (!$valid_nonce && !$valid_token) { return $response; }
        self::$request = $request;
        // Handles preloaded carts as well as normal lazy Store API loading.
        self::detach_pending_order();
        return $response;
    }

    public static function after_request($response, $handler, $request) {
        if (self::$request === $request) { self::$request = null; }
        return $response;
    }

    public static function before_hydration($response, $request, $path, $handler) {
        // WC 11.2 hydration calls the controller directly, outside REST dispatch,
        // with its own scoped nonce bypass. This supported hook runs before that
        // internal GET; it does not disable nonce checks on external requests.
        if (null === $response && 'GET' === $request->get_method()
            && preg_match('#^/wc/store/v[0-9]+/(?:checkout/?|cart(?:/.*)?)$#D', $request->get_route())) {
            self::$request = $request;
            self::detach_pending_order();
        }
        return $response;
    }

    public static function detach_pending_order() {
        if (!self::$request || !function_exists('WC') || !WC()->session) { return; }
        $session = WC()->session;
        $id = absint($session->get('store_api_draft_order', 0));
        $order = $id ? wc_get_order($id) : false;
        if (!$order instanceof WC_Order || !$order->has_status('pending') || 'store-api' !== $order->get_created_via() || self::PRO !== $order->get_payment_method()) { return; }

        // Keep the native same-gateway POST retry (including a lost response).
        // An explicit pay-existing-order request uses /checkout/{id}, or order-pay,
        // and never enters this plugin. Its native key/email/owner checks remain.
        if (self::is_same_payment_retry($order)) { return; }

        // A form/cart read or edit starts a fresh purchase context. Only detach
        // session pointers; never save/cancel the order, alter metadata or stock,
        // release its reservation, clear the cart, or intercept notifications.
        $session->set('store_api_draft_order', null);
        if ($id === absint($session->get('order_awaiting_payment', 0))) {
            $session->set('order_awaiting_payment', null);
        }
    }

    private static function is_same_payment_retry($order) {
        $request = self::$request;
        if ('POST' !== $request->get_method() || !preg_match('#/checkout/?$#D', $request->get_route()) || self::PRO !== $request->get_param('payment_method')) { return false; }
        // A stale second tab posting different buyer details is a new purchase,
        // not a retry of the original order. Explicit order-pay remains native.
        foreach (['billing', 'shipping'] as $group) {
            foreach ((array) $request->get_param($group . '_address') as $field => $value) {
                $getter = 'get_' . $group . '_' . $field;
                if (!is_callable([$order, $getter]) || (string) $order->$getter() !== (string) $value) { return false; }
            }
        }
        $note = $request->get_param('customer_note');
        return wc_sanitize_textarea($note ?? '') === $order->get_customer_note();
    }
}

add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
Commerce_Order_Integrity::register();
