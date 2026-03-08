<?php
/**
 * Settings view — Advanced Stats.
 * Custom HTML rows (no do_settings_fields) for full layout control.
 *
 * @package Statify
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$o   = get_option( 'statify_options', array() );
$mode        = isset( $o['tracking_mode'] )  ? $o['tracking_mode']  : 'cookieless';
$retention   = isset( $o['retention_days'] ) ? absint( $o['retention_days'] ) : 90;
$consent_on  = ! empty( $o['consent_enabled'] );
$anon_ip     = ! empty( $o['anonymize_ip'] );
$geo_on      = ! empty( $o['geo_enabled'] );

$need_consent = ( 'cookie' === $mode && ! $consent_on );
$ret_limit    = 390; // 13 months — CNIL threshold
$ret_warn     = ( 0 === $retention || $retention > $ret_limit );

// DB stats
global $wpdb;
$db_hits     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}statify_hits" ); // phpcs:ignore
$db_sessions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}statify_sessions" ); // phpcs:ignore
$db_scroll   = 0;
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'statify_scroll' ) ) ) {
    $db_scroll = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}statify_scroll" ); // phpcs:ignore
}
$db_daily  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}statify_daily" ); // phpcs:ignore
$db_anon   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}statify_hits WHERE visitor_hash LIKE 'anon_%'" ); // phpcs:ignore

// WP roles
$wp_roles       = wp_roles()->get_names();
$excluded_roles = isset( $o['excluded_roles'] ) ? (array) $o['excluded_roles'] : array();

// Field helpers
function as_val( $o, $k, $d = '' ) { return isset( $o[$k] ) ? $o[$k] : $d; }
function as_checked( $o, $k ) { return ! empty( $o[$k] ) ? 'checked' : ''; }
?>
<div class="wrap statify-wrap">

    <!-- Header -->
    <div class="statify-header">
        <h1>
            <img src="<?php echo esc_url( STATIFY_PLUGIN_URL . 'Statify.svg' ); ?>" alt="" style="width:32px;height:32px;vertical-align:middle;">
            <?php esc_html_e( 'Réglages', 'statify' ); ?>
        </h1>
        <div class="statify-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify' ) ); ?>" class="statify-back-btn">← <?php esc_html_e( 'Dashboard', 'statify' ); ?></a>
            <span class="as-version-badge">v<?php echo esc_html( STATIFY_VERSION ); ?></span>
        </div>
    </div>

    <!-- RGPD Status Banner -->
    <?php
    $rgpd_issues = array();
    if ( $need_consent ) {
        $rgpd_issues[] = array( 'label' => __( 'Consentement requis', 'statify' ), 'mod' => 'mod-danger', 'tab' => 'consent' );
    }
    if ( ! $anon_ip ) {
        $rgpd_issues[] = array( 'label' => __( 'IP non anonymisée', 'statify' ), 'mod' => 'mod-warn', 'tab' => 'privacy' );
    }
    if ( $ret_warn ) {
        $ret_label = ( 0 === $retention )
            ? __( 'Rétention illimitée', 'statify' )
            : sprintf( __( 'Rétention %d j (> 13 mois)', 'statify' ), $retention );
        $rgpd_issues[] = array( 'label' => $ret_label, 'mod' => 'mod-warn', 'tab' => 'privacy' );
    }
    $rgpd_ok = empty( $rgpd_issues );
    ?>
    <div class="as-status-banner">
        <span class="as-status-banner__icon"><?php echo $rgpd_ok ? '🛡️' : ( 'cookie' === $mode ? '🍪' : '⚙️' ); ?></span>
        <div class="as-status-banner__body">
            <strong><?php esc_html_e( 'Statut RGPD', 'statify' ); ?></strong>
            <span><?php echo 'cookie' === $mode ? esc_html__( 'Mode cookie', 'statify' ) : esc_html__( 'Mode sans cookie', 'statify' ); ?></span>
        </div>
        <div class="as-status-banner__chips">
            <?php if ( $rgpd_ok ) : ?>
                <span class="as-chip mod-ok">✓ <?php esc_html_e( 'Conforme RGPD', 'statify' ); ?></span>
            <?php else : ?>
                <?php foreach ( $rgpd_issues as $issue ) : ?>
                    <a href="#" class="as-chip <?php echo esc_attr( $issue['mod'] ); ?> statify-settings-tab-link" data-tab="<?php echo esc_attr( $issue['tab'] ); ?>">
                        ⚠ <?php echo esc_html( $issue['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab bar -->
    <div class="as-tabs">
        <button class="as-tab active" data-tab="tracking">📊 <?php esc_html_e( 'Tracking', 'statify' ); ?></button>
        <button class="as-tab" data-tab="privacy">🔐 <?php esc_html_e( 'Confidentialité', 'statify' ); ?></button>
        <button class="as-tab <?php echo $need_consent ? 'mod-alert' : ''; ?>" data-tab="consent">✋ <?php esc_html_e( 'Consentement', 'statify' ); ?><?php if ( $need_consent ) : ?><span class="as-tab-dot"></span><?php endif; ?></button>
        <button class="as-tab" data-tab="geo">🌍 <?php esc_html_e( 'Géolocalisation', 'statify' ); ?></button>
        <button class="as-tab" data-tab="performance">⚡ <?php esc_html_e( 'Performance', 'statify' ); ?></button>
        <button class="as-tab" data-tab="maintenance">🗄️ <?php esc_html_e( 'Maintenance', 'statify' ); ?></button>
        <button class="as-tab" data-tab="rgpd">📋 <?php esc_html_e( 'Conformité RGPD', 'statify' ); ?></button>
    </div>

    <form method="post" action="options.php" id="statify-settings-form">
        <?php settings_fields( 'statify_settings' ); ?>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — TRACKING
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel active" data-panel="tracking">
            <div class="as-card">
                <div class="as-card__head">
                    <h2>📊 <?php esc_html_e( 'Tracking', 'statify' ); ?></h2>
                    <p><?php esc_html_e( 'Contrôlez si et comment Statify collecte les données de vos visiteurs.', 'statify' ); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Désactiver le tracking', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Suspend temporairement toute collecte de données.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="statify_options[disable_tracking]" value="1" <?php echo as_checked( $o, 'disable_tracking' ); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e( 'Désactiver', 'statify' ); ?></span>
                        </label>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Mode de tracking', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Sans cookie : hash journalier anonyme, éligible exemption CNIL. Avec cookie : suivi multi-jours, consentement requis.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div class="as-radio-group">
                            <label class="as-radio">
                                <input type="radio" name="statify_options[tracking_mode]" value="cookieless" <?php checked( $mode, 'cookieless' ); ?>>
                                <span class="as-radio__box"></span>
                                <span>
                                    <strong><?php esc_html_e( 'Sans cookie', 'statify' ); ?></strong>
                                    <small><?php esc_html_e( 'Respectueux de la vie privée, pas de consentement requis', 'statify' ); ?></small>
                                </span>
                            </label>
                            <label class="as-radio">
                                <input type="radio" name="statify_options[tracking_mode]" value="cookie" <?php checked( $mode, 'cookie' ); ?>>
                                <span class="as-radio__box"></span>
                                <span>
                                    <strong><?php esc_html_e( 'Avec cookie', 'statify' ); ?></strong>
                                    <small><?php esc_html_e( 'Meilleur suivi multi-jours, bannière de consentement nécessaire', 'statify' ); ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Rôles exclus', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Les visites de ces rôles ne seront pas comptabilisées.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div class="as-checkbox-group">
                            <?php foreach ( $wp_roles as $role_key => $role_name ) : ?>
                            <label class="as-checkbox">
                                <input type="checkbox" name="statify_options[excluded_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $excluded_roles, true ) ); ?>>
                                <span class="as-checkbox__box"></span>
                                <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'IPs exclues', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Une adresse IP par ligne. Ces IPs seront ignorées lors du tracking.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <textarea name="statify_options[excluded_ips]" rows="4" class="as-textarea"><?php echo esc_textarea( as_val( $o, 'excluded_ips' ) ); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — CONFIDENTIALITÉ
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel" data-panel="privacy">
            <div class="as-card">
                <div class="as-card__head">
                    <h2>🔐 <?php esc_html_e( 'Confidentialité & RGPD', 'statify' ); ?></h2>
                    <p><?php esc_html_e( 'Protection des données personnelles et durée de conservation.', 'statify' ); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Anonymiser les IPs', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Masque le dernier octet IPv4 et les 80 derniers bits IPv6 avant le hachage.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="statify_options[anonymize_ip]" value="1" <?php echo as_checked( $o, 'anonymize_ip' ); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e( 'Activer', 'statify' ); ?></span>
                        </label>
                    </div>
                </div>

                <div class="as-row <?php echo $ret_warn ? 'as-row--warn' : ''; ?>">
                    <div class="as-row__label">
                        <span class="as-row__title">
                            <?php esc_html_e( 'Durée de rétention', 'statify' ); ?>
                            <?php if ( $ret_warn ) : ?><span class="as-inline-badge mod-warn">⚠ CNIL</span><?php endif; ?>
                        </span>
                        <span class="as-row__desc"><?php esc_html_e( 'Après cette période, les données brutes sont anonymisées (les agrégats sont conservés indéfiniment). La CNIL recommande 13 mois maximum.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="statify_options[retention_days]" class="as-select">
                            <option value="30"  <?php selected( $retention, 30  ); ?>>30 <?php esc_html_e( 'jours', 'statify' ); ?></option>
                            <option value="90"  <?php selected( $retention, 90  ); ?>>90 <?php esc_html_e( 'jours', 'statify' ); ?></option>
                            <option value="180" <?php selected( $retention, 180 ); ?>>180 <?php esc_html_e( 'jours', 'statify' ); ?></option>
                            <option value="365" <?php selected( $retention, 365 ); ?>>1 <?php esc_html_e( 'an', 'statify' ); ?></option>
                            <option value="0"   <?php selected( $retention, 0   ); ?>><?php esc_html_e( 'Illimité', 'statify' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Supprimer à la désinstallation', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Efface toutes les tables et options lors de la désinstallation du plugin.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="statify_options[delete_on_uninstall]" value="1" <?php echo as_checked( $o, 'delete_on_uninstall' ); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e( 'Activer', 'statify' ); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="as-note">
                <span class="as-note__icon">💡</span>
                <div>
                    <strong><?php esc_html_e( 'Anonymisation, pas suppression', 'statify' ); ?></strong>
                    <?php esc_html_e( 'Après la période de rétention, le visitor_hash est remplacé par un hash aléatoire, le user_id effacé, le referrer réduit au domaine. Les métriques (durée, scroll, device, page) sont conservées indéfiniment pour les statistiques.', 'statify' ); ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — CONSENTEMENT
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel" data-panel="consent" id="tab-consent">
            <div class="as-card">
                <div class="as-card__head">
                    <h2>
                        ✋ <?php esc_html_e( 'Bannière de consentement', 'statify' ); ?>
                        <?php if ( $need_consent ) : ?>
                            <span class="as-inline-badge mod-danger"><?php esc_html_e( 'Action requise', 'statify' ); ?></span>
                        <?php elseif ( $consent_on ) : ?>
                            <span class="as-inline-badge mod-ok"><?php esc_html_e( 'Active', 'statify' ); ?></span>
                        <?php endif; ?>
                    </h2>
                    <p><?php esc_html_e( 'Bandeau affiché aux visiteurs pour recueillir leur consentement au tracking par cookie.', 'statify' ); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Activer la bannière', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Affiche un bandeau de consentement aux visiteurs. Obligatoire en mode cookie.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="statify_options[consent_enabled]" value="1" <?php echo as_checked( $o, 'consent_enabled' ); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e( 'Activer', 'statify' ); ?></span>
                        </label>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Message', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Texte affiché dans le bandeau de consentement.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <input type="text" name="statify_options[consent_message]" value="<?php echo esc_attr( as_val( $o, 'consent_message', __( 'Ce site utilise des cookies pour analyser le trafic. Acceptez-vous ?', 'statify' ) ) ); ?>" class="as-input-full">
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Boutons', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Libellé des boutons Accepter et Refuser.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control as-row__control--inline">
                        <div class="as-input-group">
                            <label class="as-input-group__label"><?php esc_html_e( 'Accepter', 'statify' ); ?></label>
                            <input type="text" name="statify_options[consent_accept]" value="<?php echo esc_attr( as_val( $o, 'consent_accept', __( 'Accepter', 'statify' ) ) ); ?>" class="as-input">
                        </div>
                        <div class="as-input-group">
                            <label class="as-input-group__label"><?php esc_html_e( 'Refuser', 'statify' ); ?></label>
                            <input type="text" name="statify_options[consent_decline]" value="<?php echo esc_attr( as_val( $o, 'consent_decline', __( 'Refuser', 'statify' ) ) ); ?>" class="as-input">
                        </div>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Couleurs', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Personnalisez les couleurs du bandeau.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control as-row__control--inline">
                        <div class="as-color-group">
                            <label><?php esc_html_e( 'Fond', 'statify' ); ?></label>
                            <input type="color" name="statify_options[consent_bg_color]" value="<?php echo esc_attr( as_val( $o, 'consent_bg_color', '#1a1a2e' ) ); ?>" class="as-color">
                        </div>
                        <div class="as-color-group">
                            <label><?php esc_html_e( 'Texte', 'statify' ); ?></label>
                            <input type="color" name="statify_options[consent_text_color]" value="<?php echo esc_attr( as_val( $o, 'consent_text_color', '#ffffff' ) ); ?>" class="as-color">
                        </div>
                        <div class="as-color-group">
                            <label><?php esc_html_e( 'Bouton', 'statify' ); ?></label>
                            <input type="color" name="statify_options[consent_btn_color]" value="<?php echo esc_attr( as_val( $o, 'consent_btn_color', '#6c63ff' ) ); ?>" class="as-color">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — GÉOLOCALISATION
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel" data-panel="geo">
            <div class="as-card">
                <div class="as-card__head">
                    <h2>🌍 <?php esc_html_e( 'Géolocalisation', 'statify' ); ?></h2>
                    <p><?php esc_html_e( 'Résolution des adresses IP en localisation géographique pour enrichir vos statistiques.', 'statify' ); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Activer la géolocalisation', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Résout les IPs en pays/ville lors du tracking.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="statify_options[geo_enabled]" value="1" <?php echo as_checked( $o, 'geo_enabled' ); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e( 'Activer', 'statify' ); ?></span>
                        </label>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Fournisseur', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Natif : base IP intégrée, sans API. MaxMind : précision avancée avec GeoLite2.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="statify_options[geo_provider]" class="as-select">
                            <option value="native"  <?php selected( as_val( $o, 'geo_provider', 'native' ), 'native'  ); ?>><?php esc_html_e( 'Natif (sans clé API)', 'statify' ); ?></option>
                            <option value="maxmind" <?php selected( as_val( $o, 'geo_provider', 'native' ), 'maxmind' ); ?>><?php esc_html_e( 'MaxMind GeoLite2', 'statify' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Chemin base MaxMind', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Chemin absolu vers le fichier GeoLite2-City.mmdb sur votre serveur.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <input type="text" name="statify_options[maxmind_db_path]" value="<?php echo esc_attr( as_val( $o, 'maxmind_db_path' ) ); ?>" class="as-input-full" placeholder="/var/www/geoip/GeoLite2-City.mmdb">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — PERFORMANCE
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel" data-panel="performance">
            <div class="as-card">
                <div class="as-card__head">
                    <h2>⚡ <?php esc_html_e( 'Performance & Filtrage', 'statify' ); ?></h2>
                    <p><?php esc_html_e( 'Cache des requêtes, filtrage des bots et format d\'export.', 'statify' ); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Durée du cache', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Durée en secondes du cache des résultats API (60 – 3600 s).', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div class="as-input-suffix">
                            <input type="number" name="statify_options[cache_ttl]" value="<?php echo esc_attr( as_val( $o, 'cache_ttl', 300 ) ); ?>" min="60" max="3600" class="as-input-number">
                            <span class="as-input-suffix__unit"><?php esc_html_e( 'secondes', 'statify' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Filtrage des bots', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Normal : filtre les bots connus. Strict : filtre plus agressif. Désactivé : tout enregistre.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="statify_options[bot_filter_mode]" class="as-select">
                            <option value="normal" <?php selected( as_val( $o, 'bot_filter_mode', 'normal' ), 'normal' ); ?>><?php esc_html_e( 'Normal', 'statify' ); ?></option>
                            <option value="strict" <?php selected( as_val( $o, 'bot_filter_mode', 'normal' ), 'strict' ); ?>><?php esc_html_e( 'Strict', 'statify' ); ?></option>
                            <option value="off"    <?php selected( as_val( $o, 'bot_filter_mode', 'normal' ), 'off'    ); ?>><?php esc_html_e( 'Désactivé', 'statify' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e( 'Format d\'export', 'statify' ); ?></span>
                        <span class="as-row__desc"><?php esc_html_e( 'Format utilisé par défaut lors des exports de données.', 'statify' ); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="statify_options[export_format]" class="as-select">
                            <option value="csv"  <?php selected( as_val( $o, 'export_format', 'csv' ), 'csv'  ); ?>>CSV</option>
                            <option value="json" <?php selected( as_val( $o, 'export_format', 'csv' ), 'json' ); ?>>JSON</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save bar -->
        <div class="as-save-bar" id="statify-save-bar">
            <?php submit_button( __( 'Enregistrer les réglages', 'statify' ), 'primary', 'submit', false, array( 'class' => 'as-save-btn' ) ); ?>
        </div>

    </form><!-- /form -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB — MAINTENANCE  (outside form)
    ═══════════════════════════════════════════════════════════════════════ -->
    <div class="as-panel" data-panel="maintenance">
        <div class="statify-kpis">
            <div class="statify-kpi-card"><div class="statify-kpi-value"><?php echo esc_html( number_format_i18n( $db_hits ) ); ?></div><div class="statify-kpi-label"><?php esc_html_e( 'Hits', 'statify' ); ?></div></div>
            <div class="statify-kpi-card"><div class="statify-kpi-value"><?php echo esc_html( number_format_i18n( $db_sessions ) ); ?></div><div class="statify-kpi-label"><?php esc_html_e( 'Sessions', 'statify' ); ?></div></div>
            <div class="statify-kpi-card"><div class="statify-kpi-value"><?php echo esc_html( number_format_i18n( $db_scroll ) ); ?></div><div class="statify-kpi-label"><?php esc_html_e( 'Scroll events', 'statify' ); ?></div></div>
            <div class="statify-kpi-card"><div class="statify-kpi-value"><?php echo esc_html( number_format_i18n( $db_daily ) ); ?></div><div class="statify-kpi-label"><?php esc_html_e( 'Agrégats', 'statify' ); ?></div></div>
            <div class="statify-kpi-card"><div class="statify-kpi-value" style="color:var(--statify-success)"><?php echo esc_html( number_format_i18n( $db_anon ) ); ?></div><div class="statify-kpi-label"><?php esc_html_e( 'Hits anonymisés', 'statify' ); ?></div></div>
        </div>

        <?php if ( $db_anon > 0 ) : ?>
        <div class="as-note" style="margin-bottom:24px;">
            <span class="as-note__icon">✓</span>
            <div><?php printf( esc_html__( '%s hits anonymisés — statistiques conservées, identité effacée.', 'statify' ), '<strong>' . esc_html( number_format_i18n( $db_anon ) ) . '</strong>' ); ?></div>
        </div>
        <?php endif; ?>

        <div class="as-card">
            <div class="as-card__head">
                <h2>🔄 <?php esc_html_e( 'Anonymisation manuelle', 'statify' ); ?></h2>
                <p><?php esc_html_e( 'Déclenche immédiatement l\'anonymisation des données dépassant la période de rétention (normalement géré par wp-cron).', 'statify' ); ?></p>
            </div>
            <div class="as-card__body">
                <button id="statify-purge-btn" class="button button-secondary"
                        onclick="return confirm('<?php esc_attr_e( 'Cela va anonymiser les données plus anciennes que la période de rétention. Continuer ?', 'statify' ); ?>');"
                        style="border-radius:7px;">
                    🔄 <?php esc_html_e( 'Lancer l\'anonymisation', 'statify' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB — CONFORMITÉ RGPD
    ═══════════════════════════════════════════════════════════════════════ -->
    <div class="as-panel" data-panel="rgpd">
        <div class="as-card">
            <div class="as-card__head">
                <h2>📋 <?php esc_html_e( 'Conformité RGPD', 'statify' ); ?></h2>
                <p><?php esc_html_e( 'Vérification automatique des points de conformité selon les recommandations CNIL.', 'statify' ); ?></p>
            </div>
            <div class="as-checklist">

                <?php
                $consent_ok  = ( 'cookie' === $mode && $consent_on ) || 'cookieless' === $mode;
                $rows = array(
                    array(
                        'ok'     => true,
                        'label'  => __( 'IP non stockée', 'statify' ),
                        'detail' => __( 'L\'IP est utilisée en mémoire pour le hash/géoloc, jamais écrite en base.', 'statify' ),
                        'badge'  => __( 'Conforme', 'statify' ),
                        'bmod'   => '',
                    ),
                    array(
                        'ok'     => $anon_ip,
                        'label'  => __( 'Anonymisation IP', 'statify' ),
                        'detail' => __( 'Tronque le dernier octet IPv4 avant le hachage et la géoloc.', 'statify' ),
                        'badge'  => $anon_ip ? __( 'Activé', 'statify' ) : __( 'Désactivé', 'statify' ),
                        'bmod'   => $anon_ip ? '' : 'mod-warn',
                    ),
                    array(
                        'ok'     => true,
                        'label'  => __( 'Hash non réversible', 'statify' ),
                        'detail' => __( 'SHA-256 irréversible, pas de table hash → identité.', 'statify' ),
                        'badge'  => __( 'Conforme', 'statify' ),
                        'bmod'   => '',
                    ),
                    array(
                        'ok'     => ! $ret_warn,
                        'label'  => __( 'Rétention configurable', 'statify' ),
                        'detail' => $ret_warn
                            ? ( 0 === $retention ? __( 'Illimité — CNIL recommande 13 mois max.', 'statify' ) : sprintf( __( '%d j dépasse le seuil CNIL (390 j).', 'statify' ), $retention ) )
                            : __( 'Données anonymisées après la période, agrégats conservés.', 'statify' ),
                        'badge'  => 0 === $retention ? __( 'Illimité', 'statify' ) : esc_html( $retention . 'j' ),
                        'bmod'   => $ret_warn ? 'mod-warn' : '',
                    ),
                    array(
                        'ok'     => true,
                        'label'  => __( 'Export / Effacement WP', 'statify' ),
                        'detail' => __( 'Outils → Exporter/Effacer les données personnelles.', 'statify' ),
                        'badge'  => __( 'Conforme', 'statify' ),
                        'bmod'   => '',
                    ),
                    array(
                        'ok'     => true,
                        'label'  => __( 'Politique de confidentialité', 'statify' ),
                        'detail' => __( 'Texte suggéré dans Réglages → Confidentialité WP.', 'statify' ),
                        'badge'  => __( 'Conforme', 'statify' ),
                        'bmod'   => '',
                    ),
                    array(
                        'ok'     => $consent_ok,
                        'label'  => __( 'Consentement', 'statify' ),
                        'detail' => $need_consent
                            ? __( 'Mode cookie SANS bannière — activez-la dans l\'onglet Consentement.', 'statify' )
                            : ( 'cookieless' === $mode ? __( 'Pas de cookie, hash journalier éligible à l\'exemption CNIL.', 'statify' ) : __( 'Tracking bloqué avant acceptation.', 'statify' ) ),
                        'badge'  => $need_consent ? __( 'Consentement requis', 'statify' ) : ( 'cookieless' === $mode ? __( 'Exemption CNIL', 'statify' ) : __( 'Bannière active', 'statify' ) ),
                        'bmod'   => $need_consent ? 'mod-danger' : '',
                    ),
                    array(
                        'ok'     => true,
                        'label'  => __( 'Durée des cookies', 'statify' ),
                        'detail' => __( 'Visiteur : 13 mois. Consentement : 6 mois. Conforme CNIL.', 'statify' ),
                        'badge'  => __( 'CNIL OK', 'statify' ),
                        'bmod'   => '',
                    ),
                );
                foreach ( $rows as $r ) :
                ?>
                <div class="as-checklist__row">
                    <span class="as-checklist__icon"><?php echo $r['ok'] ? '✓' : '✕'; ?></span>
                    <div class="as-checklist__body">
                        <span class="as-checklist__label"><?php echo esc_html( $r['label'] ); ?></span>
                        <span class="as-checklist__detail"><?php echo esc_html( $r['detail'] ); ?></span>
                    </div>
                    <span class="as-checklist__badge <?php echo esc_attr( $r['bmod'] ); ?>"><?php echo esc_html( $r['badge'] ); ?></span>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <div class="statify-footer">
        <p>Statify v<?php echo esc_html( STATIFY_VERSION ); ?> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify' ) ); ?>"><?php esc_html_e( 'Dashboard', 'statify' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>"><?php esc_html_e( 'Politique de confidentialité', 'statify' ); ?></a></p>
    </div>

</div><!-- .wrap -->

<style>
/* ─── Misc header ──────────────────────────────────────────────────────────── */
.as-version-badge {
    font-size: 12px; font-weight: 500;
    color: var(--statify-text-secondary);
    background: var(--statify-bg);
    border: 1px solid var(--statify-border);
    border-radius: 20px;
    padding: 3px 11px;
}

