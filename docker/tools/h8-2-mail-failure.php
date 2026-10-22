<?php
/** Ephemeral QA MU plugin. Copy only for local HTTP failure test and remove in finally. */
defined('ABSPATH') || exit;
if(wp_get_environment_type()==='local' && getenv('LOCAL_SITE_URL')==='http://localhost:8080' && ($_SERVER['HTTP_X_H82_QA_MAIL_FAILURE'] ?? '')==='local-synthetic-only') {
    add_filter('pre_wp_mail',static function($result,$mail) {
        $recipients=is_array($mail['to']) ? $mail['to'] : [$mail['to']];
        foreach($recipients as $to) { if(!str_ends_with($to,'@example.test')) { return $result; } }
        return false;
    },PHP_INT_MAX,2);
}
