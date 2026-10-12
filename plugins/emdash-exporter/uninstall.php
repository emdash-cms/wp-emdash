<?php
/**
 * Revoke all migration keys when the plugin is deleted.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/includes/class-migration-key.php';

EmDash_Migration_Key::revoke_all();
