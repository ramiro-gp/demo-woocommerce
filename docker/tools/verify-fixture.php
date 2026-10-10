<?php
// Fixture for tests/verify-h1.ps1. It is never loaded by the web application.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') {
    exit(1);
}
$mode = $args[0] ?? '';
$directory = wp_upload_dir()['basedir'];
$proof = $directory . '/h1-proof.txt';
$extra = $directory . '/h1-after-backup.txt';
$assert = static function ($condition, $message) {
    if (!$condition) {
        WP_CLI::error($message);
    }
};
switch ($mode) {
    case 'fresh':
        $assert(get_locale() === 'es_AR', 'Initial language differs.');
        $assert(get_option('timezone_string') === 'America/Argentina/Buenos_Aires', 'Initial timezone differs.');
        $assert(get_option('blog_public') === '0', 'Search visibility differs.');
        $assert(get_option('active_plugins') === [], 'Unexpected active application plugin.');
        $user = get_user_by('login', getenv('LOCAL_ADMIN_USER'));
        $assert($user && wp_check_password(trim(file_get_contents('/run/secrets/wp_admin_password')), $user->user_pass, $user->ID), 'Private administrator password differs.');
        break;
    case 'seed':
        update_option('h1_verification', 'original');
        update_option('blogname', 'H1 persistence proof');
        update_option('timezone_string', 'UTC');
        wp_mkdir_p($directory);
        file_put_contents($proof, 'H1 original upload');
        break;
    case 'changed':
        update_option('h1_verification', 'changed');
        file_put_contents($proof, 'H1 changed upload');
        file_put_contents($extra, 'Created after backup');
        break;
    case 'original':
        $assert(get_option('h1_verification') === 'original', 'Database proof was not preserved/restored.');
        $assert(get_option('blogname') === 'H1 persistence proof', 'Title was overwritten.');
        $assert(get_option('timezone_string') === 'UTC', 'Existing preferences were overwritten.');
        $assert(file_get_contents($proof) === 'H1 original upload', 'Upload was not preserved/restored.');
        $assert(!file_exists($extra), 'Restore retained a file created after the backup.');
        break;
    case 'reset-db':
        $assert(get_option('h1_verification') === false, 'Database reset retained the option.');
        $assert(file_get_contents($proof) === 'H1 original upload', 'Database reset removed uploads.');
        break;
    case 'reset-all':
        $assert(get_option('h1_verification') === false, 'Full reset retained the option.');
        $assert(!file_exists($proof), 'Full reset retained uploads.');
        break;
    default:
        WP_CLI::error('Unknown fixture mode.');
}
WP_CLI::success('H1 fixture: ' . $mode);
