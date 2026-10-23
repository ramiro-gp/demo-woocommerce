<?php
/** Plugin Name: H11 isolated mail capture (LAB ONLY; excluded from distribution) */
defined('ABSPATH') || exit;
add_filter('pre_wp_mail', static function ($return, $mail) {
    if (wp_get_environment_type() !== 'local') { return false; }
    foreach ((array) $mail['to'] as $to) {
        if (!preg_match('/@[^<>\s,]+\.test(?:>|$)/i', trim($to))) { return false; }
    }
    // No CC/BCC path can bypass the .test restriction in this lab.
    foreach ((array)$mail['headers'] as $header) {
        if (preg_match('/(?:^|\n)\s*(?:cc|bcc):/i', $header)) { return false; }
    }
    return $return;
}, 10, 2);
add_filter('wp_mail_from', static fn() => 'store@h11.test');
add_filter('woocommerce_email_headers', static function ($headers, $id) {
    return $headers . "\r\nX-H11-QA-Type: " . sanitize_key($id) . "\r\n";
}, 10, 2);
add_action('phpmailer_init', static function ($mailer) {
    $mailer->isSMTP();
    $mailer->Host = 'h11-mail';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPAutoTLS = false;
    $mailer->SMTPSecure = '';
});
