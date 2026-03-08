<?php
/**
 * Plugin Name:       Statify
 * Plugin URI:        https://example.com/statify
 * Description:       Statistiques avancées auto-hébergées, légères et respectueuses de la vie privée pour WordPress.
 * Version:           1.1.0
 * Author:            Adrien
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       statify
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'STATIFY_VERSION', '1.1.0' );
define( 'STATIFY_PLUGIN_FILE', __FILE__ );
define( 'STATIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STATIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STATIFY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'Statify\\';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $parts          = explode( '\\', $relative_class );
    $class_file     = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

    $directories = array(
        STATIFY_PLUGIN_DIR . 'includes/',
        STATIFY_PLUGIN_DIR . 'admin/',
        STATIFY_PLUGIN_DIR . 'api/',
        STATIFY_PLUGIN_DIR . 'public/',
    );

    foreach ( $directories as $dir ) {
        $file = $dir . $class_file;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
} );

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function () {
    require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-activator.php';
    Statify\Statify_Activator::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
    require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-deactivator.php';
    Statify\Statify_Deactivator::deactivate();
} );

/**
 * Main plugin class — Singleton.
 */
final class Statify {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->register_hooks();
    }

    private function load_dependencies() {
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-loader.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-tracker.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-session.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-bot-filter.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-privacy.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-geolocation.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-cache.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-export.php';
        require_once STATIFY_PLUGIN_DIR . 'includes/class-statify-consent.php';
        require_once STATIFY_PLUGIN_DIR . 'api/class-statify-rest.php';

        if ( is_admin() ) {
            require_once STATIFY_PLUGIN_DIR . 'admin/class-statify-admin.php';
            require_once STATIFY_PLUGIN_DIR . 'admin/class-statify-dashboard.php';
            require_once STATIFY_PLUGIN_DIR . 'admin/class-statify-settings.php';
        }
    }

    private function set_locale() {
        load_plugin_textdomain( 'statify', false, dirname( STATIFY_PLUGIN_BASENAME ) . '/languages/' );
    }

    private function register_hooks() {
        // Front-end tracking
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );

        // REST API
        add_action( 'rest_api_init', array( 'Statify\\Statify_Rest', 'register_routes' ) );

        // Neutralise l'erreur d'auth WP core sur /hit.
        // WP REST appelle rest_cookie_check_errors() qui renvoie une WP_Error 403
        // si un cookie de session est présent sans nonce valide — AVANT notre callback.
        // On intercepte cette erreur et on la supprime uniquement pour /hit.
        add_filter( 'rest_authentication_errors', function ( $result ) {
            $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
            // Couvre les deux formes d'URL WP REST :
            // /wp-json/statify/v1/hit  ET  ?rest_route=/statify/v1/hit
            $is_hit = ( false !== strpos( $uri, '/statify/v1/hit' ) )
                   || ( false !== strpos( $uri, 'rest_route=%2Fstatify%2Fv1%2Fhit' ) )
                   || ( false !== strpos( $uri, 'rest_route=/statify/v1/hit' ) );
            if ( $is_hit ) {
                return null; // pas d'erreur d'auth sur cet endpoint public
            }
            return $result;
        }, 100 );

        // Privacy hooks (RGPD)
        $privacy = new Statify\Statify_Privacy();
        add_action( 'admin_init', array( $privacy, 'register_privacy_hooks' ) );

        // Consent banner
        $consent = new Statify\Statify_Consent();
        add_action( 'wp_footer', array( $consent, 'render_banner' ) );
        add_action( 'wp_enqueue_scripts', array( $consent, 'enqueue_assets' ) );

        // Cron jobs
        add_action( 'statify_daily_aggregate', array( $this, 'run_daily_aggregate' ) );
        add_action( 'statify_daily_purge', array( $this, 'run_daily_purge' ) );
        add_action( 'statify_expire_sessions', array( $this, 'run_expire_sessions' ) );

        // Admin hooks
        if ( is_admin() ) {
            $admin = new Statify\Statify_Admin();
            add_action( 'admin_menu', array( $admin, 'add_menu_pages' ) );
            add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
            add_action( 'admin_notices', array( $admin, 'render_refresh_button' ) );

            $dashboard = new Statify\Statify_Dashboard();
            add_action( 'wp_dashboard_setup', array( $dashboard, 'register_widgets' ) );

            $settings = new Statify\Statify_Settings();
            add_action( 'admin_init', array( $settings, 'register_settings' ) );
        }
    }

    /**
     * Enqueue the front-end tracker script.
     */
    public function enqueue_tracker() {
        $options = get_option( 'statify_options', array() );

        // Don't track if disabled
        if ( ! empty( $options['disable_tracking'] ) ) {
            return;
        }

        // Don't track excluded roles
        if ( is_user_logged_in() ) {
            $excluded_roles = isset( $options['excluded_roles'] ) ? (array) $options['excluded_roles'] : array( 'administrator' );
            $user           = wp_get_current_user();
            if ( array_intersect( $excluded_roles, $user->roles ) ) {
                return;
            }
        }

        wp_enqueue_script(
            'statify-tracker',
            STATIFY_PLUGIN_URL . 'public/js/statify-tracker.js',
            array(),
            STATIFY_VERSION,
            true
        );

        $tracking_mode = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
        $consent_enabled = ! empty( $options['consent_enabled'] );

        wp_localize_script( 'statify-tracker', 'statifyConfig', array(
            'endpoint'     => esc_url_raw( rest_url( 'statify/v1/hit' ) ),
            'trackingMode' => $tracking_mode,
            'consentGiven' => ( 'cookie' === $tracking_mode && $consent_enabled ) ? 'pending' : 'not_required',
        ) );
    }

    /**
     * Daily aggregation cron job.
     */
    public function run_daily_aggregate() {
        global $wpdb;
        $table_hits  = $wpdb->prefix . 'statify_hits';
        $table_daily = $wpdb->prefix . 'statify_daily';
        $t_sess      = $wpdb->prefix . 'statify_sessions';
        $yesterday   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
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
             WHERE DATE(h.hit_at) = %s
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
        ) );

        do_action( 'statify_after_daily_aggregate', $yesterday );
    }

    /**
     * Daily purge cron job.
     */
    public function run_daily_purge() {
        $privacy = new Statify\Statify_Privacy();
        $privacy->purge_old_data();
    }

    /**
     * Expire stale sessions — estime la durée des sessions orphelines.
     * Exécuté toutes les heures via WP-Cron.
     */
    public function run_expire_sessions() {
        Statify\Statify_Session::expire_stale_sessions();
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function () {
    Statify::instance();
} );

