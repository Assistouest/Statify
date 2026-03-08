<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Moteur de tracking — reçoit les hits REST et les insère en DB.
 */
class Statify_Tracker {

    public static function track( $data ) {
        global $wpdb;

        $options = get_option( 'statify_options', array() );

        // ── IP ─────────────────────────────────────────────────────────────────
        $ip = self::get_client_ip();
        if ( self::is_excluded_ip( $ip, $options ) ) {
            return false;
        }

        // ── User-Agent & bot filter ────────────────────────────────────────────
        $ua_string = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
            : '';

        $bot_mode = isset( $options['bot_filter_mode'] ) ? $options['bot_filter_mode'] : 'normal';
        if ( 'off' !== $bot_mode && Statify_Bot_Filter::is_bot( $ua_string, $bot_mode ) ) {
            return false;
        }

        // ── IP anonymisation ───────────────────────────────────────────────────
        $anonymize  = ! empty( $options['anonymize_ip'] );
        $ip_for_geo = $ip;
        if ( $anonymize ) {
            $ip = Statify_Privacy::anonymize_ip( $ip );
        }

        // ── Device info ────────────────────────────────────────────────────────
        $device_info = self::parse_user_agent( $ua_string );

        // ── Géolocalisation ────────────────────────────────────────────────────
        $geo = array( 'country_code' => '', 'region' => '', 'city' => '' );
        if ( ! empty( $options['geo_enabled'] ) ) {
            $geo_provider = isset( $options['geo_provider'] ) ? $options['geo_provider'] : 'native';
            $geolocation  = new Statify_Geolocation( $geo_provider, $options );
            $geo          = $geolocation->lookup( $anonymize ? $ip : $ip_for_geo );
        }

        // ── Visitor hash ───────────────────────────────────────────────────────
        $tracking_mode = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
        $visitor_hash  = self::generate_visitor_hash( $ip, $ua_string, $tracking_mode, $data );

        if ( empty( $visitor_hash ) ) {
            return false;
        }

        // ── Nouveau visiteur ? ─────────────────────────────────────────────────
        $is_new = self::is_new_visitor( $visitor_hash, $tracking_mode );

        // ── Post ID / type ─────────────────────────────────────────────────────
        $post_id   = isset( $data['postId'] ) ? absint( $data['postId'] ) : 0;
        $post_type = '';
        if ( $post_id > 0 ) {
            $post_type = get_post_type( $post_id );
            if ( false === $post_type ) $post_type = '';
        }

        // ── Référent ───────────────────────────────────────────────────────────
        $referrer        = isset( $data['referrer'] ) ? esc_url_raw( $data['referrer'] ) : '';
        $referrer_domain = '';
        if ( $referrer ) {
            $parsed          = wp_parse_url( $referrer );
            $referrer_domain = isset( $parsed['host'] ) ? sanitize_text_field( $parsed['host'] ) : '';

            // Ignore internal referrers (navigations within the same site).
            // Compare against the site host, stripping any leading 'www.' for robustness.
            $site_host      = wp_parse_url( home_url(), PHP_URL_HOST );
            $strip_www      = function( $host ) { return preg_replace( '/^www\./i', '', $host ); };
            if ( $referrer_domain && $strip_www( $referrer_domain ) === $strip_www( $site_host ) ) {
                $referrer        = '';
                $referrer_domain = '';
            }
        }

        // ── Session ────────────────────────────────────────────────────────────
        $session_id = isset( $data['sessionId'] ) && ! empty( $data['sessionId'] )
            ? sanitize_text_field( $data['sessionId'] )
            : wp_generate_uuid4();

        // ── Construction du hit ────────────────────────────────────────────────
        $hit_data = array(
            'visitor_hash'    => $visitor_hash,
            'session_id'      => $session_id,
            'page_url'        => isset( $data['url'] )           ? esc_url_raw( $data['url'] )                   : '',
            'page_title'      => isset( $data['title'] )         ? sanitize_text_field( $data['title'] )         : '',
            'post_id'         => $post_id,
            'post_type'       => sanitize_key( $post_type ),
            'referrer'        => $referrer,
            'referrer_domain' => $referrer_domain,
            'utm_source'      => isset( $data['utmSource'] )     ? sanitize_text_field( $data['utmSource'] )     : '',
            'utm_medium'      => isset( $data['utmMedium'] )     ? sanitize_text_field( $data['utmMedium'] )     : '',
            'utm_campaign'    => isset( $data['utmCampaign'] )   ? sanitize_text_field( $data['utmCampaign'] )   : '',
            'device_type'     => $device_info['device_type'],
            'browser'         => $device_info['browser'],
            'browser_version' => $device_info['browser_version'],
            'os'              => $device_info['os'],
            'os_version'      => $device_info['os_version'],
            'screen_width'    => isset( $data['screenWidth'] )   ? absint( $data['screenWidth'] )                : 0,
            'screen_height'   => isset( $data['screenHeight'] )  ? absint( $data['screenHeight'] )               : 0,
            'country_code'    => sanitize_text_field( $geo['country_code'] ),
            'region'          => sanitize_text_field( $geo['region'] ),
            'city'            => sanitize_text_field( $geo['city'] ),
            'is_new_visitor'  => $is_new ? 1 : 0,
            'is_logged_in'    => is_user_logged_in() ? 1 : 0,
            'user_id'         => get_current_user_id(),
            'scroll_depth'    => 0, // mis à jour par les events scroll
            'hit_at'          => current_time( 'mysql', true ), // UTC
        );

        $hit_data = apply_filters( 'statify_before_track', $hit_data );
        if ( empty( $hit_data ) ) {
            return false;
        }

        // ── Insertion ─────────────────────────────────────────────────────────
        $table = $wpdb->prefix . 'statify_hits';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->insert( $table, $hit_data );

        if ( false === $inserted ) {
            return false;
        }

        $hit_id = $wpdb->insert_id;

        // ── Mise à jour de la session ──────────────────────────────────────────
        Statify_Session::update_session( $session_id, $hit_data );

        // NOTE : plus d'appel à Statify_Cache::invalidate_group() —
        // le cache est désactivé, ces appels ne font rien mais évitent
        // des requêtes SQL inutiles sur wp_options.

        do_action( 'statify_after_track', $hit_id, $hit_data );

        return $hit_id;
    }

