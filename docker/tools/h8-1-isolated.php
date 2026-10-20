<?php
/** Per-process isolation only. Never switches the installation's theme/plugins. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
WP_CLI::add_wp_hook('setup_theme', static function () {
    add_filter('pre_option_template', static fn() => 'twentytwentyfive');
    add_filter('pre_option_stylesheet', static fn() => 'twentytwentyfive');
});
if (getenv('COMMERCE_QA_WITHOUT_WC')) {
    WP_CLI::add_wp_hook('option_active_plugins', static function ($plugins) {
        return array_values(array_filter($plugins, static fn($plugin) => 'woocommerce/woocommerce.php' !== $plugin));
    });
}
if (getenv('COMMERCE_QA_WITHOUT_COPY')) {
    WP_CLI::add_wp_hook('commerce_core_address_copy_enabled', '__return_false');
}