/**
 * Migration automatique au chargement du plugin.
 * Ajoute les colonnes et tables manquantes sans toucher aux données existantes.
 * S'exécute une fois par version, stocké dans statify_db_schema_version.
 */
add_action( 'plugins_loaded', function () {
    global $wpdb;

    // ── Vider le cache objet AVANT de lire la version ─────────────────────────
    // Sans ça, Redis/Memcached peut renvoyer une valeur périmée et sauter la migration.
    wp_cache_delete( 'statify_db_schema_version', 'options' );
    wp_cache_delete( 'alloptions', 'options' );

    $schema_version = (int) get_option( 'statify_db_schema_version', 0 );
    // Pas de return anticipé : chaque étape est idempotente (vérifie SHOW TABLES / information_schema).
    // Cela corrige les installations où la version était à 3 mais la table statify_scroll manquait.

    $charset_collate = $wpdb->get_charset_collate();

    // ── 1. Colonne scroll_depth dans statify_hits ────────────────────────────
    // Garde-fou idempotent : SHOW COLUMNS plutôt que version numérique.
    $t_hits = $wpdb->prefix . 'statify_hits';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_hits ) ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_col = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $t_hits, 'scroll_depth'
        ) );
        if ( ! $has_col ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( "ALTER TABLE `{$t_hits}` ADD COLUMN `scroll_depth` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `user_id`" );
        }
    }

    // ── 2. Colonne max_scroll_depth dans statify_sessions ────────────────────
    $t_sess = $wpdb->prefix . 'statify_sessions';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_col_s = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $t_sess, 'max_scroll_depth'
        ) );
        if ( ! $has_col_s ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( "ALTER TABLE `{$t_sess}` ADD COLUMN `max_scroll_depth` TINYINT UNSIGNED NOT NULL DEFAULT 0" );
        }
    }

    // ── 3. Table statify_scroll ──────────────────────────────────────────────
    $t_scroll = $wpdb->prefix . 'statify_scroll';
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql_scroll = "CREATE TABLE {$t_scroll} (
        id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        session_id      VARCHAR(64)      NOT NULL,
        visitor_hash    VARCHAR(64)      NOT NULL DEFAULT '',
        page_url        VARCHAR(2048)    NOT NULL DEFAULT '',
        post_id         BIGINT UNSIGNED  DEFAULT 0,
        scroll_depth    TINYINT UNSIGNED NOT NULL,
        recorded_at     DATETIME         NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_session  (session_id),
        KEY idx_page     (page_url(191)),
        KEY idx_recorded (recorded_at)
    ) {$charset_collate};";
    dbDelta( $sql_scroll );

    // ── 4. Colonne engagement_time dans statify_sessions ────────────────────
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_et = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $t_sess, 'engagement_time'
        ) );
        if ( ! $has_et ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( "ALTER TABLE `{$t_sess}` ADD COLUMN `engagement_time` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `max_scroll_depth`" );
        }
    }

    update_option( 'statify_db_schema_version', 4 );
}, 5 );

