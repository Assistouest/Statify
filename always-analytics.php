<?php
/**
 * Plugin Name:       Always Analytics
 * Plugin URI:        https://example.com/always-analytics
 * Description:       Statistiques avancées auto-hébergées, légères et respectueuses de la vie privée pour WordPress.
 * Version:           2.3.0
 * Author:            Adrien
 * Author URI:        https://assistouest.fr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       always-analytics
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AA_VERSION', '2.3.0');
define('AA_PLUGIN_FILE', __FILE__);
define('AA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Always_Analytics\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $parts = explode('\\', $relative_class);
    $class_file = 'class-' . strtolower(str_replace('_', '-', array_pop($parts))) . '.php';

    $directories = array(
        AA_PLUGIN_DIR . 'includes/',
        AA_PLUGIN_DIR . 'admin/',
        AA_PLUGIN_DIR . 'api/',
        AA_PLUGIN_DIR . 'public/',
    );

    foreach ($directories as $dir) {
        $file = $dir . $class_file;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Activation hook.
 */
register_activation_hook(__FILE__, function () {
    require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-activator.php';
    Always_Analytics\Always_Analytics_Activator::activate();
});

/**
 * Deactivation hook.
 */
register_deactivation_hook(__FILE__, function () {
    require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-deactivator.php';
    Always_Analytics\Always_Analytics_Deactivator::deactivate();
});

/**
 * Main plugin class — Singleton.
 */
