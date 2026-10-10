<?php
/**
 * Plugin Name: Local Mail Capture
 * Description: Development tooling: routes local WordPress mail to Mailpit.
 */

defined('ABSPATH') || exit;

// WordPress derives wordpress@localhost by default; PHPMailer rejects it.
add_filter('wp_mail_from', static function ($address) {
    return wp_get_environment_type() === 'local' ? 'wordpress@example.test' : $address;
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
