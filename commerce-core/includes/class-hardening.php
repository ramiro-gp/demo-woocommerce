<?php
/** Small supported controls, independent of the theme and payment gateways. */
namespace CommerceCore;
defined('ABSPATH') || exit;
final class Hardening {
    public static function register() {
        // A local DEMO must stay private even if its reading setting is changed accidentally.
        // Production uses the native reading setting and the chosen SEO provider.
        add_filter('pre_option_blog_public', static function($value) { return wp_get_environment_type()==='local' ? '0' : $value; });
        add_action('send_headers', static function() {
            if (wp_get_environment_type()==='local') { header('X-Robots-Tag: noindex, nofollow, noarchive'); }
        });
        // Remove the unused unauthenticated pingback surface, keep authenticated XML-RPC.
        add_filter('xmlrpc_methods', static function($methods) { unset($methods['pingback.ping'],$methods['pingback.extensions.getPingbacks']); return $methods; });
        add_filter('wp_headers', static function($headers) { unset($headers['X-Pingback']); return $headers; });
    }
}