    // ── Scroll event ──────────────────────────────────────────────────────────

    /**
     * Enregistre un événement de scroll et met à jour la session.
     */
    public static function handle_scroll( $data ) {
        global $wpdb;

        $session_id  = isset( $data['sessionId'] )   ? sanitize_text_field( $data['sessionId'] )  : '';
        $depth       = isset( $data['scrollDepth'] ) ? absint( $data['scrollDepth'] )             : 0;
        $url         = isset( $data['url'] )          ? esc_url_raw( $data['url'] )                : '';
        $visitor_hash = '';

        if ( empty( $session_id ) || $depth < 1 || $depth > 100 ) {
            return false;
        }

        // Valeurs valides : 25, 50, 75, 100
        if ( ! in_array( $depth, array( 25, 50, 75, 100 ), true ) ) {
            return false;
        }

        // Récupérer le visitor_hash depuis la session
        $table_sess = $wpdb->prefix . 'statify_sessions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT visitor_hash FROM {$table_sess} WHERE session_id = %s",
            $session_id
        ) );

        if ( $session ) {
            $visitor_hash = $session->visitor_hash;
        } else {
            // Fallback mode cookie : reconstruire le hash depuis le visitorId JS
            $opts   = get_option( 'statify_options', array() );
            $t_mode = isset( $opts['tracking_mode'] ) ? $opts['tracking_mode'] : 'cookieless';
            if ( 'cookie' === $t_mode && ! empty( $data['visitorId'] ) ) {
                $visitor_hash = hash( 'sha256', sanitize_text_field( $data['visitorId'] ) );
            }
        }

        // Insère l'event scroll (dédoublonné : un seul par session+depth)
        $table_scroll = $wpdb->prefix . 'statify_scroll';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_scroll} WHERE session_id = %s AND scroll_depth = %d LIMIT 1",
            $session_id,
            $depth
        ) );

        if ( ! $existing ) {
            // Trouver le post_id depuis l'URL
            $post_id = url_to_postid( $url );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert( $table_scroll, array(
                'session_id'   => $session_id,
                'visitor_hash' => $visitor_hash,
                'page_url'     => $url,
                'post_id'      => $post_id ? absint( $post_id ) : 0,
                'scroll_depth' => $depth,
                'recorded_at'  => current_time( 'mysql', true ),
            ) );
        }

        // Met à jour max_scroll_depth dans la session si supérieur
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_sess} SET max_scroll_depth = GREATEST(max_scroll_depth, %d) WHERE session_id = %s",
            $depth,
            $session_id
        ) );

        return true;
    }

        // ── IP ─────────────────────────────────────────────────────────────────────

    public static function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxies / load balancers
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR',           // Direct
        );

        foreach ( $headers as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) continue;
            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
            // X-Forwarded-For peut contenir une liste : on prend la première IP
            if ( strpos( $ip, ',' ) !== false ) {
                $ip = trim( explode( ',', $ip )[0] );
            }
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    private static function is_excluded_ip( $ip, $options ) {
        if ( empty( $options['excluded_ips'] ) ) return false;
        $excluded = array_filter( array_map( 'trim', explode( "\n", $options['excluded_ips'] ) ) );
        return in_array( $ip, $excluded, true );
    }

    // ── Visitor hash ───────────────────────────────────────────────────────────

    private static function generate_visitor_hash( $ip, $ua_string, $tracking_mode, $data ) {
        if ( 'cookie' === $tracking_mode ) {
            // En mode cookie, le JS envoie toujours le visitorId.
            // Si absent (JS désactivé, bloqueur…), on refuse le hit pour
            // éviter de créer un doublon via le hash IP/UA.
            if ( ! empty( $data['visitorId'] ) ) {
                return hash( 'sha256', sanitize_text_field( $data['visitorId'] ) );
            }
            return ''; // hash vide → hit refusé dans track()
        }

        // Mode cookieless : hash journalier SHA-256(IP + UA + Accept-Language + date)
        $accept_lang = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
            : '';
        $daily_salt = gmdate( 'Y-m-d' );

        return hash( 'sha256', $ip . $ua_string . $accept_lang . $daily_salt );
    }

    // ── Nouveau visiteur ? ─────────────────────────────────────────────────────

    /**
     * En mode cookie : "nouveau" = jamais vu ce visitor_hash dans la DB (toutes dates).
     * En mode cookieless : "nouveau" = pas vu aujourd'hui (hash tourne chaque jour).
     * La comparaison de date se fait en UTC pour coller avec hit_at stocké en UTC.
     */
    private static function is_new_visitor( $visitor_hash, $tracking_mode = 'cookieless' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'statify_hits';

        if ( 'cookie' === $tracking_mode ) {
            // En mode cookie le hash est permanent → "nouveau" = jamais vu
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s LIMIT 1",
                $visitor_hash
            ) );
        } else {
            // En mode cookieless le hash change chaque jour → "nouveau" = pas vu aujourd'hui UTC
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s AND hit_at >= %s AND hit_at < %s LIMIT 1",
                $visitor_hash,
                gmdate( 'Y-m-d' ) . ' 00:00:00',
                gmdate( 'Y-m-d', strtotime( '+1 day' ) ) . ' 00:00:00'
            ) );
        }

        return ( 0 === (int) $count );
    }

    // ── User-Agent parsing ─────────────────────────────────────────────────────

    public static function parse_user_agent( $ua ) {
        $result = array(
            'device_type'     => 'desktop',
            'browser'         => 'Unknown',
            'browser_version' => '',
            'os'              => 'Unknown',
            'os_version'      => '',
        );

        if ( empty( $ua ) ) {
            $result['device_type'] = 'unknown';
            return $result;
        }

        // Device type
        $tablet_kw = array( 'iPad', 'Tablet', 'Kindle', 'Silk', 'PlayBook' );
        $mobile_kw = array( 'Mobile', 'Android', 'iPhone', 'iPod', 'webOS', 'BlackBerry', 'Opera Mini', 'Opera Mobi', 'Windows Phone' );

        foreach ( $tablet_kw as $kw ) {
            if ( stripos( $ua, $kw ) !== false ) { $result['device_type'] = 'tablet'; break; }
        }
        if ( 'desktop' === $result['device_type'] ) {
            foreach ( $mobile_kw as $kw ) {
                if ( stripos( $ua, $kw ) !== false ) { $result['device_type'] = 'mobile'; break; }
            }
        }

        // Browser (ordre important : Edge avant Chrome)
        $browsers = array(
            'Edge'    => '/Edg[e\/]?\s?([\d.]+)/i',
            'Firefox' => '/Firefox\/([\d.]+)/i',
            'Chrome'  => '/Chrome\/([\d.]+)/i',
            'Safari'  => '/Version\/([\d.]+).*Safari/i',
            'Opera'   => '/(?:Opera|OPR)\/([\d.]+)/i',
            'IE'      => '/(?:MSIE |Trident\/.*rv:)([\d.]+)/i',
        );
        foreach ( $browsers as $name => $pattern ) {
            if ( preg_match( $pattern, $ua, $m ) ) {
                $result['browser']         = $name;
                $result['browser_version'] = $m[1];
                break;
            }
        }

        // OS
        $os_list = array(
            'Windows 10'  => '/Windows NT 10/i',
            'Windows 8.1' => '/Windows NT 6\.3/i',
            'Windows 8'   => '/Windows NT 6\.2/i',
            'Windows 7'   => '/Windows NT 6\.1/i',
            'macOS'       => '/Mac OS X ([\d_]+)/i',
            'iOS'         => '/OS ([\d_]+) like Mac OS X/i',
            'Android'     => '/Android ([\d.]+)/i',
            'Linux'       => '/Linux/i',
            'Chrome OS'   => '/CrOS/i',
        );
        foreach ( $os_list as $name => $pattern ) {
            if ( preg_match( $pattern, $ua, $m ) ) {
                $result['os'] = preg_replace( '/\s*[\d.]+$/', '', $name );
                if ( isset( $m[1] ) ) $result['os_version'] = str_replace( '_', '.', $m[1] );
                break;
            }
        }

        return $result;
    }
}
