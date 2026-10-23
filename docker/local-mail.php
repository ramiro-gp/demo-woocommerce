<?php
/**
 * Plugin Name: Local Mail Capture
 * Description: Development tooling: routes local WordPress mail to Mailpit.
 */

defined('ABSPATH') || exit;

// WordPress derives wordpress@localhost by default; PHPMailer rejects it.
add_filter('wp_mail_from', static function ($address) {
    if (wp_get_environment_type() !== 'local') { return $address; }
    $configured = get_option('woocommerce_email_from_address', '');
    // Keep the merchant's native DEMO sender while transport stays isolated.
    return is_email($configured) && str_ends_with(strtolower($configured), '.test') ? $configured : 'wordpress@example.test';
});

add_filter('wp_mail_from_name', static function ($name) {
    if (wp_get_environment_type() !== 'local' || $name !== 'WordPress') { return $name; }
    return get_option('woocommerce_email_from_name') ?: get_bloginfo('name');
});

add_action('phpmailer_init', static function ($mailer) {
    if (wp_get_environment_type() !== 'local') {
        return;
    }

    $mailer->isSMTP();
    $mailer->Host = 'mailpit';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPAutoTLS = false;
    $mailer->SMTPSecure = '';
    $mailer->Username = '';
    $mailer->Password = '';
});