final class Always_Analytics
{

    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->set_locale();
        $this->register_hooks();
    }

    private function load_dependencies()
    {
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-loader.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-tracker.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-session.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-bot-filter.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-privacy.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-geolocation.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-cache.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-export.php';
        require_once AA_PLUGIN_DIR . 'includes/class-always-analytics-consent.php';
        require_once AA_PLUGIN_DIR . 'api/class-always-analytics-rest.php';

        if (is_admin()) {
            require_once AA_PLUGIN_DIR . 'admin/class-always-analytics-admin.php';
            require_once AA_PLUGIN_DIR . 'admin/class-always-analytics-dashboard.php';
            require_once AA_PLUGIN_DIR . 'admin/class-always-analytics-settings.php';
        }
    }

    private function set_locale()
    {
        load_plugin_textdomain('always-analytics', false, dirname(AA_PLUGIN_BASENAME) . '/languages/');
    }

    private function register_hooks()
    {
        // Front-end tracking
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracker'));

        // REST API
        add_action('rest_api_init', array('Always_Analytics\\Always_Analytics_Rest', 'register_routes'));


        // Privacy hooks (RGPD)
        $privacy = new Always_Analytics\Always_Analytics_Privacy();
        add_action('admin_init', array($privacy, 'register_privacy_hooks'));

        // Consent banner
        $consent = new Always_Analytics\Always_Analytics_Consent();
        add_action('wp_footer', array($consent, 'render_banner'));
        add_action('wp_enqueue_scripts', array($consent, 'enqueue_assets'));

        // Pixel noscript — injecté dans wp_footer() après le tracker JS
        add_action('wp_footer', array($this, 'render_noscript_pixel'), 99);

        // Cron jobs
        add_action('aa_daily_aggregate', array($this, 'run_daily_aggregate'));
        add_action('aa_daily_purge', array($this, 'run_daily_purge'));
        add_action('always_analytics_expire_sessions', array($this, 'run_expire_sessions'));

        // Admin hooks
        if (is_admin()) {
            $admin = new Always_Analytics\Always_Analytics_Admin();
            add_action('admin_menu', array($admin, 'add_menu_pages'));
            add_action('admin_enqueue_scripts', array($admin, 'enqueue_assets'));
            add_action('admin_notices', array($admin, 'render_refresh_button'));
            add_action('wp_ajax_always_analytics_manual_purge', array($admin, 'ajax_manual_purge'));

            $dashboard = new Always_Analytics\Always_Analytics_Dashboard();
            add_action('wp_dashboard_setup', array($dashboard, 'register_widgets'));

            $settings = new Always_Analytics\Always_Analytics_Settings();
            add_action('admin_init', array($settings, 'register_settings'));

            // Schema updates / Migrations
            add_action('admin_init', array('Always_Analytics\\Always_Analytics_Activator', 'maybe_update'));
        }
    }

    /**
     * Enqueue the front-end tracker script.
     */
    public function enqueue_tracker()
    {
        $options = get_option('always_analytics_options', array());

        // Don't track if disabled
        if (!empty($options['disable_tracking'])) {
            return;
        }

        // Don't track excluded roles
        if (is_user_logged_in()) {
            $excluded_roles = isset($options['excluded_roles']) ? (array)$options['excluded_roles'] : array('administrator');
            $user = wp_get_current_user();
            if (array_intersect($excluded_roles, $user->roles)) {
                return;
            }
        }

        wp_enqueue_script(
            'always-analytics-tracker',
            AA_PLUGIN_URL . 'public/js/always-analytics-tracker.js',
            array(),
            AA_VERSION,
            true
        );

        $tracking_mode = isset($options['tracking_mode']) ? $options['tracking_mode'] : 'cookieless';
        $consent_enabled = !empty($options['consent_enabled']);
        $cookieless_window = isset($options['cookieless_window']) ? $options['cookieless_window'] : 'daily';

        $config = array(
            'endpoint'         => esc_url_raw(rest_url('always-analytics/v1/hit')),
            'trackingMode'     => $tracking_mode,
            'cookielessWindow' => $cookieless_window,
            'consentGiven'     => ('cookie' === $tracking_mode && $consent_enabled) ? 'pending' : 'not_required',
            'postId'           => get_queried_object_id() ?: 0,
        );

        // En mode cookie + bannière, injecter l'endpoint pour le hit pre_consent
        if ('cookie' === $tracking_mode && $consent_enabled) {
            $config['preConsentEnabled'] = true;
        }

        wp_localize_script('always-analytics-tracker', 'alwaysAnalyticsConfig', $config);
    }

    /**
     * Injecte le pixel <noscript> dans le footer pour tracker les visiteurs sans JS.
     * Actif dans tous les modes (cookieless, cookie+bannière, cookie sans bannière).
     * Sécurité : le pixel utilise toujours le mode cookieless côté serveur.
     */
    public function render_noscript_pixel()
    {
        $options = get_option('always_analytics_options', array());

        if (!empty($options['disable_tracking'])) {
            return;
        }

        // Ne pas injecter pour les rôles exclus
        if (is_user_logged_in()) {
            $excluded_roles = isset($options['excluded_roles']) ? (array)$options['excluded_roles'] : array('administrator');
            $user = wp_get_current_user();
            if (array_intersect($excluded_roles, $user->roles)) {
                return;
            }
        }

        $post_id = get_queried_object_id() ?: 0;
        $referrer = '';
        // On ne peut pas lire document.referrer en PHP, mais le Referer HTTP est disponible
        $http_ref = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

        $pixel_url = add_query_arg(array_filter(array(
            'p' => $post_id ?: null,
            'r' => $http_ref ? rawurlencode($http_ref) : null,
            'u' => rawurlencode(esc_url_raw((is_ssl() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])),
        )), esc_url_raw(rest_url('always-analytics/v1/noscript')));

        echo '<noscript><img src="' . esc_url($pixel_url) . '" width="1" height="1" alt="" loading="eager" style="display:none;position:absolute;" /></noscript>' . "\n";
    }

    /**
     * Daily aggregation cron job.
     */
    public function run_daily_aggregate()
    {
        global $wpdb;
        $table_hits = $wpdb->prefix . 'aa_hits';
        $table_daily = $wpdb->prefix . 'aa_daily';
        $t_sess = $wpdb->prefix . 'aa_sessions';
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_daily}
                (stat_date, page_url, post_id, unique_visitors, page_views, sessions,
                 new_visitors, returning_vis, avg_duration, bounce_rate)
             SELECT
                DATE(h.hit_at) as stat_date,
                h.page_url,
                h.post_id,
                COUNT(DISTINCT h.visitor_hash) as unique_visitors,
                COUNT(*) as page_views,
                COUNT(DISTINCT h.session_id) as sessions,
                SUM(h.is_new_visitor) as new_visitors,
                SUM(CASE WHEN h.is_new_visitor = 0 THEN 1 ELSE 0 END) as returning_vis,
                COALESCE(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time
                                  WHEN s.duration > 0 THEN s.duration ELSE NULL END), 0) as avg_duration,
                CASE WHEN COUNT(DISTINCT h.session_id) > 0
                     THEN SUM(CASE WHEN s.is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT h.session_id) * 100
                     ELSE 0 END as bounce_rate
             FROM {$table_hits} h
             LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
             WHERE DATE(h.hit_at) = %s AND h.is_superseded = 0
             GROUP BY DATE(h.hit_at), h.page_url, h.post_id
             ON DUPLICATE KEY UPDATE
                unique_visitors = VALUES(unique_visitors),
                page_views      = VALUES(page_views),
                sessions        = VALUES(sessions),
                new_visitors    = VALUES(new_visitors),
                returning_vis   = VALUES(returning_vis),
                avg_duration    = VALUES(avg_duration),
                bounce_rate     = VALUES(bounce_rate)",
            $yesterday
        ));

        do_action('always_analytics_after_daily_aggregate', $yesterday);
    }

    /**
     * Daily purge cron job.
     */
    public function run_daily_purge()
    {
        $privacy = new Always_Analytics\Always_Analytics_Privacy();
        $privacy->purge_old_data();
    }

    /**
     * Expire stale sessions — estime la durée des sessions orphelines.
     * Exécuté toutes les heures via WP-Cron.
     */
    public function run_expire_sessions()
    {
        Always_Analytics\Always_Analytics_Session::expire_stale_sessions();
    }
}

// Initialize the plugin
add_action('plugins_loaded', function () {
    Always_Analytics::instance();
});