/* ─── Status banner ────────────────────────────────────────────────────────── */
.as-status-banner {
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid var(--statify-border);
    border-radius: var(--statify-radius);
    box-shadow: var(--statify-shadow);
    margin-bottom: 16px;
}
/* No colored border on banner — chips carry the status signal */
.as-status-banner__icon { font-size: 22px; flex-shrink: 0; }
.as-status-banner__body { flex: 1; font-size: 13px; line-height: 1.5; color: var(--statify-text-secondary); }
.as-status-banner__body strong { display: block; font-size: 13px; font-weight: 700; color: var(--statify-text); margin-bottom: 2px; }
.as-status-banner__chips { display: flex; gap: 8px; flex-wrap: wrap; }

/* ─── Chips ────────────────────────────────────────────────────────────────── */
.as-chip {
    font-size: 11px; font-weight: 500;
    padding: 3px 10px; border-radius: 20px;
    background: var(--statify-bg);
    color: var(--statify-text-secondary);
    border: 1px solid var(--statify-border);
    white-space: nowrap;
}
.as-chip.mod-ok     { color: #065f46; background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.25); }
.as-chip.mod-warn   { color: #92400e; background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); }
.as-chip.mod-danger { color: #b91c1c; background: rgba(239,68,68,.08);  border-color: rgba(239,68,68,.25); }
a.as-chip { text-decoration: none; cursor: pointer; transition: opacity .15s; }
a.as-chip:hover { opacity: .8; }

/* ─── Inline alerts ────────────────────────────────────────────────────────── */
.as-alert {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 13px 18px;
    background: #fff;
    border: 1px solid var(--statify-border);
    border-radius: var(--statify-radius);
    box-shadow: var(--statify-shadow);
    margin-bottom: 14px;
    font-size: 13px;
}
.as-alert.mod-warn   { border-left: 3px solid var(--statify-warning); }
.as-alert.mod-danger { border-left: 3px solid var(--statify-danger); }
.as-alert__icon {
    flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; margin-top: 1px;
}
.as-alert.mod-warn   .as-alert__icon { background: rgba(245,158,11,.12); color: #92400e; }
.as-alert.mod-danger .as-alert__icon { background: rgba(239,68,68,.12);  color: #b91c1c; }
.as-alert__body { flex: 1; color: var(--statify-text-secondary); line-height: 1.5; }
.as-alert__body strong { display: block; font-weight: 700; color: var(--statify-text); margin-bottom: 3px; }
.as-alert__cta {
    align-self: center; flex-shrink: 0;
    font-size: 12px; font-weight: 600;
    color: var(--statify-primary) !important;
    border: 1px solid var(--statify-primary);
    border-radius: 6px; padding: 5px 14px;
    text-decoration: none; transition: all .15s; white-space: nowrap;
}
.as-alert__cta:hover { background: var(--statify-primary); color: #fff !important; }

/* ─── Tab bar ──────────────────────────────────────────────────────────────── */
.as-tabs {
    display: flex; gap: 2px; flex-wrap: wrap;
    background: #fff;
    border: 1px solid var(--statify-border);
    border-radius: var(--statify-radius);
    padding: 5px;
    margin-bottom: 24px;
    box-shadow: var(--statify-shadow);
}
.as-tab {
    padding: 8px 16px;
    border: none; background: transparent;
    border-radius: 7px;
    font-size: 13px; font-weight: 500;
    color: var(--statify-text-secondary);
    cursor: pointer; transition: all .15s;
    position: relative; white-space: nowrap;
}
.as-tab:hover:not(.active) { background: var(--statify-bg); color: var(--statify-text); }
.as-tab.active { background: var(--statify-primary); color: #fff; font-weight: 600; }
.as-tab-dot {
    position: absolute; top: 6px; right: 6px;
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--statify-danger);
}
.as-panel { display: none; }
.as-panel.active { display: block; }

/* ─── Card ─────────────────────────────────────────────────────────────────── */
.as-card {
    background: #fff;
    border: 1px solid var(--statify-border);
    border-radius: var(--statify-radius);
    box-shadow: var(--statify-shadow);
    margin-bottom: 20px;
    overflow: hidden;
}
.as-card__head {
    padding: 20px 28px 16px;
    border-bottom: 1px solid var(--statify-border);
}
.as-card__head h2 {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    font-size: 15px; font-weight: 700; color: var(--statify-text);
    margin: 0 0 5px; padding: 0;
}
.as-card__head p {
    font-size: 13px; color: var(--statify-text-secondary);
    margin: 0; line-height: 1.5;
}
.as-card__body { padding: 24px 28px; }

/* ─── Setting row ──────────────────────────────────────────────────────────── */
.as-row {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0 32px;
    padding: 22px 28px;
    border-bottom: 1px solid var(--statify-border);
    align-items: start;
}
.as-row--last { border-bottom: none; }
.as-row--warn { background: rgba(245,158,11,.04); }
.as-row__label { display: flex; flex-direction: column; gap: 5px; }
.as-row__title { font-size: 13px; font-weight: 600; color: var(--statify-text); display: flex; align-items: center; gap: 8px; }
.as-row__desc  { font-size: 12px; color: var(--statify-text-secondary); line-height: 1.6; }
.as-row__control { padding-top: 2px; }
.as-row__control--inline { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start; }

/* ─── Toggle switch ────────────────────────────────────────────────────────── */
.as-toggle { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
.as-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.as-toggle__track {
    position: relative; width: 38px; height: 22px;
    background: var(--statify-border); border-radius: 11px;
    transition: background .2s; flex-shrink: 0;
}
.as-toggle__track::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 16px; height: 16px;
    background: #fff; border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: transform .2s;
}
.as-toggle input:checked ~ .as-toggle__track { background: var(--statify-primary); }
.as-toggle input:checked ~ .as-toggle__track::after { transform: translateX(16px); }
.as-toggle__label { font-size: 13px; color: var(--statify-text-secondary); }

/* ─── Radio group ──────────────────────────────────────────────────────────── */
.as-radio-group { display: flex; flex-direction: column; gap: 12px; }
.as-radio { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
.as-radio input { position: absolute; opacity: 0; width: 0; height: 0; }
.as-radio__box {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid var(--statify-border);
    flex-shrink: 0; margin-top: 2px;
    background: #fff; transition: border-color .15s;
    position: relative;
}
.as-radio__box::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 8px; height: 8px;
    border-radius: 50%; background: var(--statify-primary);
    opacity: 0; transform: scale(.5); transition: all .15s;
}
.as-radio input:checked ~ .as-radio__box { border-color: var(--statify-primary); }
.as-radio input:checked ~ .as-radio__box::after { opacity: 1; transform: scale(1); }
.as-radio span:last-child { display: flex; flex-direction: column; gap: 2px; }
.as-radio span:last-child strong { font-size: 13px; font-weight: 600; color: var(--statify-text); }
.as-radio span:last-child small  { font-size: 12px; color: var(--statify-text-secondary); }

/* ─── Checkbox group ───────────────────────────────────────────────────────── */
.as-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px 20px; }
.as-checkbox { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--statify-text); }
.as-checkbox input { position: absolute; opacity: 0; width: 0; height: 0; }
.as-checkbox__box {
    width: 16px; height: 16px; border-radius: 4px;
    border: 2px solid var(--statify-border);
    flex-shrink: 0; background: #fff;
    transition: all .15s; position: relative;
}
.as-checkbox__box::after {
    content: ''; position: absolute;
    top: 1px; left: 4px;
    width: 5px; height: 9px;
    border: 2px solid #fff; border-top: none; border-left: none;
    transform: rotate(45deg) scale(0); transition: transform .15s;
}
.as-checkbox input:checked ~ .as-checkbox__box { background: var(--statify-primary); border-color: var(--statify-primary); }
.as-checkbox input:checked ~ .as-checkbox__box::after { transform: rotate(45deg) scale(1); }

/* ─── Form inputs ──────────────────────────────────────────────────────────── */
.as-input, .as-input-full, .as-textarea, .as-select, .as-input-number {
    border: 1px solid var(--statify-border);
    border-radius: 7px;
    padding: 8px 12px;
    font-size: 13px;
    background: #fff;
    color: var(--statify-text);
    transition: border-color .2s, box-shadow .2s;
}
.as-input-full, .as-textarea { width: 100%; max-width: 520px; }
.as-textarea { resize: vertical; }
.as-input-number { width: 90px; }
.as-select { min-width: 180px; cursor: pointer; }
.as-input:focus, .as-input-full:focus, .as-textarea:focus,
.as-select:focus, .as-input-number:focus {
    border-color: var(--statify-primary);
    box-shadow: 0 0 0 3px rgba(108,99,255,.1);
    outline: none;
}

.as-input-group { display: flex; flex-direction: column; gap: 5px; }
.as-input-group__label { font-size: 11px; font-weight: 600; color: var(--statify-text-secondary); text-transform: uppercase; letter-spacing: .4px; }
.as-input-group .as-input { width: 180px; }

.as-input-suffix { display: inline-flex; align-items: center; gap: 8px; }
.as-input-suffix__unit { font-size: 12px; color: var(--statify-text-secondary); }

/* ─── Color pickers ────────────────────────────────────────────────────────── */
.as-color-group { display: flex; flex-direction: column; gap: 5px; align-items: flex-start; }
.as-color-group label { font-size: 11px; font-weight: 600; color: var(--statify-text-secondary); text-transform: uppercase; letter-spacing: .4px; }
.as-color { width: 48px; height: 32px; padding: 2px; border: 1px solid var(--statify-border); border-radius: 6px; cursor: pointer; }

/* ─── Inline badge ─────────────────────────────────────────────────────────── */
.as-inline-badge {
    font-size: 11px; font-weight: 600;
    padding: 2px 9px; border-radius: 20px;
    background: var(--statify-bg); color: var(--statify-text-secondary);
    border: 1px solid var(--statify-border);
}
.as-inline-badge.mod-ok     { background: rgba(16,185,129,.1); color: #065f46; border-color: rgba(16,185,129,.25); }
.as-inline-badge.mod-warn   { background: rgba(245,158,11,.1); color: #92400e; border-color: rgba(245,158,11,.25); }
.as-inline-badge.mod-danger { background: rgba(239,68,68,.1);  color: #b91c1c; border-color: rgba(239,68,68,.25); }

/* ─── Note ─────────────────────────────────────────────────────────────────── */
.as-note {
    display: flex; gap: 12px;
    padding: 14px 18px;
    background: var(--statify-bg);
    border: 1px solid var(--statify-border);
    border-radius: var(--statify-radius);
    font-size: 13px; color: var(--statify-text-secondary);
    line-height: 1.6; margin-bottom: 20px;
}
.as-note__icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; opacity: .6; }
.as-note strong { display: block; font-weight: 600; color: var(--statify-text); margin-bottom: 3px; }

/* ─── Save bar ─────────────────────────────────────────────────────────────── */
.as-save-bar {
    position: sticky; bottom: 16px; z-index: 99;
    margin-bottom: 32px;
}
.as-save-btn {
    background: var(--statify-primary) !important;
    border-color: var(--statify-primary-dark) !important;
    border-radius: 8px !important;
    padding: 9px 30px !important;
    font-size: 13px !important; font-weight: 600 !important;
    box-shadow: 0 2px 8px rgba(108,99,255,.3) !important;
}
.as-save-btn:hover { background: var(--statify-primary-dark) !important; }

/* ─── RGPD checklist ───────────────────────────────────────────────────────── */
.as-checklist { }
.as-checklist__row {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 16px 28px;
    border-bottom: 1px solid var(--statify-border);
    transition: background .12s;
}
.as-checklist__row:last-child { border-bottom: none; }
.as-checklist__row:hover { background: var(--statify-bg); }
.as-checklist__icon {
    width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; flex-shrink: 0; margin-top: 1px;
    background: rgba(16,185,129,.1); color: #065f46;
}
.as-checklist__row:has(.mod-warn) .as-checklist__icon,
.as-checklist__row:has(.mod-danger) .as-checklist__icon {
    background: rgba(239,68,68,.1); color: #b91c1c;
}
.as-checklist__body { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.as-checklist__label  { font-size: 13px; font-weight: 600; color: var(--statify-text); }
.as-checklist__detail { font-size: 12px; color: var(--statify-text-secondary); line-height: 1.5; }
.as-checklist__badge {
    flex-shrink: 0; align-self: flex-start; margin-top: 2px;
    font-size: 11px; font-weight: 600;
    padding: 3px 11px; border-radius: 20px;
    background: var(--statify-bg); color: var(--statify-text-secondary);
    border: 1px solid var(--statify-border); white-space: nowrap;
}
.as-checklist__badge.mod-warn   { background: rgba(245,158,11,.1); color: #92400e; border-color: rgba(245,158,11,.25); }
.as-checklist__badge.mod-danger { background: rgba(239,68,68,.1);  color: #b91c1c; border-color: rgba(239,68,68,.25); }

/* ─── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .as-row { grid-template-columns: 1fr; gap: 6px; padding: 18px 20px; }
    .as-row__control { padding-top: 0; }
}
@media (max-width: 768px) {
    .as-tab { font-size: 12px; padding: 7px 12px; }
    .as-status-banner { flex-direction: column; align-items: flex-start; }
}
</style>

<script>
(function () {
    'use strict';
    var tabs   = document.querySelectorAll('.as-tab');
    var panels = document.querySelectorAll('.as-panel');
    var savebar = document.getElementById('statify-save-bar');
    var formTabs = ['tracking','privacy','consent','geo','performance'];

    function show(tab) {
        tabs.forEach(function(t)   { t.classList.toggle('active', t.dataset.tab === tab); });
        panels.forEach(function(p) { p.classList.toggle('active', p.dataset.panel === tab); });
        if (savebar) savebar.style.display = formTabs.indexOf(tab) !== -1 ? '' : 'none';
    }

    tabs.forEach(function(t) { t.addEventListener('click', function() { show(t.dataset.tab); }); });

    document.querySelectorAll('.statify-settings-tab-link').forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            if (a.dataset.tab) { show(a.dataset.tab); window.scrollTo({top:0,behavior:'smooth'}); }
        });
    });

    if (window.location.hash === '#tab-consent') show('consent');
})();
</script>
