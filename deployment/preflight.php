<?php
/** Read-only hosting compatibility check: wp eval-file preflight.php */
defined('ABSPATH') || exit;
global $wpdb;
$extensions = [];
foreach (['curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'mysqli', 'openssl', 'zip'] as $extension) {
    $extensions[$extension] = extension_loaded($extension);
}
$memory = wp_convert_hr_to_bytes(ini_get('memory_limit'));
$db = $wpdb->db_version();
$checks = [
    'php84_baseline' => PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80500,
    'wordpress_baseline' => version_compare(get_bloginfo('version'), '7.1.3', '>='),
    'memory256' => $memory === -1 || $memory >= 256 * MB_IN_BYTES,
    'db_supported' => version_compare($db, stripos($wpdb->db_server_info(), 'MariaDB') !== false ? '10.6' : '8.0', '>='),
    'https' => is_ssl() || str_starts_with(home_url(), 'https://'),
    'debug_hidden' => !ini_get('display_errors') && !WP_DEBUG_DISPLAY,
];
echo wp_json_encode(['php'=>PHP_VERSION,'wordpress'=>get_bloginfo('version'),'woocommerce'=>defined('WC_VERSION')?WC_VERSION:null,'db'=>$db,'memory_limit'=>ini_get('memory_limit'),'extensions'=>$extensions,'checks'=>$checks,'note'=>'Read-only; SSH/cron/mail/backups/isolation and host limits need separate validation. Local HTTP is expected to fail HTTPS.'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";
