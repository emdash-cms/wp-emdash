<?php
/**
 * Migration Key
 *
 * Packages everything EmDash needs to connect (site URL, username,
 * application password) into a single copy-paste string, so users never
 * type URLs or credentials manually.
 *
 * Format: "em1." + base64url(JSON { v, url, user, pass })
 */

defined('ABSPATH') || exit;

class EmDash_Migration_Key {

    const PREFIX = 'em1.';
    const APP_NAME = 'EmDash Migration';

    /**
     * Create (or re-create) the application password and return the key.
     *
     * @return array|WP_Error { key: string, user: string }
     */
    public static function generate() {
        if (!wp_is_application_passwords_available()) {
            return new WP_Error(
                'app_passwords_unavailable',
                __('Application Passwords are not available on this site. See the site check above for how to enable them.', 'emdash-exporter')
            );
        }

        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return new WP_Error('not_logged_in', __('You must be logged in.', 'emdash-exporter'));
        }

        if (!wp_is_application_passwords_available_for_user($user)) {
            return new WP_Error(
                'app_passwords_unavailable',
                __('Application Passwords are not available for your user account.', 'emdash-exporter')
            );
        }

        // Revoke a previous migration password so regenerating the key
        // invalidates the old one instead of piling up credentials.
        self::revoke_for_user($user->ID);

        $created = WP_Application_Passwords::create_new_application_password($user->ID, [
            'name' => self::APP_NAME,
        ]);

        if (is_wp_error($created)) {
            return $created;
        }

        list($password) = $created;

        $payload = wp_json_encode([
            'v' => 1,
            'url' => get_site_url(),
            'user' => $user->user_login,
            'pass' => $password,
        ]);

        return [
            'key' => self::PREFIX . self::base64url_encode($payload),
            'user' => $user->user_login,
        ];
    }

    /**
     * Revoke the migration application password for the current user.
     *
     * @return bool Whether a password was revoked.
     */
    public static function revoke() {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return false;
        }
        return self::revoke_for_user($user->ID);
    }

    /**
     * Revoke the migration application passwords of every user, so no key
     * keeps working once the plugin is deactivated or deleted.
     */
    public static function revoke_all() {
        if (!class_exists('WP_Application_Passwords')) {
            return;
        }
        $user_ids = get_users([
            'meta_key' => WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS,
            'fields' => 'ID',
            'blog_id' => 0,
        ]);
        foreach ($user_ids as $user_id) {
            self::revoke_for_user((int) $user_id);
        }
    }

    private static function revoke_for_user($user_id) {
        $revoked = false;
        foreach (WP_Application_Passwords::get_user_application_passwords($user_id) as $item) {
            if ($item['name'] === self::APP_NAME) {
                WP_Application_Passwords::delete_application_password($user_id, $item['uuid']);
                $revoked = true;
            }
        }
        return $revoked;
    }

    /**
     * Whether a migration password currently exists for the current user.
     */
    public static function exists() {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return false;
        }
        foreach (WP_Application_Passwords::get_user_application_passwords($user->ID) as $item) {
            if ($item['name'] === self::APP_NAME) {
                return true;
            }
        }
        return false;
    }

    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
