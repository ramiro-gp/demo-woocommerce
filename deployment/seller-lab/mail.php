<?php
/** Plugin Name: D1.1 seller laboratory safety (not a commercial plugin) */
defined('ABSPATH') || exit;
// All mail is captured by this project's Mailpit; non-reserved recipients fail.
add_filter('pre_wp_mail', static function($return,$mail) {
    if (wp_get_environment_type()!=='local') { return false; }
    foreach ((array)$mail['to'] as $list) {
        if (!is_string($list)) { return false; }
        foreach (explode(',',$list) as $to) {
            $address=trim($to);
            if (preg_match('/^[^<>\r\n]*<([^<>\r\n]+)>$/D',$address,$match)) { $address=trim($match[1]); }
            if (!is_email($address) || !preg_match('/\.test$/iD',$address)) { return false; }
        }
    }
    foreach ((array)$mail['headers'] as $header) {
        if (preg_match('/(?:^|\n)\s*(?:cc|bcc):/i',$header)) { return false; }
    }
    return $return;
},PHP_INT_MAX,2);
add_action('phpmailer_init',static function($mailer) {
    $mailer->isSMTP();$mailer->Host='mail';$mailer->Port=1025;
    $mailer->SMTPAuth=false;$mailer->SMTPAutoTLS=false;$mailer->SMTPSecure='';
});
// No external server calls, gateways, telemetry or downloads in the audit runtime.
add_filter('pre_http_request',static function() {
    return new WP_Error('seller_lab_offline','External HTTP is disabled in the isolated seller lab.');
},PHP_INT_MAX);
add_filter('wp_mail_from',static fn()=>'store@seller.example.test');
add_filter('woocommerce_email_headers',static function($headers,$id) {
    return $headers."\r\nX-Seller-QA-Type: ".sanitize_key($id)."\r\n";
},10,2);
