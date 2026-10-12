<?php
/**
 * Plugin Name: EmDash Exporter
 * Plugin URI: https://github.com/emdash-cms/wp-emdash
 * Description: Migrate your WordPress content to EmDash CMS with a guided wizard and one-click migration key
 * Version: 1.2.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: EmDash
 * License: GPL3
 * Text Domain: emdash-exporter
 */

defined('ABSPATH') || exit;

define('EMDASH_EXPORTER_VERSION', '1.2.0');
define('EMDASH_EXPORTER_PATH', plugin_dir_path(__FILE__));

require_once EMDASH_EXPORTER_PATH . 'includes/class-rest-controller.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-content-exporter.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-media-exporter.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-menu-exporter.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-comment-exporter.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-i18n-exporter.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-health-check.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-migration-key.php';
require_once EMDASH_EXPORTER_PATH . 'includes/class-admin-page.php';

/**
 * Register REST API routes
 */
function emdash_exporter_init() {
    $controller = new EmDash_Exporter_REST_Controller();
    $controller->register_routes();
}
add_action('rest_api_init', 'emdash_exporter_init');

// Migration wizard (Tools → EmDash Migration)
(new EmDash_Admin_Page())->register();

/**
 * Send the user into the wizard right after activation.
 */
function emdash_exporter_activate() {
    set_transient('emdash_exporter_activated', 1, 60);
}
register_activation_hook(__FILE__, 'emdash_exporter_activate');

/**
 * Revoke all migration keys on deactivation (uninstall.php covers deletion).
 */
function emdash_exporter_deactivate() {
    EmDash_Migration_Key::revoke_all();
}
register_deactivation_hook(__FILE__, 'emdash_exporter_deactivate');
