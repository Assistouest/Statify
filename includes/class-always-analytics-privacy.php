<?php
namespace Always_Analytics;

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
class Always_Analytics_Privacy {

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
        // Nettoie l'ancienne entrée enregistrée sous un nom différent
        add_action( 'admin_init', array( $this, 'cleanup_old_policy_entry' ) );
        $this->add_privacy_policy_content();
    }

    /**
     * Supprime l'ancienne entrée "Always Analytics (Advanced Stats)" du guide WordPress.
     */
    public function cleanup_old_policy_entry() {
        $old_key = 'Always Analytics (Advanced Stats)';
        $policy_content = get_option( 'wp_privacy_policy_content', array() );
        if ( isset( $policy_content[ $old_key ] ) ) {
            unset( $policy_content[ $old_key ] );
            update_option( 'wp_privacy_policy_content', $policy_content );
        }
    }

    // ── Politique de confidentialité suggérée ──────────────────────────────────

    /**
     * Ajoute un texte de politique de confidentialité dans l'outil WordPress.
     * Le contenu est généré dynamiquement en fonction des options enregistrées.
     */
    public function add_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $options          = get_option( 'always_analytics_options', array() );
        $mode             = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
        $anon_ip          = ! empty( $options['anonymize_ip'] );
        $geo_on           = ! empty( $options['geo_enabled'] );
        $consent_on       = ! empty( $options['consent_enabled'] );
        $retention        = isset( $options['retention_days'] ) ? absint( $options['retention_days'] ) : 90;
        $window           = isset( $options['cookieless_window'] ) ? $options['cookieless_window'] : 'daily';
        $disable_tracking = ! empty( $options['disable_tracking'] );
        $is_cookieless    = ( 'cookieless' === $mode );

        // ── Durée de rétention lisible ─────────────────────────────────────────
        if ( 0 === $retention ) {
            $retention_label = __( 'indéfiniment (aucune limite configurée)', 'always-analytics' );
        } elseif ( $retention < 365 ) {
            $retention_label = sprintf( __( '%d jours', 'always-analytics' ), $retention );
        } elseif ( 365 === $retention ) {
            $retention_label = __( '1 an (365 jours)', 'always-analytics' );
        } else {
            $retention_label = sprintf( __( '%d jours', 'always-analytics' ), $retention );
        }

        // ── Fenêtre d'unicité lisible ──────────────────────────────────────────
        $window_label = ( 'session' === $window )
            ? __( 'durée de la session de navigation (onglet navigateur)', 'always-analytics' )
            : __( '24 heures (remis à zéro chaque jour à minuit UTC)', 'always-analytics' );

        // ══════════════════════════════════════════════════════════════════════
        // CONSTRUCTION DU CONTENU
        // ══════════════════════════════════════════════════════════════════════
        $c = '';

        // ── 1. Introduction ────────────────────────────────────────────────────
        $c .= '<h2>' . esc_html__( 'Mesure d\'audience (Always Analytics)', 'always-analytics' ) . '</h2>';

        if ( $disable_tracking ) {
            $c .= '<p>' . esc_html__( 'La collecte de statistiques est actuellement désactivée sur ce site. Aucune donnée de navigation n\'est collectée ni traitée par Always Analytics.', 'always-analytics' ) . '</p>';
            wp_add_privacy_policy_content( 'Always Analytics', $c );
            return;
        }

        $c .= '<p>' . esc_html__( 'Ce site utilise Always Analytics pour mesurer son audience. L\'objectif est de comprendre comment le site est consulté (pages les plus visitées, sources de trafic, comportement de navigation) afin d\'en améliorer le contenu et l\'expérience.', 'always-analytics' ) . '</p>';

        // ── 2. Base légale et consentement ─────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Base légale du traitement', 'always-analytics' ) . '</h3>';

        if ( $is_cookieless ) {
            $c .= '<p>' . esc_html__( 'Ce site utilise un mode de mesure sans cookie. Aucun fichier n\'est déposé sur votre appareil. L\'identification repose sur une empreinte anonyme et non persistante, calculée à partir de données techniques (adresse IP tronquée, navigateur, langue), valable uniquement pour ', 'always-analytics' )
                . esc_html( $window_label )
                . esc_html__( '. Ce type de mesure est exempté de l\'obligation de consentement par la CNIL (délibération n°2020-091) car il ne permet pas de vous suivre dans le temps ni entre différents sites.', 'always-analytics' )
                . '</p>';
        } elseif ( $consent_on ) {
            $c .= '<p>' . esc_html__( 'Ce site utilise des cookies de mesure d\'audience. Conformément au RGPD (article 7) et à la directive ePrivacy, ces cookies ne sont déposés qu\'après avoir recueilli votre consentement explicite via la bannière de cookies. Vous pouvez retirer ce consentement à tout moment en cliquant sur le lien « Gérer mes préférences » présent dans le pied de page du site.', 'always-analytics' ) . '</p>';
        } else {
            $c .= '<p>' . esc_html__( 'Ce site utilise des cookies de mesure d\'audience. La base légale de ce traitement est votre consentement (RGPD art. 7).', 'always-analytics' ) . '</p>';
        }

        // ── 3. Données collectées ──────────────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Données collectées', 'always-analytics' ) . '</h3>';
        $c .= '<p>' . esc_html__( 'À chaque visite, les informations suivantes sont enregistrées :', 'always-analytics' ) . '</p>';
        $c .= '<ul>';
        $c .= '<li>' . esc_html__( 'URL de la page consultée et horodatage', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Durée de consultation et profondeur de défilement (scroll)', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Type d\'appareil (ordinateur, tablette, mobile), navigateur et système d\'exploitation', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Source de trafic (page de provenance, paramètres UTM)', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Résolution d\'écran', 'always-analytics' ) . '</li>';

        if ( $geo_on ) {
            if ( $anon_ip ) {
                $c .= '<li>' . esc_html__( 'Pays et région d\'origine (déterminés à partir de l\'adresse IP tronquée — la géolocalisation est effectuée sur l\'IP anonymisée, sans possibilité d\'identifier précisément le visiteur)', 'always-analytics' ) . '</li>';
            } else {
                $c .= '<li>' . esc_html__( 'Pays et région d\'origine (déterminés à partir de l\'adresse IP complète, utilisée uniquement pour la géolocalisation puis écartée — non stockée en base de données)', 'always-analytics' ) . '</li>';
            }
        }

        $c .= '</ul>';

        // ── 4. Ce qui n'est PAS collecté ───────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Ce que nous ne collectons pas', 'always-analytics' ) . '</h3>';
        $c .= '<ul>';
        $c .= '<li>' . esc_html__( 'Votre adresse IP complète n\'est jamais enregistrée en base de données.', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Aucune donnée de contact (nom, adresse e-mail, téléphone).', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Aucun contenu saisi dans les formulaires.', 'always-analytics' ) . '</li>';
        $c .= '<li>' . esc_html__( 'Aucun profil publicitaire ni aucune donnée d\'intérêt ou de comportement hors de ce site n\'est constitué par Always Analytics.', 'always-analytics' ) . '</li>';
        $c .= '</ul>';

        // ── 5. Identifiant visiteur ────────────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Identification des visiteurs uniques', 'always-analytics' ) . '</h3>';

        if ( $is_cookieless ) {
            if ( 'session' === $window ) {
                $c .= '<p>' . esc_html__( 'Pour compter les visiteurs uniques sans cookie, le site calcule une empreinte numérique temporaire à partir de données techniques non personnelles (adresse IP tronquée, navigateur, langue de navigation). Cette empreinte est un identifiant cryptographique (hash SHA-256) irréversible : il est mathématiquement impossible de retrouver votre adresse IP ou votre identité à partir de cet identifiant. Il est valable uniquement le temps de votre session de navigation (onglet ouvert) et disparaît dès la fermeture de l\'onglet. Aucune persistance entre les visites.', 'always-analytics' ) . '</p>';
            } else {
                $c .= '<p>' . esc_html__( 'Pour compter les visiteurs uniques sans cookie, le site calcule une empreinte numérique à partir de données techniques non personnelles (adresse IP tronquée, navigateur, langue de navigation). Cette empreinte est un identifiant cryptographique (hash SHA-256) irréversible, valable 24 heures maximum et remis à zéro chaque jour à minuit UTC. Il est mathématiquement impossible de retrouver votre adresse IP ou votre identité à partir de cet identifiant.', 'always-analytics' ) . '</p>';
            }
        } else {
            $c .= '<p>' . esc_html__( 'En mode cookie, un identifiant de visite anonyme est stocké dans un cookie sur votre appareil. Cet identifiant est un hash SHA-256 non réversible : il ne contient aucune information personnelle et ne permet pas de vous identifier directement. Il est utilisé uniquement pour distinguer les visiteurs uniques sur ce site et compter les sessions de navigation.', 'always-analytics' ) . '</p>';
        }

        // ── 6. Adresse IP et anonymisation ─────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Traitement de l\'adresse IP', 'always-analytics' ) . '</h3>';

        if ( $anon_ip ) {
            $c .= '<p>' . esc_html__( 'Votre adresse IP est automatiquement tronquée dès réception, avant tout traitement : le dernier groupe de chiffres est masqué (ex : 192.168.1.42 devient 192.168.1.0 pour IPv4). Cette adresse tronquée ne permet plus d\'identifier précisément un foyer ou un individu. Elle est ensuite utilisée pour calculer l\'identifiant anonyme décrit ci-dessus, puis écartée. Elle n\'est jamais enregistrée en base de données.', 'always-analytics' ) . '</p>';
        } else {
            $c .= '<p>' . esc_html__( 'Votre adresse IP est utilisée en mémoire vive pour calculer l\'identifiant de visite anonyme, puis immédiatement écartée. Elle n\'est jamais enregistrée en base de données. Nous vous recommandons d\'activer l\'anonymisation IP dans les réglages du plugin pour une protection maximale.', 'always-analytics' ) . '</p>';
        }

        // ── 7. Cookies ─────────────────────────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Cookies déposés', 'always-analytics' ) . '</h3>';

        if ( $is_cookieless ) {
            $c .= '<p>' . esc_html__( 'Aucun cookie de tracking n\'est déposé sur votre appareil. Ce site utilise le mode de mesure d\'audience sans cookie, conforme à l\'exemption de consentement de la CNIL.', 'always-analytics' ) . '</p>';
            $c .= '<p>' . esc_html__( 'Un stockage de session temporaire (sessionStorage du navigateur) peut être utilisé pour regrouper vos pages vues en une seule visite cohérente. Ce stockage disparaît automatiquement à la fermeture de l\'onglet et n\'est jamais transmis à notre serveur.', 'always-analytics' ) . '</p>';
        } else {
            $c .= '<p>' . esc_html__( 'Ce site dépose un cookie de mesure d\'audience sur votre appareil', 'always-analytics' );
            if ( $consent_on ) {
                $c .= ' ' . esc_html__( 'après avoir recueilli votre consentement', 'always-analytics' );
            }
            $c .= '.</p>';
            $c .= '<table style="border-collapse:collapse;width:100%;font-size:13px;">';
            $c .= '<thead><tr style="background:#f5f5f5;">'
                . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Nom', 'always-analytics' ) . '</th>'
                . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Finalité', 'always-analytics' ) . '</th>'
                . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Durée', 'always-analytics' ) . '</th>'
                . '</tr></thead><tbody>';
            $c .= '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;"><code>aa_visitor</code></td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . esc_html__( 'Identifiant visiteur unique anonyme (hash SHA-256 non réversible) pour compter les visites uniques', 'always-analytics' ) . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . esc_html__( '13 mois maximum', 'always-analytics' ) . '</td>'
                . '</tr>';
            $c .= '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;"><code>aa_session</code></td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . esc_html__( 'Identifiant de session pour regrouper les pages vues d\'une même visite', 'always-analytics' ) . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . esc_html__( 'Session (disparaît à la fermeture du navigateur)', 'always-analytics' ) . '</td>'
                . '</tr>';
            $c .= '</tbody></table>';
        }

        // ── 8. Durée de conservation ───────────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Durée de conservation', 'always-analytics' ) . '</h3>';

        if ( 0 === $retention ) {
            $c .= '<p>' . esc_html__( 'Les données de visite brutes sont conservées sans limite de durée. Les statistiques agrégées (nombre de visites par page, taux de rebond, etc.) sont conservées indéfiniment sous forme anonymisée.', 'always-analytics' ) . '</p>';
        } else {
            $c .= '<p>' . sprintf(
                /* translators: %s: retention period label */
                esc_html__( 'Les données de visite brutes sont automatiquement anonymisées après %s. L\'anonymisation consiste à remplacer l\'identifiant du visiteur par une valeur aléatoire irréversible : les statistiques (durée, scroll, type d\'appareil, page visitée) sont conservées, mais il devient impossible de les relier à un individu ou à une session spécifique.', 'always-analytics' ),
                esc_html( $retention_label )
            ) . '</p>';
            $c .= '<p>' . esc_html__( 'Les statistiques agrégées (nombre de visiteurs uniques par jour, taux de rebond, pages les plus vues, etc.) sont conservées indéfiniment sous forme non identifiable.', 'always-analytics' ) . '</p>';
        }

        // ── 9. Transfert de données ────────────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Hébergement et transferts', 'always-analytics' ) . '</h3>';
        $c .= '<p>' . esc_html__( 'Toutes les données collectées par Always Analytics sont stockées directement sur le serveur hébergeant ce site, dans sa base de données WordPress. Le traitement est entièrement local — aucune donnée de mesure d\'audience n\'est transmise à des serveurs externes par ce plugin.', 'always-analytics' ) . '</p>';

        // ── 10. Droits ─────────────────────────────────────────────────────────
        $c .= '<h3>' . esc_html__( 'Vos droits', 'always-analytics' ) . '</h3>';
        $c .= '<p>' . esc_html__( 'Conformément au RGPD (articles 15 à 22), vous disposez des droits suivants concernant vos données :', 'always-analytics' ) . '</p>';
        $c .= '<ul>';
        $c .= '<li><strong>' . esc_html__( 'Droit d\'accès', 'always-analytics' ) . '</strong> — '
            . esc_html__( 'vous pouvez demander quelles données de visite sont associées à votre compte utilisateur (si vous êtes connecté au moment de votre visite).', 'always-analytics' ) . '</li>';
        $c .= '<li><strong>' . esc_html__( 'Droit à l\'effacement (droit à l\'oubli)', 'always-analytics' ) . '</strong> — '
            . esc_html__( 'vous pouvez demander l\'anonymisation de vos données de visite. Les données seront rendues non identifiables (visitor_hash remplacé par une valeur aléatoire) plutôt que supprimées, afin de préserver l\'intégrité des statistiques globales du site.', 'always-analytics' ) . '</li>';
        $c .= '<li><strong>' . esc_html__( 'Droit d\'opposition', 'always-analytics' ) . '</strong> — ';
        if ( $is_cookieless ) {
            $c .= esc_html__( 'la mesure d\'audience sans cookie ne nécessitant pas de consentement, le droit d\'opposition ne s\'applique pas au sens strict. Vous pouvez toutefois utiliser un bloqueur de traqueurs ou le mode navigation privée pour ne pas être comptabilisé.', 'always-analytics' );
        } else {
            $c .= esc_html__( 'vous pouvez retirer votre consentement à tout moment en cliquant sur le bouton de gestion des cookies présent en pied de page.', 'always-analytics' );
        }
        $c .= '</li>';
        $c .= '</ul>';
        $c .= '<p>' . esc_html__( 'Pour exercer ces droits, contactez l\'administrateur de ce site via la page de contact ou en utilisant l\'outil de demande de données personnelles disponible dans votre espace utilisateur WordPress.', 'always-analytics' ) . '</p>';

        wp_add_privacy_policy_content( 'Always Analytics', $c );
    }

    // ── Purge / Anonymisation automatique ─────────────────────────────────────

    /**
     * Anonymise les données brutes plus anciennes que la période de rétention.
     *
     * Flux :
     * 1. Agrège les stats dans aa_daily (UV, sessions, durée, scroll, bounce)
     * 2. Anonymise les hits : visitor_hash → hash aléatoire, user_id → 0
     * 3. Anonymise les sessions : visitor_hash → hash aléatoire
     * 4. Anonymise les scroll events : visitor_hash → hash aléatoire
     *
     * Résultat : les données brutes restent exploitables pour les distributions
     * (durées, devices, heures, scroll…) mais ne sont plus liables à un individu.
     * Les comptages UV/sessions reposent sur aa_daily.
     */
    public function purge_old_data() {
        global $wpdb;

        $options        = get_option( 'always_analytics_options', array() );
        $retention_days = isset( $options['retention_days'] ) ? absint( $options['retention_days'] ) : 90;

        if ( 0 === $retention_days ) {
            return; // 0 = rétention illimitée
        }

        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
        $cutoff_day  = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

        do_action( 'always_analytics_before_purge', $cutoff_date );

        $table_hits     = $wpdb->prefix . 'aa_hits';
        $table_sessions = $wpdb->prefix . 'aa_sessions';
        $table_scroll   = $wpdb->prefix . 'aa_scroll';
        $table_daily    = $wpdb->prefix . 'aa_daily';

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

        do_action( 'always_analytics_after_purge', $cutoff_date );
    }

    /**
     * Agrège un jour donné dans aa_daily.
     */
    private function aggregate_day( $day ) {
        global $wpdb;
        $table_hits  = $wpdb->prefix . 'aa_hits';
        $table_daily = $wpdb->prefix . 'aa_daily';
        $t_sess      = $wpdb->prefix . 'aa_sessions';

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
        $table_hits  = $wpdb->prefix . 'aa_hits';
        $table_daily = $wpdb->prefix . 'aa_daily';
        $t_sess      = $wpdb->prefix . 'aa_sessions';

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
        $exporters['always-analytics'] = array(
            'exporter_friendly_name' => __( 'Always Analytics — Données de visite', 'always-analytics' ),
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

        $table_hits = $wpdb->prefix . 'aa_hits';
        $table_sess = $wpdb->prefix . 'aa_sessions';
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
                array( 'name' => __( 'Page', 'always-analytics' ),       'value' => $hit->page_url ),
                array( 'name' => __( 'Titre', 'always-analytics' ),      'value' => $hit->page_title ),
                array( 'name' => __( 'Date', 'always-analytics' ),       'value' => $hit->hit_at ),
                array( 'name' => __( 'Appareil', 'always-analytics' ),   'value' => $hit->device_type ),
                array( 'name' => __( 'Navigateur', 'always-analytics' ), 'value' => $hit->browser ),
                array( 'name' => __( 'OS', 'always-analytics' ),         'value' => $hit->os ),
                array( 'name' => __( 'Pays', 'always-analytics' ),       'value' => $hit->country_code ),
                array( 'name' => __( 'Écran', 'always-analytics' ),      'value' => $hit->screen_width . 'x' . $hit->screen_height ),
                array( 'name' => __( 'Référent', 'always-analytics' ),   'value' => $hit->referrer_domain ),
            );
            if ( $hit->utm_source ) {
                $item_data[] = array( 'name' => 'UTM Source',   'value' => $hit->utm_source );
                $item_data[] = array( 'name' => 'UTM Medium',   'value' => $hit->utm_medium );
                $item_data[] = array( 'name' => 'UTM Campaign', 'value' => $hit->utm_campaign );
            }
            $data[] = array(
                'group_id'    => 'aa-hits',
                'group_label' => __( 'Always Analytics — Pages visitées', 'always-analytics' ),
                'item_id'     => 'aa-hit-' . md5( $hit->hit_at . $hit->page_url ),
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
                        'group_id'    => 'aa-sessions',
                        'group_label' => __( 'Always Analytics — Sessions de visite', 'always-analytics' ),
                        'item_id'     => 'aa-sess-' . md5( $s->started_at ),
                        'data'        => array(
                            array( 'name' => __( 'Début', 'always-analytics' ),       'value' => $s->started_at ),
                            array( 'name' => __( 'Durée (s)', 'always-analytics' ),   'value' => $dur ),
                            array( 'name' => __( 'Pages vues', 'always-analytics' ),  'value' => $s->page_count ),
                            array( 'name' => __( 'Page d\'entrée', 'always-analytics' ), 'value' => $s->entry_page ),
                            array( 'name' => __( 'Page de sortie', 'always-analytics' ), 'value' => $s->exit_page ),
                            array( 'name' => __( 'Scroll max (%)', 'always-analytics' ), 'value' => $s->max_scroll_depth ),
                            array( 'name' => __( 'Rebond', 'always-analytics' ),      'value' => $s->is_bounce ? __( 'Oui', 'always-analytics' ) : __( 'Non', 'always-analytics' ) ),
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
        $erasers['always-analytics'] = array(
            'eraser_friendly_name' => __( 'Always Analytics — Données de visite', 'always-analytics' ),
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

        $table_hits   = $wpdb->prefix . 'aa_hits';
        $table_sess   = $wpdb->prefix . 'aa_sessions';
        $table_scroll = $wpdb->prefix . 'aa_scroll';

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
                __( 'Always Analytics : %d enregistrements anonymisés (données statistiques conservées de manière non identifiable).', 'always-analytics' ),
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
