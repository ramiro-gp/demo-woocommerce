<?php
/**
 * Plugin Name: Commerce Communications
 * Description: Email presentation and clear order copy over native WooCommerce communications.
 * Version: 1.0.1
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: commerce-communications
 */

defined('ABSPATH') || exit;

// No email triggers, order writes, stock hooks, transport or recipient changes.
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// A text-first receipt stays readable with images blocked and avoids SVG
// placeholders unsupported by several mail clients. Header logos remain native.
add_filter('woocommerce_email_order_items_args', static function ($args) {
    $args['show_image'] = false;
    return $args;
});

add_filter('woocommerce_email_styles', static function ($css, $email) {
    if (!$email instanceof WC_Email) { return $css; }
    // WooCommerce inlines these rules. Colors remain native, editable options.
    return $css . "\n" . '
        #wrapper { width:100%; max-width:600px; }
        #inner_wrapper, #template_container, #template_header { border-radius:0 !important; }
        #template_header_image .email-logo-text { font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; letter-spacing:3px; }
        #template_header h1 { font-family:Georgia,"Times New Roman",serif; font-size:34px; line-height:1.2; font-weight:400; letter-spacing:-0.5px; }
        #body_content_inner { font-size:16px; line-height:1.6; }
        #body_content_inner p { margin:0 0 18px; }
        #body_content_inner h2 { font-family:Arial,Helvetica,sans-serif; font-size:18px; font-weight:700; }
        #body_content_inner a, #template_footer a { text-decoration:underline; }
        .email-order-item-meta { font-size:14px; line-height:1.5; word-wrap:break-word; overflow-wrap:anywhere; }
        #template_footer #credit { font-size:13px; line-height:1.6; }
        @media screen and (max-width:600px) {
            #body_content_inner { font-size:16px !important; line-height:1.6 !important; }
            #template_header h1 { font-size:28px !important; }
            #header_wrapper { padding:24px 20px 0 !important; }
            #template_header_image { padding:28px 20px 0 !important; }
            #body_content_inner_cell { padding:20px !important; }
            .email-order-item-meta { font-size:14px !important; }
            #body_content .email-order-details th { font-size:14px !important; padding:8px 2px !important; }
            #body_content .email-order-details td { padding:10px 2px !important; }
            #body_content .email-order-details .order-item-data td { padding:0 !important; }
            #addresses td { display:block !important; width:100% !important; padding:0 0 24px !important; }
            #addresses .address { word-break:normal !important; word-wrap:break-word !important; overflow-wrap:anywhere !important; }
        }
    ';
}, 20, 2);

// Exact native email phrases, shared by HTML and plain text; no copied template.
// Preserve WooCommerce translations for other locales and all other strings.
add_filter('gettext_woocommerce', static function ($translated, $source) {
    if (!in_array(get_locale(), ['es_AR', 'es_ES'], true)) { return $translated; }
    $copy = [
        'We’ve received your order and it’s currently on hold until we can confirm your payment has been processed.' => 'Recibimos tu pedido. El pago queda pendiente de confirmación. Si elegiste transferencia, verificaremos su acreditación antes de preparar el pedido.',
        'Thanks for your order. It’s on-hold until we confirm that payment has been received.' => 'Recibimos tu pedido. El pago queda pendiente de confirmación. Si elegiste transferencia, verificaremos su acreditación antes de preparar el pedido.',
        'Here’s a reminder of what you’ve ordered:' => 'Este es el resumen de tu pedido:',
        'Just to let you know &mdash; we’ve received your order, and it is now being processed.' => 'Tu pedido está confirmado y pendiente de preparación. Te avisaremos cuando finalicemos su gestión.',
        'Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:' => 'Tu pedido #%s está confirmado y pendiente de preparación.',
        'We have finished processing your order.' => 'Finalizamos la gestión de tu pedido. Gracias por elegirnos.',
        'Your order from %s has been partially refunded.' => 'Registramos un reembolso parcial para tu pedido de %s. El registro no confirma por sí solo la acreditación de dinero en tu cuenta.',
        'Your order from %s has been refunded.' => 'Registramos un reembolso total para tu pedido de %s. El registro no confirma por sí solo la acreditación de dinero en tu cuenta.',
        'Your order on %s has been partially refunded. There are more details below for your reference:' => 'Registramos un reembolso parcial para tu pedido de %s. El registro no confirma por sí solo la acreditación de dinero en tu cuenta. Revisá el detalle:',
        'Your order on %s has been refunded. There are more details below for your reference:' => 'Registramos un reembolso total para tu pedido de %s. El registro no confirma por sí solo la acreditación de dinero en tu cuenta. Revisá el detalle:',
        'We’re getting in touch to let you know that your order #%1$s has been cancelled.' => 'Tu pedido #%1$s fue cancelado. La cancelación del pedido no devuelve dinero automáticamente. Si ya pagaste, comunicate con la tienda para coordinar la devolución.',
        'We’re sorry to let you know that your order #%1$s has been cancelled.' => 'Tu pedido #%1$s fue cancelado. La cancelación del pedido no devuelve dinero automáticamente. Si ya pagaste, comunicate con la tienda para coordinar la devolución.',
        "Unfortunately, we couldn't complete your order due to an issue with your payment method." => 'No pudimos confirmar el pago de tu pedido. Revisá el detalle o comunicate con la tienda antes de volver a pagar.',
        "If you'd like to continue with your purchase, please return to %s and try a different method of payment." => 'Para continuar tu compra en %s, revisá el estado de tu pedido con la tienda.',
    ];
    $copy = apply_filters('commerce_communications_email_copy', $copy);
    return $copy[$source] ?? $translated;
}, 20, 2);

// Native view-order has already checked access; repeat its capability check.
// Use the same BACS renderer/settings as receipt and email, including bank data.
add_action('woocommerce_view_order', static function ($order_id) {
    if (!current_user_can('view_order', $order_id)) { return; }
    $order = wc_get_order($order_id);
    if (!$order || !$order->has_status('on-hold') || 'bacs' !== $order->get_payment_method()) { return; }
    $gateway = WC()->payment_gateways()->payment_gateways()['bacs'] ?? null;
    if ($gateway instanceof WC_Gateway_BACS) { $gateway->thankyou_page($order_id); }
}, 5);
