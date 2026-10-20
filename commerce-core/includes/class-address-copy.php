<?php
/** Explicit, read-only preparation of the native shipping address form. */
namespace CommerceCore;

defined('ABSPATH') || exit;

final class Address_Copy {
    private const FIELDS = ['first_name', 'last_name', 'company', 'country', 'state', 'city', 'postcode', 'address_1', 'address_2', 'phone'];

    public static function register() {
        add_action('woocommerce_before_edit_address_form_shipping', [self::class, 'render']);
    }

    /** No user ID parameter: the current authenticated customer is the only source. */
    public static function source() {
        if (!is_user_logged_in() || !function_exists('WC') || !WC()->countries) { return []; }
        $customer = new \WC_Customer(get_current_user_id());
        $country = $customer->get_billing_country();
        if (!$country || !isset(WC()->countries->get_shipping_countries()[$country])) { return []; }

        $billing = WC()->countries->get_address_fields($country, 'billing_');
        $shipping = WC()->countries->get_address_fields($country, 'shipping_');
        $values = [];
        foreach (self::FIELDS as $field) {
            // Do not invent mappings for custom fields or copy billing_email.
            if (!isset($billing['billing_' . $field], $shipping['shipping_' . $field])) { continue; }
            $getter = 'get_billing_' . $field;
            if (is_callable([$customer, $getter])) {
                $values[$field] = wc_clean((string) $customer->$getter());
            }
        }
        // Required destination fields must be available. Native save still owns
        // all validation (including additional fields supplied by extensions).
        foreach ($shipping as $key => $field) {
            $name = substr($key, strlen('shipping_'));
            if (!in_array($name, self::FIELDS, true)) { continue; }
            if (!empty($field['required']) && true !== ($field['hidden'] ?? false)
                && '' === trim($values[$name] ?? '')) { return []; }
        }
        $states = WC()->countries->get_states($country);
        if (is_array($states) && $states && !isset($states[$values['state'] ?? ''])) { return []; }
        if (!empty($values['postcode']) && !\WC_Validation::is_postcode($values['postcode'], $country)) { return []; }
        if (!empty($values['phone']) && !\WC_Validation::is_phone($values['phone'], $country)) { return []; }
        return $values;
    }

    public static function render() {
        if (!is_user_logged_in()) { return; }
        $values = self::source();
        ?>
        <section class="commerce-core-address-copy" aria-label="<?php esc_attr_e('Copiar dirección de facturación', 'commerce-core'); ?>">
            <?php if (!$values) : ?>
                <p><?php esc_html_e('Para usar tu dirección de facturación, primero completala con datos válidos para un destino de envío de esta tienda.', 'commerce-core'); ?></p>
                <a href="<?php echo esc_url(wc_get_endpoint_url('edit-address', 'billing', wc_get_page_permalink('myaccount'))); ?>"><?php esc_html_e('Revisar mi dirección de facturación', 'commerce-core'); ?></a>
            <?php else :
                wp_enqueue_script('commerce-core-address-copy', plugins_url('assets/js/address-copy.js', dirname(__DIR__) . '/commerce-core.php'), ['jquery', 'wc-country-select', 'wc-address-i18n'], VERSION, true);
                ?>
                <p id="commerce-core-copy-help"><?php esc_html_e('Copiá los datos guardados de facturación y revisalos antes de guardar. Los cambios futuros en facturación no modificarán tu dirección de envío.', 'commerce-core'); ?></p>
                <button type="button" class="button commerce-core-copy-start" aria-describedby="commerce-core-copy-help" aria-expanded="false" aria-controls="commerce-core-copy-confirm" hidden><?php esc_html_e('Usar mi dirección de facturación', 'commerce-core'); ?></button>
                <div id="commerce-core-copy-confirm" role="group" aria-label="<?php esc_attr_e('Confirmar reemplazo de campos', 'commerce-core'); ?>" hidden>
                    <p><?php esc_html_e('Ya hay datos de envío en el formulario. Al copiar se reemplazarán los campos correspondientes. Nada se guarda hasta pulsar «Guardar dirección».', 'commerce-core'); ?></p>
                    <button type="button" class="button commerce-core-copy-apply"><?php esc_html_e('Copiar y reemplazar los campos', 'commerce-core'); ?></button>
                    <button type="button" class="button commerce-core-copy-cancel"><?php esc_html_e('Cancelar', 'commerce-core'); ?></button>
                </div>
                <p class="commerce-core-copy-status" role="status" aria-live="polite" aria-atomic="true" data-success="<?php esc_attr_e('Datos copiados al formulario. Revisalos y pulsá «Guardar dirección» para confirmar.', 'commerce-core'); ?>" data-error="<?php esc_attr_e('No se pudieron preparar todos los campos. Revisá la dirección de envío antes de guardar.', 'commerce-core'); ?>"></p>
                <script type="application/json" class="commerce-core-copy-source"><?php echo wp_json_encode($values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
                <noscript><p><?php esc_html_e('Para copiar los datos automáticamente necesitás JavaScript. También podés completar y guardar este formulario manualmente.', 'commerce-core'); ?></p></noscript>
            <?php endif; ?>
        </section>
        <?php
    }
}