/**
 * Force un flush du cache $wpdb interne sur chaque requête REST statify.
 * Empêche $wpdb de servir des résultats mis en mémoire lors de la même requête PHP.
 */
add_action( 'rest_api_init', function () {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/statify/' ) !== false && strpos( $uri, '/statify/v1/hit' ) === false ) {
        global $wpdb;
        $wpdb->flush();
    }
}, 1 );

/**
 * Anti-cache global pour l'admin WP et toutes les routes REST /statify/.
 * Couvre : LiteSpeed Cache, WP Rocket, W3TC, Varnish, Nginx, Cloudflare, Fastly.
 */

// ── 1. Headers HTTP bruts (priorité 0 = avant tout autre plugin) ─────────────
add_action( 'send_headers', function () {
    $is_statify_rest = defined( 'REST_REQUEST' ) && REST_REQUEST
        && strpos( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', '/statify/' ) !== false;

    if ( ! is_admin() && ! $is_statify_rest ) {
        return;
    }

    if ( headers_sent() ) {
        return;
    }

    header_remove( 'X-LiteSpeed-Cache' );
    header_remove( 'X-Cache' );
    header_remove( 'ETag' );
    header_remove( 'Last-Modified' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0, proxy-revalidate', true );
    header( 'Pragma: no-cache', true );
    header( 'Expires: Thu, 01 Jan 1970 00:00:01 GMT', true );
    header( 'X-LiteSpeed-Cache-Control: no-cache, no-store', true );
    header( 'Surrogate-Control: no-store', true );
    header( 'CDN-Cache-Control: no-store', true );
    header( 'Cloudflare-CDN-Cache-Control: no-store', true );
    header( 'Vary: *', true );
}, 0 );

// ── 2. LiteSpeed Cache plugin — API officielle ────────────────────────────────
add_action( 'init', function () {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( is_admin() || strpos( $uri, '/statify/' ) !== false ) {
        do_action( 'litespeed_control_set_nocache', 'statify-no-cache' );
        do_action( 'litespeed_tag_add', 'no-vary' );
    }
}, 0 );

// ── 3. LiteSpeed — filtre is_cacheable ───────────────────────────────────────
add_filter( 'litespeed_is_cacheable', function ( $cacheable ) {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( is_admin() || strpos( $uri, '/statify/' ) !== false ) {
        return false;
    }
    return $cacheable;
}, 0 );

// ── 4. WP Rocket — exclusion URI ─────────────────────────────────────────────
add_filter( 'rocket_cache_reject_uri', function ( $uris ) {
    $uris[] = '/wp-json/statify/(.*)';
    $uris[] = '.*[?&]_t=.*'; // exclut les URLs avec timestamp anti-cache
    return $uris;
} );

// ── 5. W3 Total Cache ─────────────────────────────────────────────────────────
add_filter( 'w3tc_can_cache', function ( $can ) {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/statify/' ) !== false ) {
        return false;
    }
    return $can;
}, 0 );

// ── 6. Nettoyage admin_init (headers résiduels d'autres plugins) ──────────────
add_action( 'admin_init', function () {
    if ( headers_sent() ) {
        return;
    }
    header_remove( 'X-LiteSpeed-Cache' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'Pragma: no-cache', true );
    header( 'X-LiteSpeed-Cache-Control: no-cache, no-store', true );
}, 0 );
