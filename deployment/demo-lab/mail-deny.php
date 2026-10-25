<?php
/** Plugin Name: D1 laboratory transport denial (LAB ONLY) */
defined('ABSPATH') || exit;
// Independent of DEMO mode: reversal tests must never enable an email transport.
add_filter('pre_wp_mail', '__return_false', PHP_INT_MAX);
