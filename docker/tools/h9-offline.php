<?php
/** Install only in the disposable clone's mu-plugins directory. */
defined('ABSPATH') || exit;
if (wp_get_environment_type() !== 'local' || untrailingslashit(home_url()) !== 'http://localhost:8199') { return; }
defined('DISABLE_WP_CRON') || define('DISABLE_WP_CRON', true);
add_filter('pre_http_request', static function () { return new WP_Error('h9_offline', 'External HTTP disabled in disposable clone'); }, PHP_INT_MAX);
