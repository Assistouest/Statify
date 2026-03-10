<?php
namespace Always_Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moteur de tracking — reçoit les hits REST et les insère en DB.
 */
class Always_Analytics_Tracker
{

    public static function track($data)
    {
        global $wpdb;

        $options = get_option('always_analytics_options', array());

        // ── IP ─────────────────────────────────────────────────────────────
        $ip = self::get_client_ip();
        if (self::is_excluded_ip($ip, $options)) {
            return false;
        }

        // ── User-Agent & bot filter ──────────────────────────────────────────
        $ua_string = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $bot_mode = isset($options['bot_filter_mode']) ? $options['bot_filter_mode'] : 'normal';
        if ('off' !== $bot_mode && Always_Analytics_Bot_Filter::is_bot($ua_string, $bot_mode)) {
            return false;
        }

        // ── IP anonymisation ────────────────────────────────────────────────
        $anonymize = !empty($options['anonymize_ip']);
        $ip_for_geo = $ip;
        if ($anonymize) {
            $ip = Always_Analytics_Privacy::anonymize_ip($ip);
        }

        // ── Device info ────────────────────────────────────────────────────
        $device_info = self::parse_user_agent($ua_string);

        // ── Géolocalisation ─────────────────────────────────────────────────
        $geo = array('country_code' => '', 'region' => '', 'city' => '');
        if (!empty($options['geo_enabled'])) {
            $geo_provider = isset($options['geo_provider']) ? $options['geo_provider'] : 'native';
            $geolocation = new Always_Analytics_Geolocation($geo_provider, $options);
            $geo = $geolocation->lookup($ip_for_geo);;
        }

        // ── Visitor hash ────────────────────────────────────────────────────
        $tracking_mode = isset($options['tracking_mode']) ? $options['tracking_mode'] : 'cookieless';
        // $effective_mode peut differrer de $tracking_mode si visitorId est absent
        // en mode cookie (fallback cookieless automatique - aucune visite perdue).
        $effective_mode = $tracking_mode;
        $visitor_hash = self::generate_visitor_hash($ip, $ua_string, $tracking_mode, $data, $effective_mode);

        if (empty($visitor_hash)) {
            return false;
        }

        // ── Nouveau visiteur ? ──────────────────────────────────────────────
        // Utilise $effective_mode : si fallback cookieless, is_new = lookup du jour
        // (pas lookup global qui serait faux sur un hash qui change chaque jour).
        $is_new = self::is_new_visitor($visitor_hash, $effective_mode);

        // ── Post ID / type ─────────────────────────────────────────────────
        $post_id = isset($data['postId']) ? absint($data['postId']) : 0;
        $post_type = '';
        if ($post_id > 0) {
            $post_type = get_post_type($post_id);
            if (false === $post_type)
                $post_type = '';
        }

        // ── Référent ────────────────────────────────────────────────────────
        $referrer = isset($data['referrer']) ? esc_url_raw($data['referrer']) : '';
        $referrer_domain = '';
        if ($referrer) {
            $parsed = wp_parse_url($referrer);
            $referrer_domain = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';

            // Ignore internal referrers (navigations within the same site).
            // Compare against the site host, stripping any leading 'www.' for robustness.
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            $strip_www = function ($host) {
                return preg_replace('/^www\./i', '', $host);
            };
            if ($referrer_domain && $strip_www($referrer_domain) === $strip_www($site_host)) {
                $referrer = '';
                $referrer_domain = '';
            }
        }

        // ─ Session ────────────────────────────────────────────────────────
        $session_id = isset($data['sessionId']) && !empty($data['sessionId'])
            ? sanitize_text_field($data['sessionId'])
            : wp_generate_uuid4();

        // ── Construction du hit ─────────────────────────────────────────────
        $hit_data = array(
            'visitor_hash' => substr( $visitor_hash, 0, 64 ),
            'session_id' => substr( $session_id, 0, 64 ),
            'page_url' => mb_substr( isset($data['url']) ? esc_url_raw($data['url']) : '', 0, 2048 ),
            'page_title' => mb_substr( isset($data['title']) ? sanitize_text_field($data['title']) : '', 0, 512 ),
            'post_id' => $post_id,
            'post_type' => mb_substr( sanitize_key($post_type), 0, 20 ),
            'referrer' => mb_substr( $referrer, 0, 2048 ),
            'referrer_domain' => mb_substr( $referrer_domain, 0, 255 ),
            'utm_source' => mb_substr( isset($data['utmSource']) ? sanitize_text_field($data['utmSource']) : '', 0, 255 ),
            'utm_medium' => mb_substr( isset($data['utmMedium']) ? sanitize_text_field($data['utmMedium']) : '', 0, 255 ),
            'utm_campaign' => mb_substr( isset($data['utmCampaign']) ? sanitize_text_field($data['utmCampaign']) : '', 0, 255 ),
            'device_type' => $device_info['device_type'],
            'browser' => mb_substr( $device_info['browser'], 0, 100 ),
            'browser_version' => mb_substr( $device_info['browser_version'], 0, 20 ),
            'os' => mb_substr( $device_info['os'], 0, 100 ),
            'os_version' => mb_substr( $device_info['os_version'], 0, 20 ),
            'screen_width' => isset($data['screenWidth']) ? absint($data['screenWidth']) : 0,
            'screen_height' => isset($data['screenHeight']) ? absint($data['screenHeight']) : 0,
            'country_code' => mb_substr( sanitize_text_field($geo['country_code']), 0, 2 ),
            'region' => mb_substr( sanitize_text_field($geo['region']), 0, 100 ),
            'city' => mb_substr( sanitize_text_field($geo['city']), 0, 100 ),
            'is_new_visitor' => $is_new ? 1 : 0,
            'is_logged_in' => is_user_logged_in() ? 1 : 0,
            'user_id' => get_current_user_id(),
            'scroll_depth' => 0, // mis à jour par les events scroll
            // hit_source : si mode cookie mais visitorId absent (fallback cookieless),
            // on marque 'js_cookieless' pour distinguer dans les stats.
            'hit_source' => mb_substr( isset($data['hitSource'])
            ? sanitize_key($data['hitSource'])
            : ('cookie' === $tracking_mode && 'cookieless' === $effective_mode ? 'js_cookieless' : 'js'), 0, 20 ),
            'is_superseded' => 0,
            'hit_at' => current_time('mysql', true), // UTC
        );

        $hit_data = apply_filters('always_analytics_before_track', $hit_data);
        if (empty($hit_data)) {
            return false;
        }

        // ── Insertion ──────────────────────────────────────────────────────
        $table = $wpdb->prefix . 'aa_hits';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->insert($table, $hit_data);

        if (false === $inserted) {
            return false;
        }

        $hit_id = $wpdb->insert_id;

        // ── Mise à jour de la session ───────────────────────────────────────
        Always_Analytics_Session::update_session($session_id, $hit_data);

        do_action('always_analytics_after_track', $hit_id, $hit_data);

        return $hit_id;
    }

