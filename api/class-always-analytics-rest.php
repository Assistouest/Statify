<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API — zéro cache, données live depuis la DB à chaque appel.
 */
class Always_Analytics_Rest {

    const NAMESPACE = 'always-analytics/v1';

    /**
     * Resolve the best IP address to use for rate-limiting.
     *
     * Uses REMOTE_ADDR as the authoritative source. If the site is configured
     * with trusted proxies (same setting as the tracker), the first entry of
     * X-Forwarded-For is used instead — but only when REMOTE_ADDR is itself
     * in the trusted-proxy list, preventing trivial header spoofing.
     *
     * Falls back to '0.0.0.0' for invalid values so the rate-limit bucket
     * still works (all invalid clients share one bucket rather than bypassing).
     *
     * @param \WP_REST_Request $request
     * @return string Validated IP string.
     */
    private static function get_rate_limit_ip( $request ) {
        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '0.0.0.0';

        if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
            return '0.0.0.0';
        }

        $options = get_option( 'always_analytics_options', array() );
        $mode    = isset( $options['trusted_proxy_mode'] ) ? $options['trusted_proxy_mode'] : 'none';

        if ( 'custom' === $mode && ! empty( $options['trusted_proxies'] ) ) {
            $trusted = array_filter( array_map( 'trim', explode( "\n", $options['trusted_proxies'] ) ) );
            if ( Always_Analytics_Tracker::is_ip_in_range( $remote_addr, $trusted ) ) {
                $forwarded = $request->get_header( 'x-forwarded-for' );
                if ( $forwarded ) {
                    $first = trim( explode( ',', $forwarded )[0] );
                    if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
                        return $first;
                    }
                }
            }
        }

        return $remote_addr;
    }

    private static function no_cache_headers() {
        if ( headers_sent() ) {
            return;
        }
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'Pragma: no-cache', true );
        header( 'Expires: Thu, 01 Jan 1970 00:00:01 GMT', true );
    }

    public static function register_routes() {

        // Headers no-cache sur CHAQUE réponse always-analytics dès que WP les envoie
        add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
            if ( strpos( $request->get_route(), '/always-analytics/' ) !== false ) {
                self::no_cache_headers();
            }
            return $served;
        }, 1, 3 );

        // ── Endpoint public : enregistrement d'un hit ──────────────────────────
        register_rest_route( self::NAMESPACE, '/hit', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_hit' ),
            'permission_callback' => '__return_true',
        ) );

        // ── Endpoint public : pixel noscript (GET, retourne GIF 1×1) ─────────
        register_rest_route( self::NAMESPACE, '/noscript', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_noscript' ),
            'permission_callback' => '__return_true',
        ) );

        // ── Endpoints admin ────────────────────────────────────────────────────
        $admin_routes = array(
            array( 'overview',        'GET', 'get_overview' ),
            array( 'chart/visits',    'GET', 'get_chart_visits' ),
            array( 'top-pages',       'GET', 'get_top_pages' ),
            array( 'top-referrers',   'GET', 'get_top_referrers' ),
            array( 'countries',       'GET', 'get_countries' ),
            array( 'devices',         'GET', 'get_devices' ),
            array( 'visitors',        'GET', 'get_visitors' ),
            array( 'recent-visitors', 'GET', 'get_recent_visitors' ),
            array( 'engagement',      'GET', 'get_engagement' ),
            array( 'engagement/pages','GET', 'get_engagement_pages' ),
            array( 'export',          'GET', 'handle_export' ),
        );

        foreach ( $admin_routes as $route ) {
            register_rest_route( self::NAMESPACE, '/' . $route[0], array(
                'methods'             => $route[1],
                'callback'            => array( __CLASS__, $route[2] ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ) );
        }

        // ── Endpoint sources de tracking ──────────────────────────────────────
        register_rest_route( self::NAMESPACE, '/hit-sources', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_hit_sources' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        // ── Endpoints campagnes ────────────────────────────────────────────────
        register_rest_route( self::NAMESPACE, '/campaigns', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_campaigns' ),
                'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'create_campaign' ),
                'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            ),
        ) );
        register_rest_route( self::NAMESPACE, '/campaigns/(?P<id>\d+)', array(
            array(
                'methods'             => 'DELETE',
                'callback'            => array( __CLASS__, 'delete_campaign' ),
                'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            ),
        ) );
    }

    // ── Paramètres de date ─────────────────────────────────────────────────────

    private static function get_date_params( $request ) {
        $today = wp_date( 'Y-m-d' );
        $from  = sanitize_text_field( $request->get_param( 'from' ) ?: wp_date( 'Y-m-d', strtotime( '-30 days' ) ) );
        $to    = sanitize_text_field( $request->get_param( 'to' )   ?: $today );

        $tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

        return array(
            'from'      => $from,
            'to'        => $to,
            'from_utc'  => gmdate( 'Y-m-d H:i:s', strtotime( $from . ' 00:00:00' ) - $tz_offset_seconds ),
            'to_utc'    => gmdate( 'Y-m-d H:i:s', strtotime( $to   . ' 23:59:59' ) - $tz_offset_seconds ),
            'is_today'  => ( $from === $today && $to === $today ),
            'post_type' => sanitize_key(        $request->get_param( 'post_type' ) ?: '' ),
            'device'    => sanitize_text_field( $request->get_param( 'device' )    ?: '' ),
            'country'   => sanitize_text_field( $request->get_param( 'country' )   ?: '' ),
        );
    }

    private static function build_where( $params, &$args ) {
        $where  = array( 'hit_at >= %s', 'hit_at <= %s' );
        $args[] = $params['from_utc'];
        $args[] = $params['to_utc'];
        if ( ! empty( $params['post_type'] ) ) { $where[] = 'post_type = %s';    $args[] = $params['post_type']; }
        if ( ! empty( $params['device'] ) )    { $where[] = 'device_type = %s'; $args[] = $params['device']; }
        if ( ! empty( $params['country'] ) )   { $where[] = 'country_code = %s'; $args[] = $params['country']; }
        return implode( ' AND ', $where );
    }

    // ── /hit ───────────────────────────────────────────────────────────────────

    public static function handle_hit( $request ) {
        // ── Auth : vérification d'origine du domaine ─────────────────────────────
        // On ne vérifie PAS le nonce WP ici : le tracker JS ne l'envoie plus,
        // car WP core bloque la requête AVANT handle_hit si le nonce est expiré.
        // Sécurité : Origin OU Referer doit correspondre au domaine du site.
        $site_host  = wp_parse_url( home_url(), PHP_URL_HOST );
        $origin     = $request->get_header( 'origin' );
        $origin_ok  = $origin  && ( wp_parse_url( $origin,  PHP_URL_HOST ) === $site_host );
        $referer    = $request->get_header( 'referer' );
        $referer_ok = $referer && ( wp_parse_url( $referer, PHP_URL_HOST ) === $site_host );
        if ( ! $origin_ok && ! $referer_ok ) {
            return new \WP_REST_Response( null, 403 );
        }

        // ── Lecture du body JSON AVANT de lire sessionId ──────────────────────
        // get_param() lit d'abord les query params (vides sur POST JSON),
        // puis le body — mais seulement si Content-Type est application/json.
        // On lit explicitement le JSON body pour être sûr.
        $data = $request->get_json_params();
        if ( empty( $data ) ) {
            $data = $request->get_body_params();
        }
        if ( empty( $data ) ) {
            return new \WP_REST_Response( null, 204 );
        }

        // ── Session ID : toujours depuis le body ───────────────────────────────
        $session_id = isset( $data['sessionId'] ) ? sanitize_text_field( $data['sessionId'] ) : '';

        // ── Rate-limit ─────────────────────────────────────────────────
        // Two complementary layers for non-ping/scroll actions:
        //
        // 1. Per-session  : 1 hit / 2 s  — stops repeated submits from same tab.
        // 2. Per-IP       : 60 hits / 60 s — stops DDoS via sessionId rotation
        //                   without being harsh enough to penalise shared IPs
        //                   (VPNs, NAT, office networks).
        $action = isset( $data['action'] ) ? $data['action'] : '';
        if ( ! in_array( $action, array( 'ping', 'scroll' ), true ) ) {

            // Layer 1 — session cooldown (unchanged behaviour).
            if ( $session_id ) {
                $rate_key = 'aa_rl_' . md5( $session_id );
                if ( false !== get_transient( $rate_key ) ) {
                    return new \WP_REST_Response( null, 429 );
                }
                set_transient( $rate_key, 1, 2 );
            }

            // Layer 2 — per-IP counter, threshold 60 req / 60 s.
            $client_ip = self::get_rate_limit_ip( $request );
            $ip_key    = 'aa_rl_ip_' . md5( $client_ip );
            $ip_hits   = (int) get_transient( $ip_key );
            if ( $ip_hits >= 60 ) {
                return new \WP_REST_Response( null, 429 );
            }
            // Increment; set TTL only on first hit to keep a fixed 60-s window.
            if ( 0 === $ip_hits ) {
                set_transient( $ip_key, 1, 60 );
            } else {
                set_transient( $ip_key, $ip_hits + 1, 60 );
            }
        }

        // ── Dispatch selon action ──────────────────────────────────────────────
        if ( 'ping' === $action ) {
            if ( $session_id ) {
                $scroll_depth    = isset( $data['scrollDepth'] )    ? absint( $data['scrollDepth'] )    : null;
                $client_duration = isset( $data['clientDuration'] ) ? absint( $data['clientDuration'] ) : null;
                $engagement_time = isset( $data['engagementTime'] ) ? absint( $data['engagementTime'] ) : null;
                Always_Analytics_Session::ping_session( $session_id, $scroll_depth, $client_duration, $engagement_time );
            }
            return new \WP_REST_Response( null, 204 );
        }

        if ( 'scroll' === $action ) {
            if ( $session_id ) {
                Always_Analytics_Tracker::handle_scroll( $data );
            }
            return new \WP_REST_Response( null, 204 );
        }

        // ── Hit pre_consent (cookieless avancé avant bannière) ────────────────
        // Envoyé immédiatement au chargement en mode cookie+bannière.
        // Collecte scroll, durée, engagement AVANT que l'utilisateur réponde.
        // Marqué hit_source='pre_consent'. Si l'utilisateur accepte ensuite,
        // un hit complet arrive avec preConsentSessionId → fusion via upgrade_pre_consent().
        if ( 'pre_consent' === $action ) {
            $data['hitSource'] = 'pre_consent';
            Always_Analytics_Tracker::track( $data );
            return new \WP_REST_Response( null, 204 );
        }

        // ── Hit normal (JS complet) ────────────────────────────────────────────
        // Si un preConsentSessionId est fourni → le visiteur venait d'accepter.
        // On fusionne le hit pre_consent avec ce hit complet pour éviter le doublon.
        $pre_consent_sid = isset( $data['preConsentSessionId'] )
            ? sanitize_text_field( $data['preConsentSessionId'] )
            : '';

        $hit_id = Always_Analytics_Tracker::track( $data );

        // Fusion post-consentement uniquement si :
        //   1. Le hit complet a bien été inséré (track() retourne un ID > 0)
        //   2. Un preConsentSessionId est fourni
        //   3. Un visitorId est présent pour reconstruire le visitor_hash définitif
        if ( $hit_id && $pre_consent_sid && ! empty( $data['visitorId'] ) ) {
            $new_visitor_hash = hash( 'sha256', sanitize_text_field( $data['visitorId'] ) );
            Always_Analytics_Tracker::upgrade_pre_consent( $pre_consent_sid, $new_visitor_hash );
        }

        return new \WP_REST_Response( null, 204 );
    }

    // ── /noscript ─────────────────────────────────────────────────────────────

    /**
     * Endpoint GET — retourne un GIF 1×1 transparent et enregistre un hit minimal.
     * Appelé par la balise <noscript><img> — uniquement quand JS est désactivé.
     *
     * Toujours en mode cookieless (IP anonymisée, aucun cookie écrit/lu).
     * Déduplication intégrée dans Always_Analytics_Tracker::track_noscript().
     */
    public static function handle_noscript( $request ) {
        // ── Vérification du domaine ───────────────────────────────────────────
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $referer   = $request->get_header( 'referer' );
        if ( ! $referer || wp_parse_url( $referer, PHP_URL_HOST ) !== $site_host ) {
            // Même en cas de referer invalide, on renvoie quand même le GIF
            // pour éviter des images cassées, mais on n'insère rien.
            self::output_transparent_gif();
        }

        // ── Disable tracking check ────────────────────────────────────────────
        $options = get_option( 'always_analytics_options', array() );
        if ( ! empty( $options['disable_tracking'] ) ) {
            self::output_transparent_gif();
        }

        // ── Parsing des paramètres GET ────────────────────────────────────────
        $data = array(
            'url'     => sanitize_text_field( $request->get_param( 'u' ) ?: '' ),
            'postId'  => absint( $request->get_param( 'p' ) ?: 0 ),
            'referrer'=> sanitize_text_field( rawurldecode( $request->get_param( 'r' ) ?: '' ) ),
        );

        // UTM depuis l'URL si présents
        if ( $data['url'] ) {
            $parsed_url = wp_parse_url( $data['url'] );
            if ( ! empty( $parsed_url['query'] ) ) {
                parse_str( $parsed_url['query'], $qs );
                if ( ! empty( $qs['utm_source'] ) )   $data['utm_source']   = sanitize_text_field( $qs['utm_source'] );
                if ( ! empty( $qs['utm_medium'] ) )   $data['utm_medium']   = sanitize_text_field( $qs['utm_medium'] );
                if ( ! empty( $qs['utm_campaign'] ) ) $data['utm_campaign'] = sanitize_text_field( $qs['utm_campaign'] );
            }
        }

        // Rate-limit : 1 requête / 10s par IP (protège contre les crawlers sans UA bot)
        $ip       = Always_Analytics_Tracker::get_client_ip();
        $rl_key   = 'aa_noscript_rl_' . md5( $ip );
        if ( false !== get_transient( $rl_key ) ) {
            self::output_transparent_gif();
        }
        set_transient( $rl_key, 1, 10 );

        Always_Analytics_Tracker::track_noscript( $data );

        self::output_transparent_gif();
    }

    /**
     * Envoie un GIF transparent 1×1 et termine l'exécution.
     * Headers no-cache inclus pour éviter que le pixel soit mis en cache proxy.
     */
    private static function output_transparent_gif() {
        if ( ! headers_sent() ) {
            header( 'Content-Type: image/gif' );
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'Expires: Thu, 01 Jan 1970 00:00:01 GMT' );
        }
        // GIF transparent 1×1 pixel (35 octets, format GIF89a)
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }

    // ── /overview ─────────────────────────────────────────────────────────────

    public static function get_overview( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';
        $t_sess = $wpdb->prefix . 'aa_sessions';

        $args  = array();
        $where = self::build_where( $params, $args );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $main = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(DISTINCT visitor_hash) as unique_visitors,
                COUNT(*) as page_views,
                COUNT(DISTINCT session_id) as sessions,
                SUM(is_new_visitor) as new_visitors
             FROM {$table}
             WHERE {$where} AND is_superseded = 0",
            ...$args
        ) );

        $sess_args = array( $params['from_utc'], $params['to_utc'] );

        // Durée et engagement filtrés via les sessions liées aux hits de la période
        // (JOIN sur aa_hits pour rester cohérent avec le filtre hit_at + is_superseded = 0).
        // Sessions à 0s (visiteurs non-traçables) exclues des moyennes uniquement.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $avg_duration = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END)
             FROM {$t_sess} s
             WHERE s.session_id IN (
                 SELECT DISTINCT session_id FROM {$table}
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             ) AND (s.duration > 0 OR s.engagement_time > 0)",
            ...$sess_args
        ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $engagement = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as total, SUM(CASE WHEN s.is_bounce = 0 THEN 1 ELSE 0 END) as engaged
             FROM {$t_sess} s
             WHERE s.session_id IN (
                 SELECT DISTINCT session_id FROM {$table}
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             ) AND (s.duration > 0 OR s.engagement_time > 0)",
            ...$sess_args
        ) );

        $engagement_rate = ( $engagement && $engagement->total > 0 )
            ? round( ( $engagement->engaged / $engagement->total ) * 100, 1 )
            : 0;

        $days_diff = max( 1, ( strtotime( $params['to'] ) - strtotime( $params['from'] ) ) / DAY_IN_SECONDS );
        $prev_from = gmdate( 'Y-m-d', strtotime( $params['from'] . " -{$days_diff} days" ) );
        $prev_to   = gmdate( 'Y-m-d', strtotime( $params['from'] . ' -1 day' ) );
        $tz_off    = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $prev = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_hash) as unique_visitors, COUNT(*) as page_views
             FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
            gmdate( 'Y-m-d H:i:s', strtotime( $prev_from . ' 00:00:00' ) - $tz_off ),
            gmdate( 'Y-m-d H:i:s', strtotime( $prev_to   . ' 23:59:59' ) - $tz_off )
        ) );

        $change_visitors = 0;
        $change_views    = 0;
        if ( $prev && $prev->unique_visitors > 0 ) {
            $change_visitors = round( ( ( $main->unique_visitors - $prev->unique_visitors ) / $prev->unique_visitors ) * 100, 1 );
        }
        if ( $prev && $prev->page_views > 0 ) {
            $change_views = round( ( ( $main->page_views - $prev->page_views ) / $prev->page_views ) * 100, 1 );
        }

        return rest_ensure_response( array(
            'unique_visitors' => (int) $main->unique_visitors,
            'page_views'      => (int) $main->page_views,
            'sessions'        => (int) $main->sessions,
            'new_visitors'    => (int) $main->new_visitors,
            'avg_duration'    => round( (float) $avg_duration ),
            'engagement_rate' => $engagement_rate,
            'change_visitors' => $change_visitors,
            'change_views'    => $change_views,
        ) );
    }

    // ── /chart/visits ─────────────────────────────────────────────────────────

    public static function get_chart_visits( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';
        $from   = $params['from_utc'];
        $to     = $params['to_utc'];

        // Mode horaire : today uniquement (from === to)
        if ( $params['from'] === $params['to'] ) {
            $tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
            $sql  = "SELECT HOUR(hit_at) as hour_utc,
                            COUNT(DISTINCT visitor_hash) as visitors,
                            COUNT(*) as page_views,
                            COUNT(DISTINCT session_id) as sessions
                     FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0";
            $args = array( $from, $to );
            if ( ! empty( $params['device'] ) ) { $sql .= ' AND device_type = %s'; $args[] = $params['device']; }
            $sql .= ' GROUP BY HOUR(hit_at) ORDER BY hour_utc ASC';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

            $tz_hours = (int) round( $tz_offset_seconds / HOUR_IN_SECONDS );
            $indexed  = array();
            foreach ( $results as $row ) {
                $local_hour = ( (int) $row->hour_utc + $tz_hours + 24 ) % 24;
                $indexed[ $local_hour ] = $row;
            }

            $now_local_hour = (int) wp_date( 'G' );
            $filled = array();
            for ( $h = 0; $h <= 23; $h++ ) {
                $filled[] = array(
                    'hour'       => $h,
                    'visitors'   => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->visitors   : 0,
                    'page_views' => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->page_views : 0,
                    'sessions'   => isset( $indexed[ $h ] ) ? (int) $indexed[ $h ]->sessions   : 0,
                    'future'     => $h > $now_local_hour,
                );
            }
            return rest_ensure_response( $filled );
        }

        // Mode journalier
        $tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
        $tz_str  = sprintf( '%+03d:00', (int) round( $tz_offset_seconds / HOUR_IN_SECONDS ) );
        $sql  = "SELECT DATE(CONVERT_TZ(hit_at, '+00:00', %s)) as date,
                        COUNT(DISTINCT visitor_hash) as visitors,
                        COUNT(*) as page_views,
                        COUNT(DISTINCT session_id) as sessions
                 FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0";
        $args = array( $tz_str, $from, $to );
        if ( ! empty( $params['device'] ) ) { $sql .= ' AND device_type = %s'; $args[] = $params['device']; }
        $sql   .= " GROUP BY DATE(CONVERT_TZ(hit_at, '+00:00', %s)) ORDER BY date ASC";
        $args[] = $tz_str;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        $indexed = array();
        foreach ( $results as $row ) {
            $indexed[ $row->date ] = $row;
        }

        $filled  = array();
        $current = strtotime( $params['from'] );
        $end     = strtotime( $params['to'] );
        while ( $current <= $end ) {
            $date_str = gmdate( 'Y-m-d', $current );
            $filled[] = array(
                'date'       => $date_str,
                'visitors'   => isset( $indexed[ $date_str ] ) ? (int) $indexed[ $date_str ]->visitors   : 0,
                'page_views' => isset( $indexed[ $date_str ] ) ? (int) $indexed[ $date_str ]->page_views : 0,
                'sessions'   => isset( $indexed[ $date_str ] ) ? (int) $indexed[ $date_str ]->sessions   : 0,
            );
            $current += DAY_IN_SECONDS;
        }

        return rest_ensure_response( $filled );
    }

    // ── /top-pages ────────────────────────────────────────────────────────────

    public static function get_top_pages( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';
        $limit  = absint( $request->get_param( 'limit' ) ?: 20 );

        $sql  = "SELECT page_url, page_title, post_id,
                    COUNT(*) as views,
                    COUNT(DISTINCT visitor_hash) as unique_visitors
                FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0";
        $args = array( $params['from_utc'], $params['to_utc'] );
        if ( ! empty( $params['device'] ) )  { $sql .= ' AND device_type = %s';   $args[] = $params['device']; }
        if ( ! empty( $params['country'] ) ) { $sql .= ' AND country_code = %s';  $args[] = $params['country']; }
        $sql  .= ' GROUP BY page_url, page_title, post_id ORDER BY views DESC LIMIT %d';
        $args[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        return rest_ensure_response( $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) );
    }

    // ── /top-referrers ────────────────────────────────────────────────────────

    public static function get_top_referrers( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';
        $limit  = absint( $request->get_param( 'limit' ) ?: 20 );

        // Sessions avec référent
        $sql  = "SELECT referrer_domain,
                        COUNT(DISTINCT session_id)   as hits,
                        COUNT(DISTINCT visitor_hash) as unique_visitors
                 FROM {$table}
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND referrer_domain != ''";
        $args = array( $params['from_utc'], $params['to_utc'] );
        if ( ! empty( $params['device'] ) ) { $sql .= ' AND device_type = %s'; $args[] = $params['device']; }
        $sql   .= ' GROUP BY referrer_domain ORDER BY hits DESC LIMIT %d';
        $args[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        // Sessions sans référent (trafic direct)
        $sql_direct  = "SELECT COUNT(DISTINCT session_id) as hits, COUNT(DISTINCT visitor_hash) as unique_visitors
                        FROM {$table}
                        WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND (referrer_domain = '' OR referrer_domain IS NULL)";
        $args_direct = array( $params['from_utc'], $params['to_utc'] );
        if ( ! empty( $params['device'] ) ) { $sql_direct .= ' AND device_type = %s'; $args_direct[] = $params['device']; }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $direct = $wpdb->get_row( $wpdb->prepare( $sql_direct, $args_direct ) );
        if ( $direct && (int) $direct->hits > 0 ) {
            $rows[] = (object) array(
                'referrer_domain' => '',
                'hits'            => $direct->hits,
                'unique_visitors' => $direct->unique_visitors,
            );
        }

        return rest_ensure_response( $rows );
    }

    // ── /countries ────────────────────────────────────────────────────────────

    public static function get_countries( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';
        $limit  = absint( $request->get_param( 'limit' ) ?: 100 );

        $args  = array();
        $where = self::build_where( $params, $args );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                country_code,
                COUNT(*) as hits,
                COUNT(DISTINCT visitor_hash) as unique_visitors,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$table} WHERE {$where} AND is_superseded = 0), 1) as percentage
             FROM {$table}
             WHERE {$where} AND is_superseded = 0 AND country_code != ''
             GROUP BY country_code
             ORDER BY hits DESC
             LIMIT %d",
            ...array_merge( $args, $args, array( $limit ) )
        ) );

        return rest_ensure_response( $results );
    }

    // ── /devices ──────────────────────────────────────────────────────────────

    public static function get_devices( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params    = self::get_date_params( $request );
        $table     = $wpdb->prefix . 'aa_hits';
        $base_args = array( $params['from_utc'], $params['to_utc'] );

        // Toutes les métriques sont en sessions (COUNT DISTINCT session_id),
        // pas en pages vues — un visiteur qui charge 10 pages ne compte qu'une fois.

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $devices = $wpdb->get_results( $wpdb->prepare(
            "SELECT device_type, COUNT(DISTINCT session_id) as count
             FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             GROUP BY device_type ORDER BY count DESC",
            $base_args
        ) );

        // Navigateurs et OS globaux (onglet "Tous")
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $browsers = $wpdb->get_results( $wpdb->prepare(
            "SELECT browser, COUNT(DISTINCT session_id) as count
             FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND browser != ''
             GROUP BY browser ORDER BY count DESC LIMIT 10",
            $base_args
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $os_list = $wpdb->get_results( $wpdb->prepare(
            "SELECT os, COUNT(DISTINCT session_id) as count
             FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND os != ''
             GROUP BY os ORDER BY count DESC LIMIT 10",
            $base_args
        ) );

        // Navigateurs et OS par device_type (pour les onglets filtrés)
        $by_device = array();
        foreach ( array( 'desktop', 'mobile', 'tablet' ) as $dtype ) {
            $args_dt = array( $params['from_utc'], $params['to_utc'], $dtype );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $b = $wpdb->get_results( $wpdb->prepare(
                "SELECT browser, COUNT(DISTINCT session_id) as count
                 FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND device_type = %s AND browser != ''
                 GROUP BY browser ORDER BY count DESC LIMIT 10",
                $args_dt
            ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $o = $wpdb->get_results( $wpdb->prepare(
                "SELECT os, COUNT(DISTINCT session_id) as count
                 FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0 AND device_type = %s AND os != ''
                 GROUP BY os ORDER BY count DESC LIMIT 10",
                $args_dt
            ) );
            $by_device[ $dtype ] = array( 'browsers' => $b, 'os' => $o );
        }

        return rest_ensure_response( array(
            'devices'   => $devices,
            'browsers'  => $browsers,
            'os'        => $os_list,
            'by_device' => $by_device,
        ) );
    }

    // ── /visitors ─────────────────────────────────────────────────────────────

    public static function get_visitors( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(CASE WHEN is_new_visitor = 1 THEN 1 ELSE 0 END) as new_visitors,
                SUM(CASE WHEN is_new_visitor = 0 THEN 1 ELSE 0 END) as returning_visitors,
                SUM(CASE WHEN is_logged_in = 1 THEN 1 ELSE 0 END) as logged_in
             FROM {$table} WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
            $params['from_utc'],
            $params['to_utc']
        ) );

        return rest_ensure_response( $result );
    }

    // ── /recent-visitors ─────────────────────────────────────────────────────

    public static function get_recent_visitors( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params  = self::get_date_params( $request );
        $t_hits  = $wpdb->prefix . 'aa_hits';
        $t_sess  = $wpdb->prefix . 'aa_sessions';
        $limit   = absint( $request->get_param( 'limit' ) ?: 15 );

        // On regroupe par visitor_hash pour éviter les doublons dans la liste.
        // Pour chaque visiteur, on affiche la session la plus récente + les totaux agrégés.
        $args  = array( $params['from_utc'], $params['to_utc'] );
        $extra = '';
        if ( ! empty( $params['device'] ) )  { $extra .= ' AND h.device_type = %s';  $args[] = $params['device']; }
        if ( ! empty( $params['country'] ) ) { $extra .= ' AND h.country_code = %s'; $args[] = $params['country']; }
        $args[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                s.visitor_hash,
                MAX(s.ended_at)                         AS ended_at,
                MAX(s.started_at)                       AS last_started_at,
                SUM(CASE WHEN s.engagement_time > 0 THEN s.engagement_time ELSE s.duration END) AS total_duration,
                SUM(s.page_count)                       AS total_pages,
                COUNT(s.session_id)                     AS session_count,
                MAX(s.device_type)                      AS device_type,
                MAX(s.country_code)                     AS country_code,
                -- Session la plus récente (pour le lien Voir parcours)
                SUBSTRING_INDEX(GROUP_CONCAT(s.session_id ORDER BY s.ended_at DESC), ',', 1) AS last_session_id,
                -- Durée de la dernière session uniquement
                MAX(CASE WHEN s.ended_at = (SELECT MAX(s2.ended_at) FROM {$t_sess} s2 WHERE s2.visitor_hash = s.visitor_hash) THEN CASE WHEN s.engagement_time > 0 THEN s.engagement_time ELSE s.duration END END) AS last_duration,
                MAX(CASE WHEN s.ended_at = (SELECT MAX(s2.ended_at) FROM {$t_sess} s2 WHERE s2.visitor_hash = s.visitor_hash) THEN s.page_count END) AS last_page_count,
                -- Domaine référent du premier hit de la session la plus récente
                (SELECT h2.referrer_domain FROM {$t_hits} h2
                 INNER JOIN {$t_sess} s3 ON s3.session_id = h2.session_id
                 WHERE s3.visitor_hash = s.visitor_hash AND h2.is_superseded = 0 AND h2.referrer_domain != ''
                 ORDER BY s3.ended_at DESC, h2.hit_at ASC
                 LIMIT 1)                               AS last_referrer_domain
             FROM {$t_sess} s
             INNER JOIN (
                 SELECT DISTINCT session_id
                 FROM {$t_hits} h
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0{$extra}
             ) h ON h.session_id = s.session_id
             GROUP BY s.visitor_hash
             ORDER BY MAX(s.ended_at) DESC
             LIMIT %d",
            ...$args
        ) );

        return rest_ensure_response( $results );
    }

    // ── /export ───────────────────────────────────────────────────────────────

    public static function handle_export( $request ) {
        self::no_cache_headers();
        $params = self::get_date_params( $request );
        $format = sanitize_key( $request->get_param( 'format' ) ?: 'csv' );
        Always_Analytics_Export::download( $params, $format );
    }

    // ── /engagement ───────────────────────────────────────────────────────────

    public static function get_engagement( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params   = self::get_date_params( $request );
        $t_hits   = $wpdb->prefix . 'aa_hits';
        $t_sess   = $wpdb->prefix . 'aa_sessions';
        $t_scroll = $wpdb->prefix . 'aa_scroll';
        $from     = $params['from_utc'];
        $to       = $params['to_utc'];
        $is_today = $params['is_today'];

        // ── Vérifie quelles tables/colonnes existent ───────────────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_sess_table = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_scroll_table = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_scroll ) );

        $has_scroll_col = false;
        if ( $has_sess_table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $has_scroll_col = (bool) $wpdb->get_var( $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $t_sess, 'max_scroll_depth'
            ) );
        }

        // ── KPIs ───────────────────────────────────────────────────────────────
        // Source primaire : aa_hits (toujours alimentée si le tracker marche)
        // Source secondaire : aa_sessions (enrichit durée, bounce, scroll)
        if ( $has_sess_table && $has_scroll_col ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $kpis = $wpdb->get_row( $wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END))   AS avg_duration,
                    ROUND(AVG(CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE NULL END), 2) AS avg_pages,
                    ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END), 1) AS avg_scroll_depth,
                    COUNT(DISTINCT CASE WHEN s.max_scroll_depth >= 75 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS deep_readers
                 FROM {$t_hits} h
                 LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0",
                $from, $to
            ) );
        } elseif ( $has_sess_table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $kpis = $wpdb->get_row( $wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END))   AS avg_duration,
                    ROUND(AVG(CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE NULL END), 2) AS avg_pages,
                    0                                                                      AS avg_scroll_depth,
                    0                                                                      AS deep_readers
                 FROM {$t_hits} h
                 LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0",
                $from, $to
            ) );
        } else {
            // Pas de table sessions : on travaille uniquement sur hits
            // is_bounce estimé : 1 seule page vue dans la session = bounce
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $kpis = $wpdb->get_row( $wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT session_id)                                             AS total_sessions,
                    COUNT(DISTINCT CASE WHEN pv > 1 THEN session_id END)                  AS engaged_sessions,
                    0                                                                       AS avg_duration,
                    ROUND(AVG(pv), 2)                                                      AS avg_pages,
                    0                                                                       AS avg_scroll_depth,
                    0                                                                       AS deep_readers
                 FROM (
                     SELECT session_id, COUNT(*) AS pv
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY session_id
                 ) sub",
                $from, $to
            ) );
        }

        if ( ! $kpis ) {
            $kpis = (object) array(
                'total_sessions'   => 0, 'engaged_sessions' => 0,
                'avg_duration'     => 0, 'avg_pages'        => 0,
                'avg_scroll_depth' => 0, 'deep_readers'     => 0,
            );
        }

        $total           = max( 1, (int) $kpis->total_sessions );
        $engaged         = (int) $kpis->engaged_sessions;
        $deep            = (int) $kpis->deep_readers;
        $engagement_rate = round( $engaged / $total * 100, 1 );
        $deep_read_rate  = round( $deep   / $total * 100, 1 );

        // ── Distribution scroll 25/50/75/100 ──────────────────────────────────
        $scroll_map = array( 25 => 0, 50 => 0, 75 => 0, 100 => 0 );

        if ( $has_scroll_table ) {
            // On compte par PAGE VUE (hit), pas par session.
            // Pour chaque couple (session_id, page_url), on prend le MAX scroll atteint
            // puis on le regroupe en tranche exclusive (25/50/75/100).
            // Un visiteur qui a lu 3 pages différentes jusqu'à des profondeurs différentes
            // contribue 3 pages vues, chacune dans sa propre tranche.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $scroll_dist = $wpdb->get_results( $wpdb->prepare(
                "SELECT mx.max_depth AS scroll_depth, COUNT(*) AS page_views
                 FROM (
                     SELECT sr.session_id,
                            REGEXP_REPLACE(sr.page_url, '\\?.*\$', '') AS clean_url,
                            MAX(sr.scroll_depth) AS max_depth
                     FROM {$t_scroll} sr
                     INNER JOIN {$t_sess} s ON s.session_id = sr.session_id
                     WHERE sr.session_id IN (
                         SELECT DISTINCT session_id FROM {$t_hits}
                         WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     ) AND (s.duration > 0 OR s.engagement_time > 0)
                     GROUP BY sr.session_id, REGEXP_REPLACE(sr.page_url, '\\?.*\$', '')
                 ) mx
                 WHERE mx.max_depth IN (25, 50, 75, 100)
                 GROUP BY mx.max_depth",
                $from, $to
            ) );
            foreach ( $scroll_dist as $row ) {
                $d = (int) $row->scroll_depth;
                if ( isset( $scroll_map[$d] ) ) $scroll_map[$d] = (int) $row->page_views;
            }
        }

        // Fallback : depuis max_scroll_depth des sessions — filtre via hits (hit_at + is_superseded = 0)
        if ( $has_sess_table && $has_scroll_col && array_sum( $scroll_map ) === 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            // Fallback : max_scroll_depth sur la session — on multiplie par page_count
            // pour approximer le nombre de pages vues par tranche (faute de détail par page).
            $r = $wpdb->get_row( $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN s.max_scroll_depth >= 25  AND s.max_scroll_depth < 50  AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s25,
                    SUM(CASE WHEN s.max_scroll_depth >= 50  AND s.max_scroll_depth < 75  AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s50,
                    SUM(CASE WHEN s.max_scroll_depth >= 75  AND s.max_scroll_depth < 100 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s75,
                    SUM(CASE WHEN s.max_scroll_depth >= 100                              AND (s.duration > 0 OR s.engagement_time > 0) THEN s.page_count ELSE 0 END) AS s100
                 FROM {$t_sess} s
                 WHERE s.session_id IN (
                     SELECT DISTINCT session_id FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                 )",
                $from, $to
            ) );
            if ( $r ) {
                $scroll_map[25]  = (int) $r->s25;
                $scroll_map[50]  = (int) $r->s50;
                $scroll_map[75]  = (int) $r->s75;
                $scroll_map[100] = (int) $r->s100;
            }
        }

        // ── Graphique temporel ─────────────────────────────────────────────────
        $tz_off_sec = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
        $tz_str     = sprintf( '%+03d:00', (int) round( $tz_off_sec / HOUR_IN_SECONDS ) );
        $chart      = array();

        // Colonne scroll selon disponibilité
        $scroll_col = $has_sess_table && $has_scroll_col
            ? 'ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END),1)'
            : '0';
        // Colonne durée
        $dur_col = $has_sess_table
            ? 'ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END))'
            : '0';
        // Colonne engaged
        $eng_col = $has_sess_table
            ? 'COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END)'
            : 'COUNT(DISTINCT CASE WHEN pv.pv > 1 THEN h.session_id END)';

        if ( $is_today ) {
            if ( $has_sess_table ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $chart_raw = $wpdb->get_results( $wpdb->prepare(
                    "SELECT HOUR(h.hit_at) AS hour_utc,
                            COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS sessions,
                            {$eng_col} AS engaged,
                            {$dur_col} AS avg_dur,
                            {$scroll_col} AS avg_scroll
                     FROM {$t_hits} h
                     LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                     WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                     GROUP BY HOUR(h.hit_at) ORDER BY hour_utc ASC",
                    $from, $to
                ) );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $chart_raw = $wpdb->get_results( $wpdb->prepare(
                    "SELECT HOUR(hit_at) AS hour_utc,
                            COUNT(DISTINCT session_id) AS sessions,
                            0 AS engaged, 0 AS avg_dur, 0 AS avg_scroll
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY HOUR(hit_at) ORDER BY hour_utc ASC",
                    $from, $to
                ) );
            }

            $tz_h    = (int) round( $tz_off_sec / HOUR_IN_SECONDS );
            $indexed = array();
            foreach ( $chart_raw as $row ) {
                $lh = ( (int) $row->hour_utc + $tz_h + 24 ) % 24;
                $indexed[$lh] = $row;
            }
            $now_h = (int) wp_date( 'G' );
            for ( $h = 0; $h <= 23; $h++ ) {
                $chart[] = array(
                    'label'      => $h . 'h',
                    'sessions'   => isset( $indexed[$h] ) ? (int)   $indexed[$h]->sessions   : 0,
                    'engaged'    => isset( $indexed[$h] ) ? (int)   $indexed[$h]->engaged     : 0,
                    'avg_dur'    => isset( $indexed[$h] ) ? (int)   $indexed[$h]->avg_dur     : 0,
                    'avg_scroll' => isset( $indexed[$h] ) ? (float) $indexed[$h]->avg_scroll  : 0,
                    'future'     => $h > $now_h,
                );
            }
        } else {
            if ( $has_sess_table ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $chart_raw = $wpdb->get_results( $wpdb->prepare(
                    "SELECT DATE(CONVERT_TZ(h.hit_at, '+00:00', %s)) AS date,
                            COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS sessions,
                            {$eng_col} AS engaged,
                            {$dur_col} AS avg_dur,
                            {$scroll_col} AS avg_scroll
                     FROM {$t_hits} h
                     LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                     WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                     GROUP BY DATE(CONVERT_TZ(h.hit_at, '+00:00', %s))
                     ORDER BY date ASC",
                    $tz_str, $from, $to, $tz_str
                ) );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $chart_raw = $wpdb->get_results( $wpdb->prepare(
                    "SELECT DATE(CONVERT_TZ(hit_at, '+00:00', %s)) AS date,
                            COUNT(DISTINCT session_id) AS sessions,
                            0 AS engaged, 0 AS avg_dur, 0 AS avg_scroll
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY DATE(CONVERT_TZ(hit_at, '+00:00', %s))
                     ORDER BY date ASC",
                    $tz_str, $from, $to, $tz_str
                ) );
            }

            $indexed = array();
            foreach ( $chart_raw as $row ) { $indexed[ $row->date ] = $row; }
            $cur = strtotime( $params['from'] );
            $end = strtotime( $params['to'] );
            while ( $cur <= $end ) {
                $ds      = gmdate( 'Y-m-d', $cur );
                $dt      = new \DateTime( $ds );
                $chart[] = array(
                    'label'      => $dt->format( 'd M' ),
                    'sessions'   => isset( $indexed[$ds] ) ? (int)   $indexed[$ds]->sessions   : 0,
                    'engaged'    => isset( $indexed[$ds] ) ? (int)   $indexed[$ds]->engaged     : 0,
                    'avg_dur'    => isset( $indexed[$ds] ) ? (int)   $indexed[$ds]->avg_dur     : 0,
                    'avg_scroll' => isset( $indexed[$ds] ) ? (float) $indexed[$ds]->avg_scroll  : 0,
                );
                $cur += DAY_IN_SECONDS;
            }
        }

        return rest_ensure_response( array(
            'kpis' => array(
                'total_sessions'   => (int)   $kpis->total_sessions,
                'engaged_sessions' => $engaged,
                'engagement_rate'  => $engagement_rate,
                'avg_duration'     => (int)   ($kpis->avg_duration    ?? 0),
                'avg_pages'        => (float) ($kpis->avg_pages       ?? 0),
                'avg_scroll_depth' => (float) ($kpis->avg_scroll_depth ?? 0),
                'deep_read_rate'   => $deep_read_rate,
            ),
            'scroll_distribution' => $scroll_map,
            'chart'               => $chart,
        ) );
    }

    // ── /engagement/pages ─────────────────────────────────────────────────────

    public static function get_engagement_pages( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params   = self::get_date_params( $request );
        $t_hits   = $wpdb->prefix . 'aa_hits';
        $t_sess   = $wpdb->prefix . 'aa_sessions';
        $t_scroll = $wpdb->prefix . 'aa_scroll';
        $from     = $params['from_utc'];
        $to       = $params['to_utc'];
        $limit    = absint( $request->get_param( 'limit' ) ?: 50 );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_sess   = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_sess ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_scroll = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_scroll ) );

        $has_scroll_col = false;
        if ( $has_sess ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $has_scroll_col = (bool) $wpdb->get_var( $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $t_sess, 'max_scroll_depth'
            ) );
        }

        // ── Clé de regroupement : post_id quand dispo, sinon URL sans query string ──
        // Cela fusionne automatiquement /page/?index=1, /page/?index=2, etc.
        $group_key = "CASE WHEN h.post_id > 0 THEN CONCAT('pid:', h.post_id)
                           ELSE REGEXP_REPLACE(h.page_url, '\\\\?.*$', '')
                      END";

        if ( $has_sess && $has_scroll ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    -- URL canonique : sans paramètres, ou reconstruite depuis post_id
                    CASE WHEN h.post_id > 0 THEN MIN(REGEXP_REPLACE(h.page_url, '\\\\?.*$', ''))
                         ELSE REGEXP_REPLACE(h.page_url, '\\\\?.*$', '')
                    END                             AS page_url,
                    MAX(h.page_title)               AS page_title,
                    MAX(h.post_id)                  AS post_id,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(*)                        AS page_views,
                    COUNT(DISTINCT h.visitor_hash)  AS unique_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END)), 0) AS avg_duration,
                    COALESCE(ROUND(AVG(CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN sc.max_scroll ELSE NULL END),1),
                             ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END),1),
                             0)                     AS avg_scroll,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS measurable_sessions,
                    COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN REGEXP_REPLACE(h.page_url,'\\\\?.*$','') = REGEXP_REPLACE(s.entry_page,'\\\\?.*$','') AND s.page_count > 0 THEN s.page_count ELSE NULL END), 2), 0) AS avg_depth_as_entry,
                    COUNT(DISTINCT CASE WHEN REGEXP_REPLACE(h.page_url,'\\\\?.*$','') = REGEXP_REPLACE(s.entry_page,'\\\\?.*$','') THEN h.session_id END) AS entry_sessions
                 FROM {$t_hits} h
                 LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                 LEFT JOIN (
                     SELECT session_id, REGEXP_REPLACE(page_url,'\\\\?.*$','') AS clean_url, MAX(scroll_depth) AS max_scroll
                     FROM {$t_scroll}
                     WHERE session_id IN (
                         SELECT DISTINCT session_id FROM {$t_hits}
                         WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     )
                     GROUP BY session_id, clean_url
                 ) sc ON sc.session_id = h.session_id AND sc.clean_url = REGEXP_REPLACE(h.page_url,'\\\\?.*$','')
                 LEFT JOIN (
                     SELECT visitor_hash,
                            CASE WHEN post_id > 0 THEN CONCAT('pid:', post_id) ELSE REGEXP_REPLACE(page_url,'\\\\?.*$','') END AS grp_key,
                            COUNT(DISTINCT session_id) AS visit_count
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY visitor_hash, grp_key
                 ) rv ON rv.visitor_hash = h.visitor_hash
                     AND rv.grp_key = {$group_key}
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                 GROUP BY {$group_key}",
                $from, $to, $from, $to, $from, $to
            ) );
        } elseif ( $has_sess && $has_scroll_col ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    CASE WHEN h.post_id > 0 THEN MIN(REGEXP_REPLACE(h.page_url, '\\\\?.*$', ''))
                         ELSE REGEXP_REPLACE(h.page_url, '\\\\?.*$', '')
                    END                             AS page_url,
                    MAX(h.page_title)               AS page_title,
                    MAX(h.post_id)                  AS post_id,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS total_sessions,
                    COUNT(*)                        AS page_views,
                    COUNT(DISTINCT h.visitor_hash)  AS unique_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time WHEN s.duration > 0 THEN s.duration ELSE NULL END)), 0) AS avg_duration,
                    COALESCE(ROUND(AVG(CASE WHEN s.max_scroll_depth > 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN s.max_scroll_depth ELSE NULL END), 1), 0) AS avg_scroll,
                    COUNT(DISTINCT CASE WHEN s.is_bounce = 0 AND (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS engaged_sessions,
                    COUNT(DISTINCT CASE WHEN (s.duration > 0 OR s.engagement_time > 0) THEN h.session_id END) AS measurable_sessions,
                    COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN REGEXP_REPLACE(h.page_url,'\\\\?.*$','') = REGEXP_REPLACE(s.entry_page,'\\\\?.*$','') AND s.page_count > 0 THEN s.page_count ELSE NULL END), 2), 0) AS avg_depth_as_entry,
                    COUNT(DISTINCT CASE WHEN REGEXP_REPLACE(h.page_url,'\\\\?.*$','') = REGEXP_REPLACE(s.entry_page,'\\\\?.*$','') THEN h.session_id END) AS entry_sessions
                 FROM {$t_hits} h
                 LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                 LEFT JOIN (
                     SELECT visitor_hash,
                            CASE WHEN post_id > 0 THEN CONCAT('pid:', post_id) ELSE REGEXP_REPLACE(page_url,'\\\\?.*$','') END AS grp_key,
                            COUNT(DISTINCT session_id) AS visit_count
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY visitor_hash, grp_key
                 ) rv ON rv.visitor_hash = h.visitor_hash
                     AND rv.grp_key = {$group_key}
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                 GROUP BY {$group_key}",
                $from, $to, $from, $to
            ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    CASE WHEN h.post_id > 0 THEN MIN(REGEXP_REPLACE(h.page_url, '\\\\?.*$', ''))
                         ELSE REGEXP_REPLACE(h.page_url, '\\\\?.*$', '')
                    END                             AS page_url,
                    MAX(h.page_title)               AS page_title,
                    MAX(h.post_id)                  AS post_id,
                    COUNT(DISTINCT h.session_id)    AS total_sessions,
                    COUNT(*)                        AS page_views,
                    COUNT(DISTINCT h.visitor_hash)  AS unique_visitors,
                    0                               AS avg_duration,
                    0                               AS avg_scroll,
                    COUNT(DISTINCT CASE WHEN pv.pv > 1 THEN h.session_id END) AS engaged_sessions,
                    COUNT(DISTINCT h.session_id)    AS measurable_sessions,
                    COUNT(DISTINCT CASE WHEN rv.visit_count > 1 THEN h.visitor_hash END) AS returning_visitors,
                    COALESCE(ROUND(AVG(CASE WHEN pv.pv > 0 THEN pv.pv ELSE NULL END), 2), 0) AS avg_depth_as_entry,
                    COUNT(DISTINCT h.session_id)    AS entry_sessions
                 FROM {$t_hits} h
                 LEFT JOIN (
                     SELECT session_id, COUNT(*) AS pv
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY session_id
                 ) pv ON pv.session_id = h.session_id
                 LEFT JOIN (
                     SELECT visitor_hash,
                            CASE WHEN post_id > 0 THEN CONCAT('pid:', post_id) ELSE REGEXP_REPLACE(page_url,'\\\\?.*$','') END AS grp_key,
                            COUNT(DISTINCT session_id) AS visit_count
                     FROM {$t_hits}
                     WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                     GROUP BY visitor_hash, grp_key
                 ) rv ON rv.visitor_hash = h.visitor_hash
                     AND rv.grp_key = {$group_key}
                 WHERE h.hit_at >= %s AND h.hit_at <= %s AND h.is_superseded = 0
                 GROUP BY {$group_key}",
                $from, $to, $from, $to, $from, $to
            ) );
        }

        if ( empty( $rows ) ) {
            return rest_ensure_response( array() );
        }

        // ── Calcul du score composite en PHP ────────────────────────────────────
        //
        // 5 signaux indépendants, normalisés 0→1, combinés avec des poids calibrés :
        //
        //  1. Durée relative    ~23.9% — durée de la page VS médiane du site
        //  2. Scroll depth      ~21.7% — profondeur de lecture effective
        //  3. Taux engagement   ~21.7% — sessions non-bounce
        //  4. Taux de retour    ~19.6% — visiteurs revenant sur la même page
        //  5. Depth/session     ~13.0% — pages vues dans la session (entry point)
        //
        // La correction d'incertitude (Wilson lower bound, z=1.96) s'applique sur
        // P_brut (synthèse pondérée des 5 signaux) avec n = sessions totales de la page.
        // Une page avec peu de données est pénalisée globalement sur l'ensemble du score.
        //
        // Poids renormalisés sur les 5 signaux (anciens 22+20+20+18+12 = 92%) :
        // w1 = 22/92 ≈ 0.2391, w2 = 20/92 ≈ 0.2174, w3 = 20/92 ≈ 0.2174
        // w4 = 18/92 ≈ 0.1957, w5 = 12/92 ≈ 0.1304

        $durations = array_filter( array_map( function( $r ) { return (float) $r->avg_duration; }, $rows ), function( $d ) { return $d > 0; } );
        sort( $durations );
        $count_dur  = count( $durations );
        $median_dur = $count_dur > 0
            ? ( $count_dur % 2 === 0
                ? ( $durations[ $count_dur / 2 - 1 ] + $durations[ $count_dur / 2 ] ) / 2
                : $durations[ (int) ( $count_dur / 2 ) ] )
            : 120;
        $median_dur = max( 10, $median_dur );

        $z  = 1.96;
        $z2 = $z * $z;

        // Poids renormalisés (5 signaux, somme = 1.0)
        $w_duration   = 22 / 92;
        $w_scroll     = 20 / 92;
        $w_engagement = 20 / 92;
        $w_return     = 18 / 92;
        $w_depth      = 12 / 92;

        $scored = array();
        foreach ( $rows as $row ) {
            $n_total      = max( 1, (int) $row->total_sessions );
            $n_measurable = max( 0, (int) $row->measurable_sessions );
            $n_engaged    = max( 0, (int) $row->engaged_sessions );
            $n_returning  = max( 0, (int) $row->returning_visitors );
            $n_unique     = max( 1, (int) $row->unique_visitors );
            $n_entry      = max( 0, (int) $row->entry_sessions );
            $avg_dur      = max( 0, (float) $row->avg_duration );
            $avg_scroll   = max( 0, min( 100, (float) $row->avg_scroll ) );
            $avg_depth    = max( 0, (float) $row->avg_depth_as_entry );

            // Signal 1 : Durée relative (plafonnée à 2× la médiane)
            $s_duration = $avg_dur > 0 ? min( 1.0, $avg_dur / ( 2.0 * $median_dur ) ) : 0.0;

            // Signal 2 : Scroll depth
            $s_scroll = $avg_scroll / 100.0;

            // Signal 3 : Taux d'engagement brut (Wilson s'applique sur P global, pas ici)
            $s_engagement = $n_measurable > 0 ? min( 1.0, $n_engaged / $n_measurable ) : 0.0;

            // Signal 4 : Taux de retour
            $s_return = min( 1.0, ( $n_returning / $n_unique ) / 0.5 );

            // Signal 5 : Profondeur de navigation (entry point)
            $s_depth = ( $n_entry > 0 && $avg_depth > 0 )
                ? min( 1.0, max( 0.0, ( $avg_depth - 1.0 ) / 4.0 ) )
                : 0.0;

            // Performance brute : moyenne pondérée des 5 signaux ∈ [0, 1]
            $p_brut = $s_duration   * $w_duration
                    + $s_scroll     * $w_scroll
                    + $s_engagement * $w_engagement
                    + $s_return     * $w_return
                    + $s_depth      * $w_depth;

            // Wilson lower bound sur P_brut avec n = sessions totales.
            // Pour n → ∞, S_final → P_brut.
            // Pour n petit, S_final << P_brut : la page est pénalisée globalement.
            // La formule est valide pour P ∈ [0,1] et n ≥ 1.
            $n = $n_total;
            $wilson_final = ( $p_brut + $z2 / ( 2 * $n )
                - $z * sqrt( $p_brut * ( 1 - $p_brut ) / $n + $z2 / ( 4 * $n * $n ) ) )
                / ( 1 + $z2 / $n );

            $score = round( min( 100, max( 0, $wilson_final * 100 ) ), 1 );

            // Facteur de confiance : ratio S_final / P_brut, exprimé en %
            // 100% = aucune pénalité (n élevé), <100% = pénalisé par manque de données
            $confidence_factor = $p_brut > 0 ? round( $wilson_final / $p_brut * 100, 1 ) : 0;

            $eng_rate_pct = $n_measurable > 0 ? round( $n_engaged / $n_measurable * 100 ) : null;

            $scored[] = array(
                'page_url'           => $row->page_url,
                'page_title'         => $row->page_title,
                'post_id'            => (int) $row->post_id,
                'total_sessions'     => $n_total,
                'page_views'         => (int) $row->page_views,
                'unique_visitors'    => $n_unique,
                'avg_duration'       => $avg_dur,
                'avg_scroll'         => $avg_scroll,
                'engaged_sessions'   => $n_engaged,
                'returning_visitors' => $n_returning,
                'avg_depth_as_entry' => round( $avg_depth, 1 ),
                'entry_sessions'     => $n_entry,
                'engagement_score'   => $score,
                'score_signals'      => array(
                    'duration'   => array( 'score' => round( $s_duration   * 100, 1 ), 'raw' => round( $avg_dur ) ),
                    'scroll'     => array( 'score' => round( $s_scroll     * 100, 1 ), 'raw' => round( $avg_scroll ) ),
                    'engagement' => array( 'score' => round( $s_engagement * 100, 1 ), 'raw' => $eng_rate_pct ),
                    'return'     => array( 'score' => round( $s_return     * 100, 1 ), 'raw' => round( $n_returning / $n_unique * 100 ) ),
                    'depth'      => array( 'score' => round( $s_depth      * 100, 1 ), 'raw' => round( $avg_depth, 1 ) ),
                    // Confiance = facteur Wilson : jusqu'à quel point P_brut est retenu.
                    // Exemple : 73% signifie que Wilson a réduit le score de 27% par manque de données.
                    'confidence' => array( 'score' => $confidence_factor, 'raw' => $n_total ),
                ),
            );
        }

        usort( $scored, function( $a, $b ) {
            if ( $b['engagement_score'] !== $a['engagement_score'] ) {
                return $b['engagement_score'] <=> $a['engagement_score'];
            }
            return $b['total_sessions'] <=> $a['total_sessions'];
        } );

        return rest_ensure_response( array_slice( $scored, 0, $limit ) );
    }

    // ── /hit-sources ──────────────────────────────────────────────────────────

    /**
     * Retourne la distribution des sources de tracking (hit_source) pour la période.
     *
     * Sources possibles :
     *   js              — hit JavaScript normal (mode cookieless ou cookie sans bannière)
     *   js_cookieless   — hit JS en mode cookie mais sans visitorId (fallback cookieless auto)
     *   pre_consent     — hit JS envoyé avant réponse à la bannière (mode cookie+consent)
     *                     Note : ces hits sont is_superseded=1 s'ils ont été fusionnés.
     *                     On les compte TOUS (superseded ou non) pour montrer la réalité du trafic.
     *   noscript        — pixel <noscript> (visiteur sans JavaScript)
     *   cookie          — hit JS avec cookie visitorId actif (si hit_source='js' en mode cookie)
     *
     * Le champ `is_superseded` est exclu des comptages principaux (comme partout dans le plugin)
     * sauf pour les pre_consent dont on veut la réalité complète.
     */
    public static function get_hit_sources( $request ) {
        global $wpdb;
        self::no_cache_headers();

        $params = self::get_date_params( $request );
        $table  = $wpdb->prefix . 'aa_hits';
        $from   = $params['from_utc'];
        $to     = $params['to_utc'];

        // ── 1. Distribution par hit_source ────────────────────────────────────
        // On inclut is_superseded=1 (hits pre_consent fusionnés) pour avoir la
        // vraie réalité de la collecte — avec un flag `superseded` pour les distinguer.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                hit_source,
                COUNT(*)                        AS hits,
                COUNT(DISTINCT visitor_hash)    AS unique_visitors,
                COUNT(DISTINCT session_id)      AS sessions,
                SUM(is_superseded)              AS superseded_count
             FROM {$table}
             WHERE hit_at >= %s AND hit_at <= %s
             GROUP BY hit_source
             ORDER BY hits DESC",
            $from, $to
        ) );

        // ── 2. Total général (is_superseded=0 uniquement = même base que le reste du dashboard)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $totals = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*)                     AS total_hits,
                COUNT(DISTINCT visitor_hash) AS total_uv,
                COUNT(DISTINCT session_id)   AS total_sessions
             FROM {$table}
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0",
            $from, $to
        ) );

        // ── 3. Tendance temporelle par source (pour mini-sparkline) ────────────
        // Agrégation journalière si plage > 1 jour, horaire sinon.
        $tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
        $tz_str = sprintf( '%+03d:00', (int) round( $tz_offset_seconds / HOUR_IN_SECONDS ) );
        $is_today = $params['is_today'];

        if ( $is_today ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $trend = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    hit_source,
                    HOUR(hit_at) AS period,
                    COUNT(*) AS hits
                 FROM {$table}
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                 GROUP BY hit_source, HOUR(hit_at)
                 ORDER BY hit_source, period ASC",
                $from, $to
            ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $trend = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    hit_source,
                    DATE(CONVERT_TZ(hit_at, '+00:00', %s)) AS period,
                    COUNT(*) AS hits
                 FROM {$table}
                 WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
                 GROUP BY hit_source, DATE(CONVERT_TZ(hit_at, '+00:00', %s))
                 ORDER BY hit_source, period ASC",
                $tz_str, $from, $to, $tz_str
            ) );
        }

        // ── 4. Taux de nouveaux visiteurs par source ──────────────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $new_vis = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                hit_source,
                SUM(is_new_visitor)                                              AS new_visitors,
                COUNT(DISTINCT CASE WHEN is_new_visitor = 1 THEN visitor_hash END) AS new_uv
             FROM {$table}
             WHERE hit_at >= %s AND hit_at <= %s AND is_superseded = 0
             GROUP BY hit_source",
            $from, $to
        ) );

        // ── 5. Distribution temporelle (pour le graphique global) ─────────────
        // Nb de hits par source, groupés par heure/jour.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $timeline_sources = array( 'js', 'js_cookieless', 'pre_consent', 'noscript' );

        // ── 6. Assemblage de la réponse ───────────────────────────────────────
        $total_hits = $totals ? (int) $totals->total_hits : 1;

        // Index par source
        $new_vis_idx = array();
        foreach ( $new_vis as $nv ) {
            $new_vis_idx[ $nv->hit_source ] = (int) $nv->new_visitors;
        }

        // Trend indexé par source
        $trend_idx = array();
        foreach ( $trend as $t ) {
            $src = $t->hit_source;
            if ( ! isset( $trend_idx[ $src ] ) ) $trend_idx[ $src ] = array();
            $trend_idx[ $src ][] = array(
                'period' => $t->period,
                'hits'   => (int) $t->hits,
            );
        }

        // Libellés et descriptions des sources
        $source_meta = array(
            'js'             => array(
                'label'       => 'JavaScript',
                'description' => 'Hits collectés par le tracker JS (mode cookieless ou cookie actif)',
                'color'       => '#6c63ff',
                'icon'        => 'js',
            ),
            'js_cookieless'  => array(
                'label'       => 'JS sans cookie (fallback)',
                'description' => 'Mode cookie configuré mais cookie bloqué (bloqueur de pub, Safari ITP…) → fallback cookieless automatique',
                'color'       => '#a78bfa',
                'icon'        => 'fallback',
            ),
            'pre_consent'    => array(
                'label'       => 'Pré-consentement',
                'description' => 'Hits cookieless envoyés avant que le visiteur réponde à la bannière. Les hits fusionnés (acceptation) sont marqués superseded.',
                'color'       => '#f59e0b',
                'icon'        => 'consent',
            ),
            'noscript'       => array(
                'label'       => 'Pixel noscript',
                'description' => 'Visiteurs sans JavaScript — collecte via pixel <img> 1×1 injecté dans <noscript>',
                'color'       => '#10b981',
                'icon'        => 'noscript',
            ),
            'cookie'         => array(
                'label'       => 'Cookie',
                'description' => 'Hits avec visitorId cookie actif et consentement accordé',
                'color'       => '#3b82f6',
                'icon'        => 'cookie',
            ),
        );

        $sources = array();
        foreach ( $rows as $row ) {
            $src   = $row->hit_source ?: 'js'; // fallback si NULL
            $hits  = (int) $row->hits;
            $meta  = isset( $source_meta[ $src ] ) ? $source_meta[ $src ] : array(
                'label' => $src, 'description' => '', 'color' => '#94a3b8', 'icon' => 'unknown',
            );

            $sources[] = array(
                'source'           => $src,
                'label'            => $meta['label'],
                'description'      => $meta['description'],
                'color'            => $meta['color'],
                'icon'             => $meta['icon'],
                'hits'             => $hits,
                'unique_visitors'  => (int) $row->unique_visitors,
                'sessions'         => (int) $row->sessions,
                'superseded_count' => (int) $row->superseded_count,
                'new_visitors'     => isset( $new_vis_idx[ $src ] ) ? $new_vis_idx[ $src ] : 0,
                'pct_of_total'     => $total_hits > 0 ? round( $hits / $total_hits * 100, 1 ) : 0,
                'trend'            => isset( $trend_idx[ $src ] ) ? $trend_idx[ $src ] : array(),
            );
        }

        return rest_ensure_response( array(
            'sources'       => $sources,
            'total_hits'    => (int) ( $totals ? $totals->total_hits    : 0 ),
            'total_uv'      => (int) ( $totals ? $totals->total_uv      : 0 ),
            'total_sessions'=> (int) ( $totals ? $totals->total_sessions: 0 ),
            'is_today'      => $is_today,
        ) );
    }

    // ── Campaigns ─────────────────────────────────────────────────────────────

    /**
     * GET /campaigns?from=&to=
     * Returns all campaigns. Optionally filtered by date range (any campaign
     * whose event_date falls in [from, to] is returned, but we always return
     * all so the chart can show them on any range).
     */
    public static function get_campaigns( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_campaigns';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            return rest_ensure_response( array() );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            "SELECT id, event_date, label, description, color, created_at FROM {$table} ORDER BY event_date ASC",
            ARRAY_A
        );

        return rest_ensure_response( $rows ? $rows : array() );
    }

    /**
     * POST /campaigns
     * Body (JSON): { event_date, label, description, color }
     */
    public static function create_campaign( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_campaigns';

        // Créer la table si elle n'existe pas encore (migration non encore jouée)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_date      DATE            NOT NULL,
                label           VARCHAR(255)    NOT NULL,
                description     TEXT            DEFAULT '',
                color           VARCHAR(7)      DEFAULT '#6c63ff',
                created_at      DATETIME        NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_event_date (event_date)
            ) {$charset_collate};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }

        $body        = $request->get_json_params();
        $event_date  = isset( $body['event_date'] )  ? sanitize_text_field( $body['event_date'] )  : '';
        $label       = isset( $body['label'] )        ? sanitize_text_field( $body['label'] )        : '';
        $description = isset( $body['description'] )  ? sanitize_textarea_field( $body['description'] ) : '';
        $color       = isset( $body['color'] )        ? sanitize_hex_color( $body['color'] )         : '#6c63ff';

        // Validate date
        if ( empty( $event_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
            return new \WP_Error( 'invalid_date', 'Date invalide.', array( 'status' => 400 ) );
        }
        if ( empty( $label ) ) {
            return new \WP_Error( 'invalid_label', 'Label requis.', array( 'status' => 400 ) );
        }
        // Max 1 campaign per day security
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_date = %s",
            $event_date
        ) );
        if ( (int) $existing >= 1 ) {
            return new \WP_Error( 'date_taken', 'Un événement existe déjà pour cette date.', array( 'status' => 409 ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->insert( $table, array(
            'event_date'  => $event_date,
            'label'       => $label,
            'description' => $description,
            'color'       => $color ?: '#6c63ff',
            'created_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s' ) );

        if ( ! $inserted ) {
            return new \WP_Error( 'db_error', 'Erreur lors de la création.', array( 'status' => 500 ) );
        }

        return rest_ensure_response( array(
            'id'         => $wpdb->insert_id,
            'event_date' => $event_date,
            'label'      => $label,
            'description'=> $description,
            'color'      => $color ?: '#6c63ff',
        ) );
    }

    /**
     * DELETE /campaigns/{id}
     */
    public static function delete_campaign( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_campaigns';
        $id    = (int) $request->get_param( 'id' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        if ( ! $deleted ) {
            return new \WP_Error( 'not_found', 'Événement introuvable.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( array( 'deleted' => true ) );
    }

}
