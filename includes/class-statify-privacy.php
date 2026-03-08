<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Privacy handler — anonymisation, agrégation, RGPD.
 *
 * Philosophie v1.1 : « Anonymiser plutôt que supprimer ».
 * Les données brutes anciennes sont rendues non-liables à une personne
 * (visitor_hash randomisé, user_id effacé) tandis que les métriques
 * statistiques (durée, scroll, device, pays, page…) sont conservées.
 * L'agrégation journalière consolide les comptages (UV, sessions, etc.)
 * qui ne seraient plus calculables après anonymisation.
 */
class Statify_Privacy {

    // ── IP anonymisation ──────────────────────────────────────────────────────

    /**
     * Anonymize an IP address (remove last octet for IPv4, last 80 bits for IPv6).
     */
    public static function anonymize_ip( $ip ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return preg_replace( '/\.\d+$/', '.0', $ip );
        }
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            $packed = inet_pton( $ip );
            if ( false === $packed ) {
                return '::';
            }
            for ( $i = 6; $i < 16; $i++ ) {
                $packed[ $i ] = "\x00";
            }
            return inet_ntop( $packed );
        }
        return '0.0.0.0';
    }

    // ── Hooks WordPress vie privée ────────────────────────────────────────────

    /**
     * Enregistre tous les hooks RGPD.
     */
    public function register_privacy_hooks() {
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
        $this->add_privacy_policy_content();
    }

    // ── Politique de confidentialité suggérée ──────────────────────────────────

    /**
     * Ajoute un texte de politique de confidentialité dans l'outil WordPress.
     */
    public function add_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = sprintf(
            '<h2>%s</h2>' .
            '<p>%s</p>' .
            '<h3>%s</h3>' .
            '<p>%s</p>' .
            '<ul>' .
            '<li>%s</li>' .
            '<li>%s</li>' .
            '<li>%s</li>' .
            '<li>%s</li>' .
            '<li>%s</li>' .
            '<li>%s</li>' .
            '</ul>' .
            '<h3>%s</h3>' .
            '<p>%s</p>' .
            '<h3>%s</h3>' .
            '<p>%s</p>' .
            '<h3>%s</h3>' .
            '<p>%s</p>',
            __( 'Statistiques de visite (Statify)', 'statify' ),
            __( 'Ce site utilise le plugin Statify pour collecter des statistiques de visite anonymes. Ces données nous aident à comprendre comment le site est utilisé et à améliorer son contenu.', 'statify' ),
            __( 'Données collectées', 'statify' ),
            __( 'Lors de votre visite, les informations suivantes peuvent être collectées :', 'statify' ),
            __( 'Pages visitées et durée de consultation', 'statify' ),
            __( 'Profondeur de défilement (scroll)', 'statify' ),
            __( 'Type d\'appareil, navigateur et système d\'exploitation', 'statify' ),
            __( 'Pays d\'origine (via géolocalisation IP anonymisée)', 'statify' ),
            __( 'Source de trafic (référent, paramètres UTM)', 'statify' ),
            __( 'Résolution d\'écran', 'statify' ),
            __( 'Traitement des données', 'statify' ),
            __( 'Aucune adresse IP complète n\'est stockée. Les adresses IP sont anonymisées (dernier octet supprimé) avant tout traitement. Un identifiant visiteur non réversible (hash cryptographique) est généré pour compter les visiteurs uniques sans permettre de vous identifier. Les données brutes sont automatiquement anonymisées après la période de rétention configurée, et les statistiques agrégées sont conservées.', 'statify' ),
            __( 'Cookies', 'statify' ),
            __( 'En mode « sans cookie », aucun cookie n\'est déposé. En mode « cookie », un cookie d\'identification visiteur peut être déposé avec votre consentement préalable (durée : 13 mois maximum). Un cookie de session temporaire (sessionStorage) est utilisé pour regrouper vos pages vues en une seule visite.', 'statify' ),
            __( 'Vos droits', 'statify' ),
            __( 'Vous pouvez demander l\'exportation ou la suppression de vos données de visite via la page « Politique de confidentialité » ou en contactant l\'administrateur du site. Les données seront anonymisées (rendues non identifiables) plutôt que supprimées, afin de préserver les statistiques globales.', 'statify' )
        );

        wp_add_privacy_policy_content( 'Statify (Advanced Stats)', $content );
    }

    // ── Purge / Anonymisation automatique ─────────────────────────────────────

    /**
     * Anonymise les données brutes plus anciennes que la période de rétention.
     *
     * Flux :
     * 1. Agrège les stats dans statify_daily (UV, sessions, durée, scroll, bounce)
     * 2. Anonymise les hits : visitor_hash → hash aléatoire, user_id → 0
     * 3. Anonymise les sessions : visitor_hash → hash aléatoire
     * 4. Anonymise les scroll events : visitor_hash → hash aléatoire
     *
     * Résultat : les données brutes restent exploitables pour les distributions
     * (durées, devices, heures, scroll…) mais ne sont plus liables à un individu.
     * Les comptages UV/sessions reposent sur statify_daily.
     */
    public function purge_old_data() {
        global $wpdb;

        $options        = get_option( 'statify_options', array() );
        $retention_days = isset( $options['retention_days'] ) ? absint( $options['retention_days'] ) : 90;

        if ( 0 === $retention_days ) {
            return; // 0 = rétention illimitée
        }

        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
        $cutoff_day  = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

        do_action( 'statify_before_purge', $cutoff_date );

        $table_hits     = $wpdb->prefix . 'statify_hits';
        $table_sessions = $wpdb->prefix . 'statify_sessions';
        $table_scroll   = $wpdb->prefix . 'statify_scroll';
        $table_daily    = $wpdb->prefix . 'statify_daily';

        // ── 1. Agrégation enrichie des jours non encore agrégés ───────────────
        // On agrège chaque jour avant le cutoff qui n'a pas encore d'entrée daily.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $days_to_agg = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT DATE(hit_at) as d
             FROM {$table_hits}
             WHERE hit_at < %s
               AND DATE(hit_at) NOT IN (SELECT DISTINCT stat_date FROM {$table_daily})
             ORDER BY d ASC
             LIMIT 365",
            $cutoff_date
        ) );

        foreach ( $days_to_agg as $day ) {
            $this->aggregate_day( $day );
        }

        // ── 2. Enrichir les agrégats existants (durée, engagement, scroll) ────
        // Met à jour avg_duration, bounce_rate (et avg_engagement, avg_scroll si colonnes présentes)
        $this->enrich_daily_aggregates( $cutoff_date );

        // ── 3. Anonymiser les hits ────────────────────────────────────────────
        // Remplace visitor_hash par un hash aléatoire unique par ligne.
        // Efface user_id. Conserve tout le reste pour les stats.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_hits}
             SET visitor_hash = SHA2(CONCAT(id, RAND(), UUID()), 256),
                 user_id      = 0,
                 is_logged_in = 0,
                 referrer     = CASE WHEN referrer_domain != '' THEN referrer_domain ELSE '' END
             WHERE hit_at < %s
               AND visitor_hash NOT LIKE 'anon_%%'",
            $cutoff_date
        ) );

        // Marquer comme anonymisé (préfixe anon_ pour ne pas re-traiter)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_hits}
             SET visitor_hash = CONCAT('anon_', LEFT(visitor_hash, 58))
             WHERE hit_at < %s
               AND visitor_hash NOT LIKE 'anon_%%'",
            $cutoff_date
        ) );

        // ── 4. Anonymiser les sessions ────────────────────────────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_sessions}
             SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(session_id, RAND()), 256))
             WHERE started_at < %s
               AND visitor_hash NOT LIKE 'anon_%%'",
            $cutoff_date
        ) );

        // ── 5. Anonymiser les scroll events ───────────────────────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $has_scroll = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_scroll ) );
        if ( $has_scroll ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table_scroll}
                 SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(id, RAND()), 256))
                 WHERE recorded_at < %s
                   AND visitor_hash NOT LIKE 'anon_%%'",
                $cutoff_date
            ) );
        }

        do_action( 'statify_after_purge', $cutoff_date );
    }

    /**
     * Agrège un jour donné dans statify_daily.
     */
    private function aggregate_day( $day ) {
        global $wpdb;
        $table_hits  = $wpdb->prefix . 'statify_hits';
        $table_daily = $wpdb->prefix . 'statify_daily';
        $t_sess      = $wpdb->prefix . 'statify_sessions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table_daily}
                (stat_date, page_url, post_id, unique_visitors, page_views, sessions,
                 new_visitors, returning_vis, avg_duration, bounce_rate)
             SELECT
                DATE(h.hit_at),
                h.page_url,
                h.post_id,
                COUNT(DISTINCT h.visitor_hash),
                COUNT(*),
                COUNT(DISTINCT h.session_id),
                SUM(h.is_new_visitor),
                SUM(CASE WHEN h.is_new_visitor = 0 THEN 1 ELSE 0 END),
                COALESCE(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time
                                  WHEN s.duration > 0 THEN s.duration ELSE NULL END), 0),
                CASE WHEN COUNT(DISTINCT h.session_id) > 0
                     THEN SUM(CASE WHEN s.is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT h.session_id) * 100
                     ELSE 0 END
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
            $day
        ) );
    }

    /**
     * Enrichit les agrégats journaliers existants avec durée/bounce
     * pour les jours dont les données brutes vont être anonymisées.
     */
    private function enrich_daily_aggregates( $cutoff_date ) {
        global $wpdb;
        $table_hits  = $wpdb->prefix . 'statify_hits';
        $table_daily = $wpdb->prefix . 'statify_daily';
        $t_sess      = $wpdb->prefix . 'statify_sessions';

        // Met à jour avg_duration et bounce_rate pour les jours avant le cutoff
        // qui ont encore avg_duration = 0 (pas encore enrichis).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_daily} d
             INNER JOIN (
                SELECT DATE(h.hit_at) as stat_date, h.page_url,
                    COALESCE(AVG(CASE WHEN s.engagement_time > 0 THEN s.engagement_time
                                      WHEN s.duration > 0 THEN s.duration ELSE NULL END), 0) as avg_dur,
                    CASE WHEN COUNT(DISTINCT h.session_id) > 0
                         THEN SUM(CASE WHEN s.is_bounce = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT h.session_id) * 100
                         ELSE 0 END as br
                FROM {$table_hits} h
                LEFT JOIN {$t_sess} s ON s.session_id = h.session_id
                WHERE h.hit_at < %s AND h.visitor_hash NOT LIKE 'anon_%%'
                GROUP BY DATE(h.hit_at), h.page_url
             ) src ON d.stat_date = src.stat_date AND d.page_url = src.page_url
             SET d.avg_duration = src.avg_dur,
                 d.bounce_rate  = src.br
             WHERE d.avg_duration = 0",
            $cutoff_date
        ) );
    }

    // ── Export de données personnelles (RGPD art. 20) ─────────────────────────

    public function register_exporter( $exporters ) {
        $exporters['statify'] = array(
            'exporter_friendly_name' => __( 'Statify — Données de visite', 'statify' ),
            'callback'               => array( $this, 'export_personal_data' ),
        );
        return $exporters;
    }

    /**
     * Exporte les données de visite d'un utilisateur.
     * Couvre hits + sessions + scroll.
     */
    public function export_personal_data( $email_address, $page = 1 ) {
        global $wpdb;

        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return array( 'data' => array(), 'done' => true );
        }

        $table_hits = $wpdb->prefix . 'statify_hits';
        $table_sess = $wpdb->prefix . 'statify_sessions';
        $limit  = 100;
        $offset = ( $page - 1 ) * $limit;

        // ── Hits ──────────────────────────────────────────────────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $hits = $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url, page_title, referrer_domain, device_type, browser, os,
                    country_code, screen_width, screen_height, hit_at,
                    utm_source, utm_medium, utm_campaign
             FROM {$table_hits}
             WHERE user_id = %d AND visitor_hash NOT LIKE 'anon_%%'
             ORDER BY hit_at DESC LIMIT %d OFFSET %d",
            $user->ID, $limit, $offset
        ) );

        $data = array();
        foreach ( $hits as $hit ) {
            $item_data = array(
                array( 'name' => __( 'Page', 'statify' ),       'value' => $hit->page_url ),
                array( 'name' => __( 'Titre', 'statify' ),      'value' => $hit->page_title ),
                array( 'name' => __( 'Date', 'statify' ),       'value' => $hit->hit_at ),
                array( 'name' => __( 'Appareil', 'statify' ),   'value' => $hit->device_type ),
                array( 'name' => __( 'Navigateur', 'statify' ), 'value' => $hit->browser ),
                array( 'name' => __( 'OS', 'statify' ),         'value' => $hit->os ),
                array( 'name' => __( 'Pays', 'statify' ),       'value' => $hit->country_code ),
                array( 'name' => __( 'Écran', 'statify' ),      'value' => $hit->screen_width . 'x' . $hit->screen_height ),
                array( 'name' => __( 'Référent', 'statify' ),   'value' => $hit->referrer_domain ),
            );
            if ( $hit->utm_source ) {
                $item_data[] = array( 'name' => 'UTM Source',   'value' => $hit->utm_source );
                $item_data[] = array( 'name' => 'UTM Medium',   'value' => $hit->utm_medium );
                $item_data[] = array( 'name' => 'UTM Campaign', 'value' => $hit->utm_campaign );
            }
            $data[] = array(
                'group_id'    => 'statify-hits',
                'group_label' => __( 'Statify — Pages visitées', 'statify' ),
                'item_id'     => 'statify-hit-' . md5( $hit->hit_at . $hit->page_url ),
                'data'        => $item_data,
            );
        }

        // ── Sessions (première page seulement) ───────────────────────────────
        if ( 1 === $page ) {
            // Récupère les visitor_hash de cet utilisateur
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $hashes = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT visitor_hash FROM {$table_hits}
                 WHERE user_id = %d AND visitor_hash NOT LIKE 'anon_%%'",
                $user->ID
            ) );

            if ( ! empty( $hashes ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $sessions = $wpdb->get_results( $wpdb->prepare(
                    "SELECT started_at, duration, engagement_time, page_count,
                            entry_page, exit_page, device_type, country_code,
                            is_bounce, max_scroll_depth
                     FROM {$table_sess}
                     WHERE visitor_hash IN ({$placeholders})
                     ORDER BY started_at DESC LIMIT 100",
                    ...$hashes
                ) );

                foreach ( $sessions as $s ) {
                    $dur = $s->engagement_time > 0 ? $s->engagement_time : $s->duration;
                    $data[] = array(
                        'group_id'    => 'statify-sessions',
                        'group_label' => __( 'Statify — Sessions de visite', 'statify' ),
                        'item_id'     => 'statify-sess-' . md5( $s->started_at ),
                        'data'        => array(
                            array( 'name' => __( 'Début', 'statify' ),       'value' => $s->started_at ),
                            array( 'name' => __( 'Durée (s)', 'statify' ),   'value' => $dur ),
                            array( 'name' => __( 'Pages vues', 'statify' ),  'value' => $s->page_count ),
                            array( 'name' => __( 'Page d\'entrée', 'statify' ), 'value' => $s->entry_page ),
                            array( 'name' => __( 'Page de sortie', 'statify' ), 'value' => $s->exit_page ),
                            array( 'name' => __( 'Scroll max (%)', 'statify' ), 'value' => $s->max_scroll_depth ),
                            array( 'name' => __( 'Rebond', 'statify' ),      'value' => $s->is_bounce ? __( 'Oui', 'statify' ) : __( 'Non', 'statify' ) ),
                        ),
                    );
                }
            }
        }

        return array(
            'data' => $data,
            'done' => count( $hits ) < $limit,
        );
    }

    // ── Effacement de données personnelles (RGPD art. 17) ─────────────────────

    public function register_eraser( $erasers ) {
        $erasers['statify'] = array(
            'eraser_friendly_name' => __( 'Statify — Données de visite', 'statify' ),
            'callback'             => array( $this, 'erase_personal_data' ),
        );
        return $erasers;
    }

    /**
     * Anonymise (plutôt que supprimer) les données d'un utilisateur.
     *
     * - Trouve tous les visitor_hash liés au user_id
     * - Remplace visitor_hash par un hash aléatoire dans hits, sessions et scroll
     * - Met user_id à 0 et is_logged_in à 0
     * - Les métriques (durée, scroll, device, page…) sont conservées
     */
    public function erase_personal_data( $email_address, $page = 1 ) {
        global $wpdb;

        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return array(
                'items_removed'  => 0,
                'items_retained' => false,
                'messages'       => array(),
                'done'           => true,
            );
        }

        $table_hits   = $wpdb->prefix . 'statify_hits';
        $table_sess   = $wpdb->prefix . 'statify_sessions';
        $table_scroll = $wpdb->prefix . 'statify_scroll';

        // ── Récupère les identifiants liés à cet utilisateur ──────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $visitor_hashes = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT visitor_hash FROM {$table_hits}
             WHERE user_id = %d AND visitor_hash NOT LIKE 'anon_%%'",
            $user->ID
        ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $session_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT session_id FROM {$table_hits}
             WHERE user_id = %d",
            $user->ID
        ) );

        $items_anonymized = 0;

        // ── Anonymiser les hits ───────────────────────────────────────────────
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $hits_updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_hits}
             SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(id, %s), 256)),
                 user_id      = 0,
                 is_logged_in = 0,
                 referrer     = CASE WHEN referrer_domain != '' THEN referrer_domain ELSE '' END
             WHERE user_id = %d",
            wp_generate_uuid4(),
            $user->ID
        ) );
        $items_anonymized += (int) $hits_updated;

        // ── Anonymiser les sessions liées ─────────────────────────────────────
        if ( ! empty( $visitor_hashes ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $visitor_hashes ), '%s' ) );
            $anon_salt    = wp_generate_uuid4();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sess_updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table_sess}
                 SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(session_id, %s), 256))
                 WHERE visitor_hash IN ({$placeholders})",
                ...array_merge( array( $anon_salt ), $visitor_hashes )
            ) );
            $items_anonymized += (int) $sess_updated;

            // ── Anonymiser les scroll events ──────────────────────────────────
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $has_scroll = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_scroll ) );
            if ( $has_scroll ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $scroll_updated = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table_scroll}
                     SET visitor_hash = CONCAT('anon_', SHA2(CONCAT(id, %s), 256))
                     WHERE visitor_hash IN ({$placeholders})",
                    ...array_merge( array( $anon_salt ), $visitor_hashes )
                ) );
                $items_anonymized += (int) $scroll_updated;
            }
        }

        $messages = array();
        if ( $items_anonymized > 0 ) {
            $messages[] = sprintf(
                /* translators: %d: number of anonymized records */
                __( 'Statify : %d enregistrements anonymisés (données statistiques conservées de manière non identifiable).', 'statify' ),
                $items_anonymized
            );
        }

        return array(
            'items_removed'  => $items_anonymized,
            'items_retained' => $items_anonymized > 0,
            'messages'       => $messages,
            'done'           => true,
        );
    }
}
