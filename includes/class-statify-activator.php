<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin activator — creates database tables and default options.
 */
class Statify_Activator {

    /**
     * Run activation tasks.
     */
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::migrate_from_advstats();
        self::set_default_options();
        self::schedule_crons();
        flush_rewrite_rules();
    }

    /**
     * Check minimum requirements.
     */
    private static function check_requirements() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( STATIFY_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Advanced Stats requires PHP 7.4 or higher.', 'statify' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            deactivate_plugins( STATIFY_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Advanced Stats requires WordPress 5.8 or higher.', 'statify' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_hits     = $wpdb->prefix . 'statify_hits';
        $table_daily    = $wpdb->prefix . 'statify_daily';
        $table_sessions = $wpdb->prefix . 'statify_sessions';

        $sql_hits = "CREATE TABLE {$table_hits} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_hash    VARCHAR(64)     NOT NULL,
            session_id      VARCHAR(64)     NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            page_title      VARCHAR(512)    DEFAULT '',
            post_id         BIGINT UNSIGNED DEFAULT 0,
            post_type       VARCHAR(20)     DEFAULT '',
            referrer        VARCHAR(2048)   DEFAULT '',
            referrer_domain VARCHAR(255)    DEFAULT '',
            utm_source      VARCHAR(255)    DEFAULT '',
            utm_medium      VARCHAR(255)    DEFAULT '',
            utm_campaign    VARCHAR(255)    DEFAULT '',
            device_type     VARCHAR(20)     DEFAULT 'unknown',
            browser         VARCHAR(100)    DEFAULT '',
            browser_version VARCHAR(20)     DEFAULT '',
            os              VARCHAR(100)    DEFAULT '',
            os_version      VARCHAR(20)     DEFAULT '',
            screen_width    SMALLINT UNSIGNED DEFAULT 0,
            screen_height   SMALLINT UNSIGNED DEFAULT 0,
            country_code    CHAR(2)         DEFAULT '',
            region          VARCHAR(100)    DEFAULT '',
            city            VARCHAR(100)    DEFAULT '',
            is_new_visitor  TINYINT(1)      DEFAULT 1,
            is_logged_in    TINYINT(1)      DEFAULT 0,
            user_id         BIGINT UNSIGNED DEFAULT 0,
            scroll_depth    TINYINT UNSIGNED DEFAULT 0,
            hit_at          DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_hit_at       (hit_at),
            KEY idx_visitor_hash (visitor_hash),
            KEY idx_session_id   (session_id),
            KEY idx_post_id      (post_id),
            KEY idx_country      (country_code)
        ) {$charset_collate};";

        $sql_sessions = "CREATE TABLE {$table_sessions} (
            session_id      VARCHAR(64)     NOT NULL,
            visitor_hash    VARCHAR(64)     NOT NULL,
            started_at      DATETIME        NOT NULL,
            ended_at        DATETIME        DEFAULT NULL,
            duration        INT UNSIGNED    DEFAULT 0,
            page_count      SMALLINT UNSIGNED DEFAULT 1,
            entry_page      VARCHAR(2048)   DEFAULT '',
            exit_page       VARCHAR(2048)   DEFAULT '',
            referrer        VARCHAR(2048)   DEFAULT '',
            device_type     VARCHAR(20)     DEFAULT 'unknown',
            country_code    CHAR(2)         DEFAULT '',
            is_bounce       TINYINT(1)      DEFAULT 1,
            max_scroll_depth TINYINT UNSIGNED DEFAULT 0,
            engagement_time  INT UNSIGNED    DEFAULT 0,
            PRIMARY KEY  (session_id),
            KEY idx_started  (started_at),
            KEY idx_visitor  (visitor_hash)
        ) {$charset_collate};";

        $sql_daily = "CREATE TABLE {$table_daily} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date       DATE            NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            post_id         BIGINT UNSIGNED DEFAULT 0,
            unique_visitors INT UNSIGNED    DEFAULT 0,
            page_views      INT UNSIGNED    DEFAULT 0,
            sessions        INT UNSIGNED    DEFAULT 0,
            avg_duration    FLOAT           DEFAULT 0,
            bounce_rate     FLOAT           DEFAULT 0,
            new_visitors    INT UNSIGNED    DEFAULT 0,
            returning_vis   INT UNSIGNED    DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_date_page (stat_date, page_url(191)),
            KEY idx_post_id (post_id)
        ) {$charset_collate};";

        $table_scroll = $wpdb->prefix . 'statify_scroll';

        $sql_scroll = "CREATE TABLE {$table_scroll} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id      VARCHAR(64)     NOT NULL,
            visitor_hash    VARCHAR(64)     NOT NULL,
            page_url        VARCHAR(2048)   NOT NULL,
            post_id         BIGINT UNSIGNED DEFAULT 0,
            scroll_depth    TINYINT UNSIGNED NOT NULL,
            recorded_at     DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_session  (session_id),
            KEY idx_page     (page_url(191)),
            KEY idx_recorded (recorded_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_hits );
        dbDelta( $sql_sessions );
        dbDelta( $sql_daily );
        dbDelta( $sql_scroll );

        // Migration si table existante : ajouter les colonnes manquantes
        $cols = $wpdb->get_col( "DESCRIBE {$table_hits}", 0 );
        if ( ! in_array( 'scroll_depth', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_hits} ADD COLUMN scroll_depth TINYINT UNSIGNED DEFAULT 0 AFTER user_id" );
        }
        $cols_s = $wpdb->get_col( "DESCRIBE {$table_sessions}", 0 );
        if ( ! in_array( 'max_scroll_depth', $cols_s, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_sessions} ADD COLUMN max_scroll_depth TINYINT UNSIGNED DEFAULT 0" );
        }
        if ( ! in_array( 'engagement_time', $cols_s, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_sessions} ADD COLUMN engagement_time INT UNSIGNED DEFAULT 0 AFTER max_scroll_depth" );
        }

        update_option( 'statify_db_version', STATIFY_VERSION );
    }


    /**
     * Migrate data from old advstats_* tables and option to statify_* equivalents.
     * Runs only when the old tables/option exist — safe to call on fresh installs.
     */
    private static function migrate_from_advstats() {
        global $wpdb;

        // ── Option ────────────────────────────────────────────────────────────
        $old_option = get_option( 'advstats_options', null );
        if ( null !== $old_option && false === get_option( 'statify_options' ) ) {
            add_option( 'statify_options', $old_option );
            delete_option( 'advstats_options' );
        }

        // ── Tables ────────────────────────────────────────────────────────────
        $table_map = array(
            'advstats_hits'     => 'statify_hits',
            'advstats_sessions' => 'statify_sessions',
            'advstats_scroll'   => 'statify_scroll',
            'advstats_daily'    => 'statify_daily',
        );

        foreach ( $table_map as $old_suffix => $new_suffix ) {
            $old_table = $wpdb->prefix . $old_suffix;
            $new_table = $wpdb->prefix . $new_suffix;

            // Old table must exist
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $old_exists = (bool) $wpdb->get_var(
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table )
            );
            if ( ! $old_exists ) {
                continue;
            }

            // New table must exist (created just before by create_tables())
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_exists = (bool) $wpdb->get_var(
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table )
            );
            if ( ! $new_exists ) {
                continue;
            }

            // Skip if new table already has data (migration already ran)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$new_table}` LIMIT 1" ); // phpcs:ignore
            if ( $new_count > 0 ) {
                continue;
            }

            // Determine common columns between old and new table to avoid
            // INSERT errors if schemas differ slightly.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $old_cols = $wpdb->get_col( "DESCRIBE `{$old_table}`", 0 ); // phpcs:ignore
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $new_cols = $wpdb->get_col( "DESCRIBE `{$new_table}`", 0 ); // phpcs:ignore

            $common = array_intersect( $old_cols, $new_cols );
            if ( empty( $common ) ) {
                continue;
            }

            $cols_sql = implode( ', ', array_map( function( $c ) {
                return '`' . $c . '`';
            }, $common ) );

            // Batch copy in chunks of 5 000 rows to avoid memory issues on
            // large sites. Uses AUTO_INCREMENT id when available, otherwise
            // copies all at once.
            $has_id = in_array( 'id', $common, true );

            if ( $has_id ) {
                $min_id = (int) $wpdb->get_var( "SELECT MIN(id) FROM `{$old_table}`" ); // phpcs:ignore
                $max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$old_table}`" ); // phpcs:ignore

                $chunk = 5000;
                for ( $offset = $min_id; $offset <= $max_id; $offset += $chunk ) {
                    $end = $offset + $chunk - 1;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query(
                        "INSERT IGNORE INTO `{$new_table}` ({$cols_sql})
                         SELECT {$cols_sql} FROM `{$old_table}`
                         WHERE id BETWEEN {$offset} AND {$end}"
                    );
                }
            } else {
                // No id column (sessions use session_id as PK)
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query(
                    "INSERT IGNORE INTO `{$new_table}` ({$cols_sql})
                     SELECT {$cols_sql} FROM `{$old_table}`"
                );
            }

            // Drop old table once data is safely in the new one
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS `{$old_table}`" );
        }

        // Also clean up old db_version option
        delete_option( 'advstats_db_version' );
        delete_option( 'advstats_db_schema_version' );

        // Clean up old cron hooks
        $old_crons = array(
            'advstats_daily_aggregate',
            'advstats_daily_purge',
            'advstats_expire_sessions',
        );
        foreach ( $old_crons as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $defaults = array(
            'disable_tracking'   => false,
            'tracking_mode'      => 'cookieless',    // 'cookieless' or 'cookie'
            'excluded_roles'     => array( 'administrator' ),
            'excluded_ips'       => '',
            'anonymize_ip'       => true,
            'retention_days'     => 90,
            'delete_on_uninstall'=> true,
            'geo_enabled'        => true,
            'geo_provider'       => 'native',         // 'native' or 'maxmind'
            'maxmind_db_path'    => '',
            'cache_ttl'          => 300,              // 5 minutes
            'bot_filter_mode'    => 'normal',         // 'strict', 'normal', 'off'
            'export_format'      => 'csv',
            'consent_enabled'    => false,
            'consent_message'    => __( 'Ce site utilise des cookies pour analyser le trafic. Acceptez-vous ?', 'statify' ),
            'consent_accept'     => __( 'Accepter', 'statify' ),
            'consent_decline'    => __( 'Refuser', 'statify' ),
            'consent_bg_color'   => '#1a1a2e',
            'consent_text_color' => '#ffffff',
            'consent_btn_color'  => '#6c63ff',
        );

        // Only set defaults if option doesn't exist yet (fresh install or post-migration)
        if ( false === get_option( 'statify_options' ) ) {
            add_option( 'statify_options', $defaults );
        }
    }

    /**
     * Schedule WP-Cron events.
     */
    private static function schedule_crons() {
        if ( ! wp_next_scheduled( 'statify_daily_aggregate' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', 'statify_daily_aggregate' );
        }
        if ( ! wp_next_scheduled( 'statify_daily_purge' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 03:00:00' ), 'daily', 'statify_daily_purge' );
        }
        if ( ! wp_next_scheduled( 'statify_expire_sessions' ) ) {
            wp_schedule_event( time(), 'hourly', 'statify_expire_sessions' );
        }
    }
}
