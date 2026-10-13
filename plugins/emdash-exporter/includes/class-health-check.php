<?php
/**
 * Health Check
 *
 * Preflight checks for the migration wizard. Each check returns a specific,
 * actionable message so users are never stuck with a silent failure.
 */

defined('ABSPATH') || exit;

class EmDash_Health_Check {

    /**
     * Run all checks.
     *
     * @return array[] Each: { id, label, status: 'pass'|'warn'|'fail', message, fix? }
     */
    public function run() {
        return array_values(array_filter([
            $this->check_permalinks(),
            $this->check_application_passwords(),
            $this->check_rest_loopback(),
            $this->check_authorization_header(),
            $this->check_reachability(),
            $this->check_bot_protection(),
        ]));
    }

    /** True when every check passes (warnings are acceptable). */
    public static function all_ok(array $checks) {
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }
        }
        return true;
    }

    /** True when at least one check returned a warning. */
    public static function has_warnings(array $checks) {
        foreach ($checks as $check) {
            if ($check['status'] === 'warn') {
                return true;
            }
        }
        return false;
    }

    private function check_permalinks() {
        $structure = get_option('permalink_structure');
        if ($structure) {
            return [
                'id' => 'permalinks',
                'label' => __('Pretty permalinks', 'emdash-exporter'),
                'status' => 'pass',
                'message' => __('Pretty permalinks are enabled, the REST API is available at /wp-json/.', 'emdash-exporter'),
            ];
        }
        return [
            'id' => 'permalinks',
            'label' => __('Pretty permalinks', 'emdash-exporter'),
            'status' => 'warn',
            'message' => __('Plain permalinks are active. The REST API is only reachable via ?rest_route=, which some importers do not try.', 'emdash-exporter'),
            'fix' => sprintf(
                /* translators: %s: URL of the permalinks settings screen */
                __('Go to <a href="%s">Settings → Permalinks</a> and choose any structure other than "Plain".', 'emdash-exporter'),
                esc_url(admin_url('options-permalink.php'))
            ),
        ];
    }

    private function check_application_passwords() {
        if (wp_is_application_passwords_available()) {
            return [
                'id' => 'app_passwords',
                'label' => __('Application Passwords', 'emdash-exporter'),
                'status' => 'pass',
                'message' => __('Application Passwords are available for API authentication.', 'emdash-exporter'),
            ];
        }
        return [
            'id' => 'app_passwords',
            'label' => __('Application Passwords', 'emdash-exporter'),
            'status' => 'fail',
            'message' => __('Application Passwords are disabled on this site. EmDash cannot authenticate without them.', 'emdash-exporter'),
            'fix' => is_ssl()
                ? __('A security plugin or a filter (wp_is_application_passwords_available) is disabling them. Re-enable Application Passwords in your security plugin settings.', 'emdash-exporter')
                : __('WordPress disables Application Passwords on non-HTTPS sites. Enable HTTPS for this site, then reload this page.', 'emdash-exporter'),
        ];
    }

    private function check_rest_loopback() {
        $response = wp_remote_get(rest_url('emdash/v1/probe'), [
            'timeout' => 10,
            'sslverify' => false, // ponytail: loopback to self; local certs are often self-signed
        ]);

        if (is_wp_error($response)) {
            return [
                'id' => 'rest',
                'label' => __('REST API reachable', 'emdash-exporter'),
                'status' => 'fail',
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('This site cannot reach its own REST API: %s', 'emdash-exporter'),
                    esc_html($response->get_error_message())
                ),
                'fix' => __('Loopback requests are blocked. Check with your host, or look for security plugins blocking the REST API.', 'emdash-exporter'),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [
                'id' => 'rest',
                'label' => __('REST API reachable', 'emdash-exporter'),
                'status' => 'fail',
                'message' => sprintf(
                    /* translators: %d: HTTP status code */
                    __('The EmDash Exporter REST endpoint returned HTTP %d instead of 200.', 'emdash-exporter'),
                    (int) $code
                ),
                'fix' => __('A security plugin or firewall rule is likely blocking /wp-json/. Allow the emdash/v1 namespace.', 'emdash-exporter'),
            ];
        }

        return [
            'id' => 'rest',
            'label' => __('REST API reachable', 'emdash-exporter'),
            'status' => 'pass',
            'message' => __('The EmDash Exporter REST API responds correctly.', 'emdash-exporter'),
        ];
    }

    /**
     * The classic Application Password failure: Apache/CGI setups strip the
     * Authorization header before PHP sees it. We loop back to our own
     * header-check endpoint with a dummy header and see if it arrives.
     */
    private function check_authorization_header() {
        $response = wp_remote_get(rest_url('emdash/v1/header-check'), [
            'timeout' => 10,
            'sslverify' => false,
            // Custom scheme on purpose: a Basic header with fake credentials
            // would trigger Application Password validation (rejecting the
            // whole REST request if an 'emdash' user exists) and can count as
            // a failed login for brute-force protection plugins. A Bearer
            // token could collide with JWT/OAuth plugins the same way. We
            // only test whether the header string survives the server config.
            'headers' => ['Authorization' => 'EmDashCheck header-check'],
        ]);

        $label = __('Authorization header', 'emdash-exporter');

        if (is_wp_error($response)) {
            return [
                'id' => 'auth_header',
                'label' => $label,
                'status' => 'warn',
                'message' => __('Could not verify (loopback request failed). Authentication may still work from outside.', 'emdash-exporter'),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($body) && !empty($body['authorization_header_received'])) {
            return [
                'id' => 'auth_header',
                'label' => $label,
                'status' => 'pass',
                'message' => __('The Authorization header reaches WordPress. Application Password logins will work.', 'emdash-exporter'),
            ];
        }

        return [
            'id' => 'auth_header',
            'label' => $label,
            'status' => 'fail',
            'message' => __('Your server strips the Authorization header before it reaches WordPress, so Application Password logins fail.', 'emdash-exporter'),
            'fix' => __('Add this line to your .htaccess, directly after "RewriteEngine On":<br><code>RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]</code>', 'emdash-exporter'),
        ];
    }

    private function check_reachability() {
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        $is_local = $host === 'localhost'
            || substr($host, -6) === '.local'
            || substr($host, -5) === '.test'
            || (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));

        if ($is_local) {
            return [
                'id' => 'reachability',
                'label' => __('Publicly reachable', 'emdash-exporter'),
                'status' => 'warn',
                'message' => sprintf(
                    /* translators: %s: host name */
                    __('This site (%s) is only reachable on your local machine. A cloud-hosted EmDash site cannot connect to it.', 'emdash-exporter'),
                    esc_html($host)
                ),
                'fix' => __('Run EmDash locally on the same machine (npx emdash dev), or use a tunnel (e.g. cloudflared) to expose this site temporarily.', 'emdash-exporter'),
            ];
        }

        return [
            'id' => 'reachability',
            'label' => __('Publicly reachable', 'emdash-exporter'),
            'status' => 'pass',
            'message' => __('The site URL looks publicly reachable.', 'emdash-exporter'),
        ];
    }

    /**
     * Cloudflare adds CF-Ray to every request it proxies. Its bot protection
     * answers server-side clients like EmDash with a challenge page, and
     * whether it is on can't be seen from here, so this only warns.
     */
    private function check_bot_protection() {
        if (empty($_SERVER['HTTP_CF_RAY'])) {
            return null;
        }

        return [
            'id' => 'bot_protection',
            'label' => __('Cloudflare bot protection', 'emdash-exporter'),
            'status' => 'warn',
            'message' => __('This site is served through Cloudflare. If bot protection is on (Bot Fight Mode, Super Bot Fight Mode, or a challenge rule), Cloudflare answers EmDash with a challenge page and the import cannot connect.', 'emdash-exporter'),
            'fix' => __('For the duration of the migration, add a WAF custom rule that skips bot protection for /wp-json/emdash/v1/. Bot Fight Mode on the Free plan cannot be skipped: turn it off and turn it back on afterwards.', 'emdash-exporter'),
        ];
    }
}
