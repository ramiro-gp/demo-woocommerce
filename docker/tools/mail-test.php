<?php
$subject = 'H1 local mail verification';
if (!wp_mail('recipient@example.test', $subject, 'Captured locally by Mailpit.')) {
    WP_CLI::error('Mailpit capture failed.');
}
WP_CLI::success($subject);
