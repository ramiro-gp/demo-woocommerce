<?php
/** Accurate receipt copy, using only the order already authorized by WooCommerce. */
if (!defined('ABSPATH')) { exit; }

function commerce_base_order_received_text($message, $order) {
    if (!$order instanceof WC_Order) { return $message; }
    if ($order->has_status(['pending', 'on-hold'])) {
        return $order->get_payment_method() === 'bacs'
            ? __('Registramos tu pedido. El pago por transferencia está pendiente de verificación.', 'commerce-base')
            : __('Registramos tu pedido. El pago está pendiente de confirmación.', 'commerce-base');
    }
    if ($order->has_status('processing') && $order->is_paid() && $order->get_date_paid()) {
        return __('Recibimos tu pedido y confirmamos el pago.', 'commerce-base');
    }
    // Keep native failure, cancellation, refund and fulfillment messages.
    return $message;
}
add_filter('woocommerce_thankyou_order_received_text', 'commerce_base_order_received_text', 20, 2);