    // ── Noscript tracker ──────────────────────────────────────────────────

    /**
     * Enregistre un hit minimal depuis le pixel <noscript>.
     * Toujours en mode cookieless : IP anonymisée, pas de cookie, pas de visitorId.
     * Appelé uniquement si JS est désactivé (le pixel <noscript> n'est chargé
     * que quand JS est absent — garantie navigateur).
     *
     * Déduplication : on vérifie si un hit JS récent (< 60s) existe pour le même
     * hash afin de se protéger contre des cas edge (proxies, crawlers malveillants
     * qui chargent à la fois JS et noscript).
     *
     * @param array $data Données parsées depuis les query params GET.
     * @return int|false  ID du hit inséré, ou false si ignoré.
     */
    public static function track_noscript($data)
    {
        global $wpdb;

        $options = get_option('always_analytics_options', array());

        // ── Bot filter ────────────────────────────────────────────────────
        $ua_string = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $bot_mode = isset($options['bot_filter_mode']) ? $options['bot_filter_mode'] : 'normal';
        if ('off' !== $bot_mode && Always_Analytics_Bot_Filter::is_bot($ua_string, $bot_mode)) {
            return false;
        }

        // ── IP — toujours anonymisée en noscript ────────────────────────────
        $ip = self::get_client_ip();
        if (self::is_excluded_ip($ip, $options)) {
            return false;
        }
        $ip_anon = Always_Analytics_Privacy::anonymize_ip($ip);
        $ip_for_geo = $ip; // IP réelle pour la géoloc, avant anonymisation

        // ── Hash cookieless standard ───────────────────────────────────────
        $accept_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']))
            : '';
        $daily_salt = gmdate('Y-m-d');
        $visitor_hash = hash('sha256', $ip_anon . $ua_string . $accept_lang . $daily_salt);

        // ── Déduplication : hit JS récent pour ce hash ? ─────────────────────
        $table = $wpdb->prefix . 'aa_hits';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $recent_js = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE visitor_hash = %s
               AND hit_source IN ('js','pre_consent')
               AND hit_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)
             LIMIT 1",
            $visitor_hash
        ));
        if ((int)$recent_js > 0) {
            return false; // hit JS déjà présent — on ignore le pixel noscript
        }

        // ── Device depuis UA ───────────────────────────────────────────────
        $device_info = self::parse_user_agent($ua_string);

        // ─ Géolocalisation ────────────────────────────────────────────────
        $geo = array('country_code' => '', 'region' => '', 'city' => '');
        if (!empty($options['geo_enabled'])) {
            $geo_provider = isset($options['geo_provider']) ? $options['geo_provider'] : 'native';
            $geolocation = new Always_Analytics_Geolocation($geo_provider, $options);
            $geo = $geolocation->lookup($ip_for_geo);
        }

        // ── Nouveau visiteur ───────────────────────────────────────────────
        $is_new = self::is_new_visitor($visitor_hash, 'cookieless');

        // ── URL / referrer ──────────────────────────────────────────────────
        $page_url = isset($data['url']) ? esc_url_raw($data['url']) : '';
        $page_title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        $post_id = isset($data['postId']) ? absint($data['postId']) : 0;
        $referrer = isset($data['referrer']) ? esc_url_raw($data['referrer']) : '';
        $referrer_domain = '';
        if ($referrer) {
            $parsed = wp_parse_url($referrer);
            $referrer_domain = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            $strip_www = function ($h) {
                return preg_replace('/^www\./i', '', $h);
            };
            if ($referrer_domain && $strip_www($referrer_domain) === $strip_www($site_host)) {
                $referrer = $referrer_domain = '';
            }
        }

        // ── Session ID unique pour ce hit noscript ───────────────────────────
        // Pas de sessionStorage disponible → chaque chargement de page est sa propre session.
        $session_id = 'noscript_' . wp_generate_uuid4();

        // ─ Hit data ──────────────────────────────────────────────────────
        $hit_data = array(
            'visitor_hash' => substr( $visitor_hash, 0, 64 ),
            'session_id' => substr( $session_id, 0, 64 ),
            'page_url' => mb_substr( $page_url, 0, 2048 ),
            'page_title' => mb_substr( $page_title, 0, 512 ),
            'post_id' => $post_id,
            'post_type' => mb_substr( $post_id > 0 ? (get_post_type($post_id) ?: '') : '', 0, 20 ),
            'referrer' => mb_substr( $referrer, 0, 2048 ),
            'referrer_domain' => mb_substr( $referrer_domain, 0, 255 ),
            'utm_source' => mb_substr( isset($data['utm_source']) ? sanitize_text_field($data['utm_source']) : '', 0, 255 ),
            'utm_medium' => mb_substr( isset($data['utm_medium']) ? sanitize_text_field($data['utm_medium']) : '', 0, 255 ),
            'utm_campaign' => mb_substr( isset($data['utm_campaign']) ? sanitize_text_field($data['utm_campaign']) : '', 0, 255 ),
            'device_type' => $device_info['device_type'],
            'browser' => mb_substr( $device_info['browser'], 0, 100 ),
            'browser_version' => mb_substr( $device_info['browser_version'], 0, 20 ),
            'os' => mb_substr( $device_info['os'], 0, 100 ),
            'os_version' => mb_substr( $device_info['os_version'], 0, 20 ),
            'screen_width' => 0,
            'screen_height' => 0,
            'country_code' => mb_substr( sanitize_text_field($geo['country_code']), 0, 2 ),
            'region' => mb_substr( sanitize_text_field($geo['region']), 0, 100 ),
            'city' => mb_substr( sanitize_text_field($geo['city']), 0, 100 ),
            'is_new_visitor' => $is_new ? 1 : 0,
            'is_logged_in' => 0,
            'user_id' => 0,
            'scroll_depth' => 0,
            'hit_source' => 'noscript',
            'is_superseded' => 0,
            'hit_at' => current_time('mysql', true),
        );

        $hit_data = apply_filters('always_analytics_before_track', $hit_data);
        if (empty($hit_data)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->insert($table, $hit_data);
        if (false === $inserted) {
            return false;
        }

        $hit_id = $wpdb->insert_id;

        // Session minimale (bounce automatique, durée 0)
        Always_Analytics_Session::update_session($session_id, $hit_data);

        do_action('always_analytics_after_track', $hit_id, $hit_data);
        return $hit_id;
    }

    // ── Pre-consent upgrade ─────────────────────────────────────────────────

    /**
     * Fusionne un hit pre_consent avec le hit cookie complet après acceptation.
     *
     * Scénario mode cookie + bannière :
     *   1. Visiteur arrive → JS envoie un hit cookieless avancé (hit_source='pre_consent')
     *      avec le sessionId de sessionStorage. Scroll, heartbeat, durée sont collectés.
     *   2. Visiteur accepte → JS envoie le hit complet (visitorId cookie) en incluant
     *      preConsentSessionId pour permettre la fusion.
     *   3. Cette méthode :
     *      - Marque les hits pre_consent comme is_superseded=1 (exclus du comptage UV)
     *      - Rattache la session pre_consent au visitor_hash définitif (cookie)
     *      - Les données scroll/durée/engagement sont ainsi conservées et rattachées
     *
     * @param string $pre_session_id   SessionId du hit pre_consent à fusionner.
     * @param string $new_visitor_hash Visitor hash définitif (depuis cookie).
     * @return bool
     */
    public static function upgrade_pre_consent($pre_session_id, $new_visitor_hash)
    {
        global $wpdb;
        $table_hits = $wpdb->prefix . 'aa_hits';
        $table_sess = $wpdb->prefix . 'aa_sessions';

        if (empty($pre_session_id) || empty($new_visitor_hash)) {
            return false;
        }

        // Marquer tous les hits pre_consent de cette session comme superseded
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $updated = $wpdb->update(
            $table_hits,
            array('is_superseded' => 1),
            array('session_id' => $pre_session_id, 'hit_source' => 'pre_consent'),
            array('%d'),
            array('%s', '%s')
        );

        // Rattacher la session pre_consent au visitor_hash définitif
        // Les données de scroll/durée/engagement collectées avant consentement
        // sont ainsi liées au visiteur identifié par cookie.
        if ($updated) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $table_sess,
                array('visitor_hash' => $new_visitor_hash),
                array('session_id' => $pre_session_id),
                array('%s'),
                array('%s')
            );
        }

        return (bool)$updated;
    }

    // ── Scroll event ───────────────────────────────────────────────────────

    /**
     * Enregistre un événement de scroll et met à jour la session.
     */
    public static function handle_scroll($data)
    {
        global $wpdb;

        $session_id = isset($data['sessionId']) ? sanitize_text_field($data['sessionId']) : '';
        $depth = isset($data['scrollDepth']) ? absint($data['scrollDepth']) : 0;
        $url = isset($data['url']) ? esc_url_raw($data['url']) : '';
        $visitor_hash = '';

        if (empty($session_id) || $depth < 1 || $depth > 100) {
            return false;
        }

        // Valeurs valides : 25, 50, 75, 100
        if (!in_array($depth, array(25, 50, 75, 100), true)) {
            return false;
        }

        // Récupérer le visitor_hash depuis la session
        $table_sess = $wpdb->prefix . 'aa_sessions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT visitor_hash FROM {$table_sess} WHERE session_id = %s",
            $session_id
        ));

        if ($session) {
            $visitor_hash = $session->visitor_hash;
        }
        else {
            // Fallback mode cookie : reconstruire le hash depuis le visitorId JS
            $opts = get_option('always_analytics_options', array());
            $t_mode = isset($opts['tracking_mode']) ? $opts['tracking_mode'] : 'cookieless';
            if ('cookie' === $t_mode && !empty($data['visitorId'])) {
                $visitor_hash = hash('sha256', sanitize_text_field($data['visitorId']));
            }
        }

        // Insère l'event scroll (dédoublonné : un seul par session+depth)
        $table_scroll = $wpdb->prefix . 'aa_scroll';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_scroll} WHERE session_id = %s AND scroll_depth = %d LIMIT 1",
            $session_id,
            $depth
        ));

        if (!$existing) {
            // Trouver le post_id depuis l'URL
            $post_id = url_to_postid($url);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert($table_scroll, array(
                'session_id' => $session_id,
                'visitor_hash' => $visitor_hash,
                'page_url' => $url,
                'post_id' => $post_id ? absint($post_id) : 0,
                'scroll_depth' => $depth,
                'recorded_at' => current_time('mysql', true),
            ));
        }

        // Met à jour max_scroll_depth dans la session si supérieur
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_sess} SET max_scroll_depth = GREATEST(max_scroll_depth, %d) WHERE session_id = %s",
            $depth,
            $session_id
        ));

        return true;
    }

    // ── IP ─────────────────────────────────────────────────────────────────

    /**
     * Récupère l'adresse IP du client de manière sécurisée.
     * Ne fait confiance aux en-têtes de proxy que si l'IP de connexion directe (REMOTE_ADDR)
     * appartient à une infrastructure de confiance (Cloudflare ou proxy déclaré).
     */
    public static function get_client_ip()
    {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!filter_var($remote_addr, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        $options = get_option('always_analytics_options', array());
        $mode = isset($options['trusted_proxy_mode']) ? $options['trusted_proxy_mode'] : 'none';

        if ('none' === $mode) {
            return $remote_addr;
        }



        if ('custom' === $mode && !empty($options['trusted_proxies'])) {
            $trusted = array_filter(array_map('trim', explode("\n", $options['trusted_proxies'])));
            if (self::is_ip_in_range($remote_addr, $trusted)) {
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
                    if (strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return $remote_addr;
    }

    /**
     * Vérifie si une IP appartient à une liste d'IPs ou de plages CIDR.
     */
    public static function is_ip_in_range($ip, $ranges)
    {
        foreach ((array)$ranges as $range) {
            if (strpos($range, '/') === false) {
                if ($ip === $range)
                    return true;
                continue;
            }

            list($subnet, $bits) = explode('/', $range);
            $bits = (int)$bits;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip_long = ip2long($ip);
                $subnet_long = ip2long($subnet);
                $mask = -1 << (32 - $bits);
                if (($ip_long& $mask) === ($subnet_long& $mask))
                    return true;
            }
            elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ip_bin = inet_pton($ip);
                $subnet_bin = inet_pton($subnet);
                $mask = '';
                for ($i = 0; $i < 16; $i++) {
                    if ($bits >= 8) {
                        $mask .= chr(255);
                        $bits -= 8;
                    }
                    elseif ($bits > 0) {
                        $mask .= chr(256 - (1 << (8 - $bits)));
                        $bits = 0;
                    }
                    else {
                        $mask .= chr(0);
                    }
                }
                if (($ip_bin& $mask) === ($subnet_bin& $mask))
                    return true;
            }
        }
        return false;
    }

    private static function is_excluded_ip($ip, $options)
    {
        if (empty($options['excluded_ips']))
            return false;
        $excluded = array_filter(array_map('trim', explode("\n", $options['excluded_ips'])));
        return in_array($ip, $excluded, true);
    }

    // ─ Visitor hash ───────────────────────────────────────────────────────

    private static function generate_visitor_hash($ip, $ua_string, $tracking_mode, $data, &$effective_mode = null)
    {
        if ('cookie' === $tracking_mode) {
            if (!empty($data['visitorId'])) {
                // Cas nominal : visitorId cookie present -> hash permanent.
                if (null !== $effective_mode)
                    $effective_mode = 'cookie';
                return hash('sha256', sanitize_text_field($data['visitorId']));
            }
            // Fallback cookieless : visitorId absent (cookie bloqué, bloqueur de pub…).
            if (null !== $effective_mode)
                $effective_mode = 'cookieless';
        }

        // ── Hash cookieless ─────────────────────────────────────────────────
        // La fenêtre d'unicité est configurable dans les réglages :
        //   'daily'   → SHA256(IP_anon + UA + Accept-Language + Y-m-d UTC)
        //               Un visiteur unique par jour. Par défaut.
        //   'session' → SHA256(IP_anon + UA + Accept-Language + sessionId JS)
        //               Hash lié à la session navigateur, aucune persistance.
        //               Recommandé par la CNIL pour le mode sans cookie.
        $options = get_option('always_analytics_options', array());
        $window  = isset($options['cookieless_window']) ? $options['cookieless_window'] : 'daily';

        $accept_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']))
            : '';

        if ('session' === $window) {
            // Sel = sessionId envoyé par le tracker JS (sessionStorage, durée onglet).
            // Si absent (noscript, fallback), on replie sur le sel journalier.
            $session_id = isset($data['sessionId']) && !empty($data['sessionId'])
                ? sanitize_text_field($data['sessionId'])
                : gmdate('Y-m-d');
            $salt = $session_id;
        } else {
            // daily (défaut)
            $salt = gmdate('Y-m-d');
        }

        return hash('sha256', $ip . $ua_string . $accept_lang . $salt);
    }

    // ── Nouveau visiteur ? ─────────────────────────────────────────────────

    /**
     * En mode cookie : "nouveau" = jamais vu ce visitor_hash dans la DB (toutes dates).
     * En mode cookieless : "nouveau" = pas vu aujourd'hui (hash tourne chaque jour).
     * La comparaison de date se fait en UTC pour coller avec hit_at stocké en UTC.
     */
    private static function is_new_visitor($visitor_hash, $tracking_mode = 'cookieless')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_hits';

        $options = get_option('always_analytics_options', array());
        $window  = isset($options['cookieless_window']) ? $options['cookieless_window'] : 'daily';

        if ('cookie' === $tracking_mode) {
            // Hash permanent → nouveau = jamais vu en base
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s LIMIT 1",
                $visitor_hash
            ));
        } elseif ('session' === $window) {
            // Hash lié à la session JS → par définition jamais vu (hash unique par session)
            // On vérifie quand même pour se protéger contre les doublons (retry réseau, etc.)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s LIMIT 1",
                $visitor_hash
            ));
        } else {
            // daily (défaut) → nouveau = pas vu aujourd'hui UTC
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s AND hit_at >= %s AND hit_at < %s LIMIT 1",
                $visitor_hash,
                gmdate('Y-m-d') . ' 00:00:00',
                gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00'
            ));
        }

        return (0 === (int)$count);
    }

    // ── User-Agent parsing ──────────────────────────────────────────────────

    public static function parse_user_agent($ua)
    {
        $result = array(
            'device_type' => 'desktop',
            'browser' => 'Unknown',
            'browser_version' => '',
            'os' => 'Unknown',
            'os_version' => '',
        );

        if (empty($ua)) {
            $result['device_type'] = 'unknown';
            return $result;
        }

        // Device type
        $tablet_kw = array('iPad', 'Tablet', 'Kindle', 'Silk', 'PlayBook');
        $mobile_kw = array('Mobile', 'Android', 'iPhone', 'iPod', 'webOS', 'BlackBerry', 'Opera Mini', 'Opera Mobi', 'Windows Phone');

        foreach ($tablet_kw as $kw) {
            if (stripos($ua, $kw) !== false) {
                $result['device_type'] = 'tablet';
                break;
            }
        }
        if ('desktop' === $result['device_type']) {
            foreach ($mobile_kw as $kw) {
                if (stripos($ua, $kw) !== false) {
                    $result['device_type'] = 'mobile';
                    break;
                }
            }
        }

        // Browser — ordre critique : du plus spécifique au plus générique
        $browsers = array(
            // Chromium-based (avant Chrome pour éviter faux positifs)
            'Edge'            => '/Edg[e\/]?\s?([\d.]+)/i',
            'Opera GX'        => '/OPR\/([\d.]+).*OPG|OPG.*OPR\/([\d.]+)/i',
            'Opera Mini'      => '/Opera Mini\/([\d.]+)/i',
            'Opera'           => '/(?:Opera|OPR)\/([\d.]+)/i',
            'Samsung Browser' => '/SamsungBrowser\/([\d.]+)/i',
            'Yandex Browser'  => '/YaBrowser\/([\d.]+)/i',
            'Brave'           => '/Brave\/([\d.]+)/i',
            'Vivaldi'         => '/Vivaldi\/([\d.]+)/i',
            'DuckDuckGo'      => '/DuckDuckGo\/([\d.]+)/i',
            'Puffin'          => '/Puffin\/([\d.]+)/i',
            'UCBrowser'       => '/UCBrowser\/([\d.]+)/i',
            'QQ Browser'      => '/MQQBrowser\/([\d.]+)/i',
            'Baidu Browser'   => '/baidubrowser\/([\d.]+)/i',
            'Silk'            => '/Silk\/([\d.]+)/i',
            // Chrome doit être après tous les Chromium dérivés
            'Chrome'          => '/Chrome\/([\d.]+)/i',
            // Firefox et dérivés
            'Firefox Focus'   => '/Focus\/([\d.]+)/i',
            'Firefox'         => '/Firefox\/([\d.]+)/i',
            // Safari en dernier car son token apparaît dans beaucoup d'UA Chromium
            'Safari'          => '/Version\/([\d.]+).*Safari/i',
            // Legacy
            'IE'              => '/(?:MSIE |Trident\/.*rv:)([\d.]+)/i',
        );
        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                $result['browser'] = $name;
                $result['browser_version'] = $m[1];
                break;
            }
        }

        // OS — ordre : iOS avant macOS (iOS UA contient aussi "Mac OS X")
        $os_list = array(
            // Apple mobile/desktop — iOS avant macOS obligatoire
            'iOS'          => '/OS ([\d_]+) like Mac OS X/i',
            'iPadOS'       => '/iPad.*OS ([\d_]+)/i',
            'macOS'        => '/Mac OS X ([\d_]+)/i',
            // Windows — versions spécifiques avant le générique
            'Windows 11'   => '/Windows NT 10\.0.*Win64/i',
            'Windows 10'   => '/Windows NT 10/i',
            'Windows 8.1'  => '/Windows NT 6\.3/i',
            'Windows 8'    => '/Windows NT 6\.2/i',
            'Windows 7'    => '/Windows NT 6\.1/i',
            'Windows Vista'=> '/Windows NT 6\.0/i',
            'Windows XP'   => '/Windows NT 5\.1/i',
            'Windows Phone'=> '/Windows Phone/i',
            // Android
            'Android'      => '/Android ([\d.]+)/i',
            // Linux distros — avant Linux générique
            'Ubuntu'       => '/Ubuntu/i',
            'Fedora'       => '/Fedora/i',
            'Debian'       => '/Debian/i',
            'Linux Mint'   => '/Linux Mint/i',
            'Chrome OS'    => '/CrOS/i',
            'Linux'        => '/Linux/i',
            // Autres
            'BlackBerry'   => '/BlackBerry/i',
            'Symbian'      => '/Symbian/i',
            'KaiOS'        => '/KAIOS/i',
            'Tizen'        => '/Tizen/i',
            'HarmonyOS'    => '/HarmonyOS/i',
        );
        foreach ($os_list as $name => $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                $result['os'] = $name;
                if (isset($m[1]))
                    $result['os_version'] = str_replace('_', '.', $m[1]);
                break;
            }
        }

        return $result;
    }
}
