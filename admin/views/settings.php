<?php
/**
 * Settings view — Advanced Stats.
 * Custom HTML rows (no do_settings_fields) for full layout control.
 *
 * @package Always_Analytics
 */
if (!defined('ABSPATH')) {
    exit;
}

$o = get_option('always_analytics_options', array());
$mode = isset($o['tracking_mode']) ? $o['tracking_mode'] : 'cookieless';
$retention = isset($o['retention_days']) ? absint($o['retention_days']) : 90;
$consent_on = !empty($o['consent_enabled']);
$anon_ip = !empty($o['anonymize_ip']);
$geo_on = !empty($o['geo_enabled']);
$cookieless_window = isset($o['cookieless_window']) ? $o['cookieless_window'] : 'daily';

$need_consent = ('cookie' === $mode && !$consent_on);
$ret_limit = 390; // 13 months — CNIL threshold
$ret_warn = (0 === $retention || $retention > $ret_limit);

// DB stats
global $wpdb;
$db_hits = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aa_hits")); // phpcs:ignore
$db_sessions = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aa_sessions")); // phpcs:ignore
$db_scroll = 0;
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'aa_scroll'))) {
    $db_scroll = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aa_scroll")); // phpcs:ignore
}
$db_daily = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aa_daily")); // phpcs:ignore
$db_anon = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aa_hits WHERE visitor_hash LIKE 'anon_%'")); // phpcs:ignore

// WP roles
$wp_roles = wp_roles()->get_names();
$excluded_roles = isset($o['excluded_roles']) ? (array)$o['excluded_roles'] : array();

// Field helpers
function as_val($o, $k, $d = '')
{
    return isset($o[$k]) ? $o[$k] : $d;
}
function as_checked($o, $k)
{
    return !empty($o[$k]) ? 'checked' : '';
}
?>
<div class="wrap aa-wrap">

    <!-- Header -->
    <div class="aa-header">
        <h1>
            <img src="<?php echo esc_url(AA_PLUGIN_URL . 'always-analytics.svg'); ?>" alt="" style="width:32px;height:32px;vertical-align:middle;">
            <?php esc_html_e('Réglages', 'always-analytics'); ?>
        </h1>
        <div class="aa-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=always-analytics')); ?>" class="aa-back-btn">← <?php esc_html_e('Dashboard', 'always-analytics'); ?></a>
            <span class="as-version-badge">v<?php echo esc_html(AA_VERSION); ?></span>
        </div>
    </div>

    <!-- RGPD Status Banner -->
    <?php
$rgpd_issues = array();
if ($need_consent) {
    $rgpd_issues[] = array('label' => __('Consentement requis', 'always-analytics'), 'mod' => 'mod-danger', 'tab' => 'consent');
}
if (!$anon_ip) {
    $rgpd_issues[] = array('label' => __('IP non anonymisée', 'always-analytics'), 'mod' => 'mod-warn', 'tab' => 'privacy');
}
if ($ret_warn) {
    $ret_label = (0 === $retention)
        ? __('Rétention illimitée', 'always-analytics')
        : sprintf(__('Rétention %d j (> 13 mois)', 'always-analytics'), $retention);
    $rgpd_issues[] = array('label' => $ret_label, 'mod' => 'mod-warn', 'tab' => 'privacy');
}
$rgpd_ok = empty($rgpd_issues);
?>
    <div class="as-status-banner">
        <span class="as-status-banner__icon"><?php echo $rgpd_ok ? '<svg class="as-banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>' : '<svg class="as-banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'; ?></span>
        <div class="as-status-banner__body">
            <strong><?php esc_html_e('Statut RGPD', 'always-analytics'); ?></strong>
            <span><?php echo 'cookie' === $mode ? esc_html__('Mode cookie', 'always-analytics') : esc_html__('Mode sans cookie', 'always-analytics'); ?></span>
        </div>
        <div class="as-status-banner__chips">
            <?php if ($rgpd_ok): ?>
                <span class="as-chip mod-ok"><svg class="as-chip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <?php esc_html_e('Conforme RGPD', 'always-analytics'); ?></span>
            <?php
else: ?>
                <?php foreach ($rgpd_issues as $issue): ?>
                    <a href="#" class="as-chip <?php echo esc_attr($issue['mod']); ?> aa-settings-tab-link" data-tab="<?php echo esc_attr($issue['tab']); ?>">
                        <svg class="as-chip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <?php echo esc_html($issue['label']); ?>
                    </a>
                <?php
    endforeach; ?>
            <?php
endif; ?>
        </div>
    </div>

    <!-- Tab bar -->
    <div class="as-tabs">
        <button class="as-tab active" data-tab="tracking"><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> <?php esc_html_e('Tracking', 'always-analytics'); ?></button>
        <button class="as-tab" data-tab="privacy"><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> <?php esc_html_e('Confidentialité', 'always-analytics'); ?></button>
        <button class="as-tab <?php echo $need_consent ? 'mod-alert' : ''; ?>" data-tab="consent"><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg> <?php esc_html_e('Consentement', 'always-analytics'); ?><?php if ($need_consent): ?><span class="as-tab-dot"></span><?php
endif; ?></button>
        <button class="as-tab" data-tab="performance"><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <?php esc_html_e('Performance', 'always-analytics'); ?></button>
        <button class="as-tab" data-tab="maintenance"><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg> <?php esc_html_e('Maintenance', 'always-analytics'); ?></button>
        <button class="as-tab" data-tab="rgpd"><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> <?php esc_html_e('Conformité RGPD', 'always-analytics'); ?></button>
    </div>

    <form method="post" action="options.php" id="aa-settings-form">
        <?php settings_fields('always_analytics_settings'); ?>
        <input type="hidden" name="_aa_active_tab" id="aa-active-tab-field" value="tracking">

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — TRACKING
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel active" data-panel="tracking">
            <div class="as-card">
                <div class="as-card__head">
                    <h2><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> <?php esc_html_e('Tracking', 'always-analytics'); ?></h2>
                    <p><?php esc_html_e('Contrôlez si et comment Always Analytics collecte les données de vos visiteurs.', 'always-analytics'); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Désactiver le tracking', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Suspend temporairement toute collecte de données.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="always_analytics_options[disable_tracking]" value="1" <?php echo as_checked($o, 'disable_tracking'); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e('Désactiver', 'always-analytics'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Mode de tracking', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Sans cookie : hash journalier anonyme, éligible exemption CNIL. Avec cookie : suivi multi-jours, consentement requis.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div class="as-radio-group">
                            <label class="as-radio">
                                <input type="radio" name="always_analytics_options[tracking_mode]" value="cookieless" <?php checked($mode, 'cookieless'); ?>>
                                <span class="as-radio__box"></span>
                                <span>
                                    <strong><?php esc_html_e('Sans cookie', 'always-analytics'); ?></strong>
                                    <small><?php esc_html_e('Respectueux de la vie privée, pas de consentement requis', 'always-analytics'); ?></small>
                                </span>
                            </label>
                            <label class="as-radio">
                                <input type="radio" name="always_analytics_options[tracking_mode]" value="cookie" <?php checked($mode, 'cookie'); ?>>
                                <span class="as-radio__box"></span>
                                <span>
                                    <strong><?php esc_html_e('Avec cookie', 'always-analytics'); ?></strong>
                                    <small><?php esc_html_e('Meilleur suivi multi-jours, bannière de consentement nécessaire', 'always-analytics'); ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                    <?php if ($need_consent): ?>
                    <div class="as-row__alert">
                        <div id="aa-alert-cookie-mode" class="as-inline-alert as-inline-alert--danger">
                            <svg class="as-inline-alert__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div>
                                <strong><?php esc_html_e('Bannière de consentement obligatoire en mode cookie', 'always-analytics'); ?></strong>
                                <?php esc_html_e('En passant en mode cookie, un fichier est déposé sur l\'appareil du visiteur pour le reconnaître d\'une visite à l\'autre. Or la loi est claire : on ne peut pas déposer un cookie de tracking sans avoir obtenu le consentement préalable et explicite du visiteur.', 'always-analytics'); ?>
                                <div class="as-inline-alert__laws">
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 7</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>Directive ePrivacy 2002/58/CE art. 5.3</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>CNIL délibération n°2020-091</span>
                                </div>
                                <?php esc_html_e('Concrètement : sans bannière active, vos visiteurs sont trackés sans le savoir, ce qui constitue une infraction. En cas de contrôle, la CNIL peut prononcer une mise en demeure, voire une sanction financière.', 'always-analytics'); ?>
                                <a href="#" class="as-inline-alert__link aa-settings-tab-link" data-tab="consent"><?php esc_html_e('→ Activer la bannière dans l\'onglet Consentement', 'always-analytics'); ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Rôles exclus', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Les visites de ces rôles ne seront pas comptabilisées.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div class="as-checkbox-group">
                            <?php foreach ($wp_roles as $role_key => $role_name): ?>
                            <label class="as-checkbox">
                                <input type="checkbox" name="always_analytics_options[excluded_roles][]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $excluded_roles, true)); ?>>
                                <span class="as-checkbox__box"></span>
                                <?php echo esc_html(translate_user_role($role_name)); ?>
                            </label>
                            <?php
endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('IPs exclues', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Une adresse IP par ligne. Ces IPs seront ignorées lors du tracking.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <textarea name="always_analytics_options[excluded_ips]" rows="4" class="as-textarea"><?php echo esc_textarea(as_val($o, 'excluded_ips')); ?></textarea>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Proxy de confiance', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Configurez comment Always Analytics détecte l\'adresse IP des visiteurs quand votre site est derrière un proxy.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <?php $proxy_mode = as_val($o, 'trusted_proxy_mode', 'none'); ?>
                        <div class="as-radio-group">
                            <label class="as-radio">
                                <input type="radio" name="always_analytics_options[trusted_proxy_mode]" value="none" <?php checked($proxy_mode, 'none'); ?>>
                                <span class="as-radio__box"></span>
                                <span>
                                    <strong><?php esc_html_e('Aucun', 'always-analytics'); ?></strong>
                                    <small><?php esc_html_e('Recommandé si pas de proxy ou load balancer', 'always-analytics'); ?></small>
                                </span>
                            </label>

                            <label class="as-radio">
                                <input type="radio" name="always_analytics_options[trusted_proxy_mode]" value="custom" <?php checked($proxy_mode, 'custom'); ?>>
                                <span class="as-radio__box"></span>
                                <span>
                                    <strong><?php esc_html_e('Proxy spécifique', 'always-analytics'); ?></strong>
                                    <small><?php esc_html_e('Load balancer, Nginx, etc. (IPs à spécifier ci-dessous)', 'always-analytics'); ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('IPs des proxys personnalisés', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Si "Proxy spécifique" est sélectionné. Une IP ou un bloc CIDR par ligne.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <textarea name="always_analytics_options[trusted_proxies]" rows="4" class="as-textarea"><?php echo esc_textarea(as_val($o, 'trusted_proxies')); ?></textarea>
                    </div>
                </div>

                <div class="as-row <?php echo ($geo_on && !$anon_ip) ? '' : 'as-row--last'; ?>">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Géolocalisation', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Résout les IPs en pays/ville lors du tracking pour enrichir vos statistiques.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="always_analytics_options[geo_enabled]" value="1" <?php echo as_checked($o, 'geo_enabled'); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e('Activer', 'always-analytics'); ?></span>
                        </label>
                    </div>
                    <?php if ($geo_on && !$anon_ip): ?>
                    <div class="as-row__alert">
                        <div id="aa-alert-geo" class="as-inline-alert as-inline-alert--warn">
                            <svg class="as-inline-alert__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <div>
                                <strong><?php esc_html_e('La géolocalisation traite l\'IP complète de vos visiteurs', 'always-analytics'); ?></strong>
                                <?php esc_html_e('Pour localiser un visiteur (pays, région, ville), le plugin utilise son adresse IP. Sans anonymisation préalable, cette IP complète est traitée comme donnée personnelle identifiante. Ce n\'est pas interdit, mais cela oblige à une base légale explicite et à en informer les visiteurs.', 'always-analytics'); ?>
                                <div class="as-inline-alert__laws">
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 4.1 — l'IP est une donnée personnelle</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 25 — privacy by design</span>
                                </div>
                                <?php esc_html_e('Solution simple : activez l\'anonymisation IP dans l\'onglet Confidentialité. La géolocalisation continuera de fonctionner à l\'échelle du pays et de la ville, sans traiter d\'IP personnelle.', 'always-analytics'); ?>
                                <a href="#" class="as-inline-alert__link aa-settings-tab-link" data-tab="privacy"><?php esc_html_e('→ Activer l\'anonymisation IP', 'always-analytics'); ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             TAB — CONFIDENTIALITÉ
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="as-panel" data-panel="privacy">
            <div class="as-card">
                <div class="as-card__head">
                    <h2><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> <?php esc_html_e('Confidentialité & RGPD', 'always-analytics'); ?></h2>
                    <p><?php esc_html_e('Protection des données personnelles et durée de conservation.', 'always-analytics'); ?></p>
                </div>

                <div class="as-row <?php echo !$anon_ip ? '' : 'as-row--last'; ?>">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Anonymiser les IPs', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Masque le dernier octet IPv4 et les 80 derniers bits IPv6 avant le hachage.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="always_analytics_options[anonymize_ip]" value="1" <?php echo as_checked($o, 'anonymize_ip'); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e('Activer', 'always-analytics'); ?></span>
                        </label>
                    </div>
                    <?php if (!$anon_ip): ?>
                    <div class="as-row__alert">
                        <div id="aa-alert-anon-ip" class="as-inline-alert as-inline-alert--warn">
                            <svg class="as-inline-alert__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <div>
                                <strong><?php esc_html_e('L\'adresse IP de vos visiteurs est une donnée personnelle', 'always-analytics'); ?></strong>
                                <?php esc_html_e('Sans anonymisation, l\'IP complète du visiteur (ex : 192.168.1.42) est utilisée pour calculer son identifiant. Or une IP permet d\'identifier une personne — c\'est donc une donnée personnelle au sens strict du RGPD. La CNIL recommande de tronquer systématiquement le dernier octet (ex : 192.168.1.0) avant tout traitement, rendant l\'identification impossible.', 'always-analytics'); ?>
                                <div class="as-inline-alert__laws">
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 4.1 — définition donnée personnelle</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 25 — protection des données dès la conception</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>CNIL recommandation anonymisation IP</span>
                                </div>
                                <?php esc_html_e('Activer cette option ne change rien à vos statistiques : vous verrez toujours les pays, villes et visites uniques — mais sans risque RGPD.', 'always-analytics'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Fenêtre d\'unicité (sans cookie)', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Définit la durée pendant laquelle un visiteur est considéré unique en mode sans cookie et en pré-consentement RGPD.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                                <input type="radio" name="always_analytics_options[cookieless_window]" value="daily"
                                       <?php checked($cookieless_window, 'daily'); ?>
                                       style="margin-top:3px;flex-shrink:0;">
                                <span>
                                    <strong><?php esc_html_e('Journalière', 'always-analytics'); ?></strong><br>
                                    <span class="as-row__desc"><?php esc_html_e('SHA256(IP + UA + Langue + date du jour). Un visiteur unique par jour, remis à zéro à minuit UTC.', 'always-analytics'); ?></span>
                                </span>
                            </label>
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                                <input type="radio" name="always_analytics_options[cookieless_window]" value="session"
                                       <?php checked($cookieless_window, 'session'); ?>
                                       style="margin-top:3px;flex-shrink:0;">
                                <span>
                                    <strong><?php esc_html_e('Session uniquement', 'always-analytics'); ?></strong>
                                    &nbsp;<span class="as-inline-badge" style="background:#e8f5e9;color:#2e7d32;font-size:10px;padding:1px 6px;border-radius:4px;">CNIL</span><br>
                                    <span class="as-row__desc"><?php esc_html_e('SHA256(IP + UA + Langue + sessionId). Hash lié à l\'onglet navigateur, disparu à sa fermeture. Aucune persistance cross-session. Recommandé par la CNIL pour l\'exemption de consentement.', 'always-analytics'); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="as-row <?php echo $ret_warn ? 'as-row--warn' : ''; ?>">
                    <div class="as-row__label">
                        <span class="as-row__title">
                            <?php esc_html_e('Durée de rétention', 'always-analytics'); ?>
                            <?php if ($ret_warn): ?><span class="as-inline-badge mod-warn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;vertical-align:middle;margin-right:3px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>CNIL</span><?php
endif; ?>
                        </span>
                        <span class="as-row__desc"><?php esc_html_e('Après cette période, les données brutes sont anonymisées (les agrégats sont conservés indéfiniment). La CNIL recommande 13 mois maximum.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="always_analytics_options[retention_days]" class="as-select">
                            <option value="30"  <?php selected($retention, 30); ?>>30 <?php esc_html_e('jours', 'always-analytics'); ?></option>
                            <option value="90"  <?php selected($retention, 90); ?>>90 <?php esc_html_e('jours', 'always-analytics'); ?></option>
                            <option value="180" <?php selected($retention, 180); ?>>180 <?php esc_html_e('jours', 'always-analytics'); ?></option>
                            <option value="365" <?php selected($retention, 365); ?>>1 <?php esc_html_e('an', 'always-analytics'); ?></option>
                            <option value="0"   <?php selected($retention, 0); ?>><?php esc_html_e('Illimité', 'always-analytics'); ?></option>
                        </select>
                    </div>
                    <?php if (0 === $retention || $ret_warn): ?>
                    <div class="as-row__alert">
                        <div id="aa-alert-retention" class="as-inline-alert as-inline-alert--<?php echo (0 === $retention) ? 'danger' : 'warn'; ?>">
                            <svg class="as-inline-alert__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div>
                                <?php if (0 === $retention): ?>
                                <strong><?php esc_html_e('Rétention illimitée : vos données ne sont jamais effacées', 'always-analytics'); ?></strong>
                                <?php esc_html_e('Le RGPD pose un principe fondamental : on ne peut pas garder des données personnelles plus longtemps que nécessaire. Avec une rétention illimitée, les visites de vos utilisateurs restent en base indéfiniment — sans aucune base légale. La CNIL fixe un maximum de 13 mois pour les données d\'audience web.', 'always-analytics'); ?>
                                <div class="as-inline-alert__laws">
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 5.1.e — limitation de la conservation</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>CNIL recommandation — 13 mois max (390 jours)</span>
                                </div>
                                <?php esc_html_e('Bonne nouvelle : choisir 365 jours suffit pour avoir un historique annuel complet, tout en étant pleinement conforme.', 'always-analytics'); ?>
                                <?php else: ?>
                                <strong><?php printf(esc_html__('Rétention de %d jours : au-delà du seuil CNIL', 'always-analytics'), $retention); ?></strong>
                                <?php printf(esc_html__('Vous conservez des données de visite pendant %d jours, soit plus de 13 mois. La CNIL recommande de ne pas dépasser 390 jours pour les statistiques d\'audience. Au-delà, vous devrez justifier d\'une nécessité particulière — ce qui est rarement le cas pour de la mesure d\'audience simple.', 'always-analytics'), $retention); ?>
                                <div class="as-inline-alert__laws">
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>RGPD art. 5.1.e — limitation de la conservation</span>
                                    <span class="as-law-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>CNIL recommandation — 13 mois max (390 jours)</span>
                                </div>
                                <?php esc_html_e('→ Passez à 365 jours : vous gardez un an d\'historique complet, en toute conformité.', 'always-analytics'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Supprimer à la désinstallation', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Efface toutes les tables et options lors de la désinstallation du plugin.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="always_analytics_options[delete_on_uninstall]" value="1" <?php echo as_checked($o, 'delete_on_uninstall'); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e('Activer', 'always-analytics'); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="as-note">
                <span class="as-note__icon"><svg class="as-note__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg></span>
                <div>
                    <strong><?php esc_html_e('Anonymisation, pas suppression', 'always-analytics'); ?></strong>
                    <?php esc_html_e('Après la période de rétention, le visitor_hash est remplacé par un hash aléatoire, le user_id effacé, le referrer réduit au domaine. Les métriques (durée, scroll, device, page) sont conservées indéfiniment pour les statistiques.', 'always-analytics'); ?>
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
                        <svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg> <?php esc_html_e('Bannière de consentement', 'always-analytics'); ?>
                        <?php if ($need_consent): ?>
                            <span class="as-inline-badge mod-danger"><?php esc_html_e('Action requise', 'always-analytics'); ?></span>
                        <?php elseif ('cookieless' === $mode): ?>
                            <span class="as-inline-badge mod-ok"><?php esc_html_e('Exemption CNIL', 'always-analytics'); ?></span>
                        <?php elseif ($consent_on): ?>
                            <span class="as-inline-badge mod-ok"><?php esc_html_e('Active', 'always-analytics'); ?></span>
                        <?php endif; ?>
                    </h2>
                    <?php if ('cookieless' === $mode): ?>
                    <p><?php esc_html_e('Bandeau affiché aux visiteurs pour recueillir leur consentement au tracking par cookie.', 'always-analytics'); ?></p>
                    <div class="as-notice as-notice--success">
                        <svg class="as-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <div>
                            <strong><?php esc_html_e('Vous êtes exempté de consentement.', 'always-analytics'); ?></strong><br>
                            <?php esc_html_e('En mode cookieless, le tracking utilise un hash journalier anonyme sans dépôt de cookie. Conformément aux recommandations de la CNIL (délibération n°2020-091), ce type de mesure d\'audience est exempté de l\'obligation de recueil du consentement. Aucune bannière n\'est requise.', 'always-analytics'); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p><?php esc_html_e('Bandeau affiché aux visiteurs pour recueillir leur consentement au tracking par cookie.', 'always-analytics'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Activer la bannière', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Affiche un bandeau de consentement aux visiteurs. Obligatoire en mode cookie.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <label class="as-toggle">
                            <input type="checkbox" name="always_analytics_options[consent_enabled]" value="1" <?php echo as_checked($o, 'consent_enabled'); ?>>
                            <span class="as-toggle__track"></span>
                            <span class="as-toggle__label"><?php esc_html_e('Activer', 'always-analytics'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Message', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Texte affiché dans le bandeau de consentement.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <input type="text" name="always_analytics_options[consent_message]" value="<?php echo esc_attr(as_val($o, 'consent_message', __('Ce site utilise des cookies pour analyser le trafic. Acceptez-vous ?', 'always-analytics'))); ?>" class="as-input-full">
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Boutons', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Libellé des boutons Accepter et Refuser.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control as-row__control--inline">
                        <div class="as-input-group">
                            <label class="as-input-group__label"><?php esc_html_e('Accepter', 'always-analytics'); ?></label>
                            <input type="text" name="always_analytics_options[consent_accept]" value="<?php echo esc_attr(as_val($o, 'consent_accept', __('Accepter', 'always-analytics'))); ?>" class="as-input">
                        </div>
                        <div class="as-input-group">
                            <label class="as-input-group__label"><?php esc_html_e('Refuser', 'always-analytics'); ?></label>
                            <input type="text" name="always_analytics_options[consent_decline]" value="<?php echo esc_attr(as_val($o, 'consent_decline', __('Refuser', 'always-analytics'))); ?>" class="as-input">
                        </div>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Couleurs', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Personnalisez les couleurs du bandeau.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control as-row__control--inline">
                        <div class="as-color-group">
                            <label><?php esc_html_e('Fond', 'always-analytics'); ?></label>
                            <input type="color" name="always_analytics_options[consent_bg_color]" value="<?php echo esc_attr(as_val($o, 'consent_bg_color', '#1a1a2e')); ?>" class="as-color">
                        </div>
                        <div class="as-color-group">
                            <label><?php esc_html_e('Texte', 'always-analytics'); ?></label>
                            <input type="color" name="always_analytics_options[consent_text_color]" value="<?php echo esc_attr(as_val($o, 'consent_text_color', '#ffffff')); ?>" class="as-color">
                        </div>
                        <div class="as-color-group">
                            <label><?php esc_html_e('Bouton', 'always-analytics'); ?></label>
                            <input type="color" name="always_analytics_options[consent_btn_color]" value="<?php echo esc_attr(as_val($o, 'consent_btn_color', '#6c63ff')); ?>" class="as-color">
                        </div>
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
                    <h2><svg class="as-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <?php esc_html_e('Performance & Filtrage', 'always-analytics'); ?></h2>
                    <p><?php esc_html_e('Cache des requêtes, filtrage des bots et format d\'export.', 'always-analytics'); ?></p>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Durée du cache', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Durée en secondes du cache des résultats API (60 – 3600 s).', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <div class="as-input-suffix">
                            <input type="number" name="always_analytics_options[cache_ttl]" value="<?php echo esc_attr(as_val($o, 'cache_ttl', 300)); ?>" min="60" max="3600" class="as-input-number">
                            <span class="as-input-suffix__unit"><?php esc_html_e('secondes', 'always-analytics'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="as-row">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Filtrage des bots', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Activé : filtre les bots connus, les outils de performance (Lighthouse, PageSpeed…) et les URLs suspectes. Désactivé : tout enregistrer.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="always_analytics_options[bot_filter_mode]" class="as-select">
                            <option value="normal" <?php selected(as_val($o, 'bot_filter_mode', 'normal'), 'normal'); ?>><?php esc_html_e('Activé', 'always-analytics'); ?></option>
                            <option value="off"    <?php selected(as_val($o, 'bot_filter_mode', 'normal'), 'off'); ?>><?php esc_html_e('Désactivé', 'always-analytics'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="as-row as-row--last">
                    <div class="as-row__label">
                        <span class="as-row__title"><?php esc_html_e('Format d\'export', 'always-analytics'); ?></span>
                        <span class="as-row__desc"><?php esc_html_e('Format utilisé par défaut lors des exports de données.', 'always-analytics'); ?></span>
                    </div>
                    <div class="as-row__control">
                        <select name="always_analytics_options[export_format]" class="as-select">
                            <option value="csv"  <?php selected(as_val($o, 'export_format', 'csv'), 'csv'); ?>>CSV</option>
                            <option value="json" <?php selected(as_val($o, 'export_format', 'csv'), 'json'); ?>>JSON</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save bar -->
        <div class="as-save-bar" id="aa-save-bar">
            <?php submit_button(__('Enregistrer les réglages', 'always-analytics'), 'primary', 'submit', false, array('class' => 'as-save-btn')); ?>
        </div>

    </form><!-- /form -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB — MAINTENANCE  (outside form)
    ═══════════════════════════════════════════════════════════════════════ -->
    <div class="as-panel" data-panel="maintenance">
        <div class="aa-kpis">
            <div class="aa-kpi-card"><div class="aa-kpi-value"><?php echo esc_html(number_format_i18n($db_hits)); ?></div><div class="aa-kpi-label"><?php esc_html_e('Hits', 'always-analytics'); ?></div></div>
            <div class="aa-kpi-card"><div class="aa-kpi-value"><?php echo esc_html(number_format_i18n($db_sessions)); ?></div><div class="aa-kpi-label"><?php esc_html_e('Sessions', 'always-analytics'); ?></div></div>
            <div class="aa-kpi-card"><div class="aa-kpi-value"><?php echo esc_html(number_format_i18n($db_scroll)); ?></div><div class="aa-kpi-label"><?php esc_html_e('Scroll events', 'always-analytics'); ?></div></div>
            <div class="aa-kpi-card"><div class="aa-kpi-value"><?php echo esc_html(number_format_i18n($db_daily)); ?></div><div class="aa-kpi-label"><?php esc_html_e('Agrégats', 'always-analytics'); ?></div></div>
            <div class="aa-kpi-card"><div class="aa-kpi-value" style="color:var(--aa-success)"><?php echo esc_html(number_format_i18n($db_anon)); ?></div><div class="aa-kpi-label"><?php esc_html_e('Hits anonymisés', 'always-analytics'); ?></div></div>
        </div>

        <?php if ($db_anon > 0): ?>
        <div class="as-note" style="margin-bottom:24px;">
            <span class="as-note__icon"><svg class="as-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            <div><?php printf(esc_html__('%s hits anonymisés — statistiques conservées, identité effacée.', 'always-analytics'), '<strong>' . esc_html(number_format_i18n($db_anon)) . '</strong>'); ?></div>
        </div>
        <?php
endif; ?>

        <div class="as-card">
            <div class="as-card__head">
                <h2><svg class="as-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> <?php esc_html_e('Anonymisation manuelle', 'always-analytics'); ?></h2>
                <p><?php esc_html_e('Déclenche immédiatement l\'anonymisation des données dépassant la période de rétention (normalement géré par wp-cron).', 'always-analytics'); ?></p>
            </div>
            <div class="as-card__body">
                <button id="aa-purge-btn" class="button button-secondary"
                        style="border-radius:7px;">
                    <svg class="as-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> <?php esc_html_e('Lancer l\'anonymisation', 'always-analytics'); ?>
                </button>
                <span id="aa-purge-result" style="margin-left:12px;display:none;"></span>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         TAB — CONFORMITÉ RGPD
    ═══════════════════════════════════════════════════════════════════════ -->
    <div class="as-panel" data-panel="rgpd">
        <?php
        /* ── Calcul du score et des diagnostics ──────────────────────────── */
        $consent_ok  = ('cookie' === $mode && $consent_on) || 'cookieless' === $mode;
        $is_cookieless = ('cookieless' === $mode);
        $is_session    = ($cookieless_window === 'session');
        $tracking_off  = !empty($o['disable_tracking']);
        $has_geo       = $geo_on;
        $has_policy    = (bool) get_option('wp_page_for_privacy_policy');

        /* ── Définition des points de contrôle ──────────────────────────── */
        /* Chaque point : level = ok | warn | danger | info | exempt */
        $diag = array();

        /* 1. MODE DE TRACKING */
        if ($tracking_off) {
            $diag[] = array(
                'level'  => 'info',
                'cat'    => __('Tracking', 'always-analytics'),
                'title'  => __('Tracking désactivé', 'always-analytics'),
                'detail' => __('Aucune donnée n\'est collectée. Du point de vue RGPD, c\'est la situation la plus simple qui soit : pas de collecte = pas d\'obligation. En contrepartie, vos statistiques sont à l\'arrêt.', 'always-analytics'),
                'laws'   => array(),
                'action' => null,
                'tab'    => 'tracking',
            );
        } elseif ($is_cookieless) {
            $diag[] = array(
                'level'  => 'ok',
                'cat'    => __('Mode de tracking', 'always-analytics'),
                'title'  => __('Mode sans cookie — aucun dépôt sur l\'appareil du visiteur', 'always-analytics'),
                'detail' => __('Excellent choix. En mode sans cookie, rien n\'est écrit sur l\'appareil du visiteur. L\'identification repose sur un hash anonyme recalculé à chaque session ou chaque jour, sans aucune persistance. Ce type de mesure est explicitement reconnu comme exempté de consentement par la CNIL.', 'always-analytics'),
                'laws'   => array('CNIL délibération n°2020-091 — exemption mesure d\'audience', 'Directive ePrivacy 2002/58/CE art. 5.3'),
                'action' => null,
                'tab'    => 'tracking',
            );
        } else {
            $diag[] = array(
                'level'  => 'info',
                'cat'    => __('Mode de tracking', 'always-analytics'),
                'title'  => __('Mode avec cookie — suivi multi-jours activé', 'always-analytics'),
                'detail' => __('Un cookie est déposé chez le visiteur pour le reconnaître sur plusieurs visites. Ce mode offre des statistiques plus précises (retours, parcours multi-sessions), mais implique une obligation légale stricte : vous devez recueillir le consentement du visiteur AVANT de déposer le cookie.', 'always-analytics'),
                'laws'   => array('RGPD art. 7 — conditions du consentement', 'Directive ePrivacy 2002/58/CE art. 5.3 — cookies nécessitant un consentement'),
                'action' => null,
                'tab'    => 'tracking',
            );
        }

        /* 2. CONSENTEMENT */
        if ($need_consent) {
            $diag[] = array(
                'level'  => 'danger',
                'cat'    => __('Consentement', 'always-analytics'),
                'title'  => __('Bannière manquante — cookie déposé sans accord du visiteur', 'always-analytics'),
                'detail' => __('C\'est le point le plus critique de votre configuration. Vous utilisez des cookies de tracking, mais aucune bannière n\'est affichée pour recueillir l\'accord du visiteur. Concrètement, chaque visite sur votre site dépose un cookie sans que la personne ait pu accepter ou refuser. C\'est une infraction directement sanctionnable par la CNIL. La correction est simple : activez la bannière dans l\'onglet Consentement.', 'always-analytics'),
                'laws'   => array('RGPD art. 7 — le consentement doit être libre, éclairé et préalable', 'Directive ePrivacy 2002/58/CE art. 5.3', 'CNIL — sanctions possibles jusqu\'à 4% du CA mondial'),
                'action' => __('Activer la bannière →', 'always-analytics'),
                'tab'    => 'consent',
            );
        } elseif ($is_cookieless) {
            $diag[] = array(
                'level'  => 'exempt',
                'cat'    => __('Consentement', 'always-analytics'),
                'title'  => __('Exempté — aucune bannière requise en mode sans cookie', 'always-analytics'),
                'detail' => __('Vous n\'avez rien à faire ici. La CNIL a publié une liste d\'opérations de traçage exemptées de consentement, et la mesure d\'audience anonyme sans cookie en fait partie. La condition est que les données ne soient pas croisées avec d\'autres traitements ni cédées à des tiers — ce qu\'Always Analytics ne fait pas.', 'always-analytics'),
                'laws'   => array('CNIL délibération n°2020-091 — liste des exemptions', 'Directive ePrivacy 2002/58/CE art. 5.3 — exception intérêt légitime'),
                'action' => null,
                'tab'    => 'consent',
            );
        } else {
            $diag[] = array(
                'level'  => 'ok',
                'cat'    => __('Consentement', 'always-analytics'),
                'title'  => __('Bannière active — les visiteurs sont informés avant tout tracking', 'always-analytics'),
                'detail' => __('Bien configuré. La bannière s\'affiche avant tout dépôt de cookie, et le tracking ne démarre qu\'après acceptation explicite. Vous respectez le principe du consentement préalable et éclairé. Assurez-vous que les boutons "Accepter" et "Refuser" sont aussi faciles d\'accès l\'un que l\'autre (pas de "dark pattern").', 'always-analytics'),
                'laws'   => array('RGPD art. 7 — consentement préalable, libre et éclairé', 'CNIL recommandation — égale visibilité des choix accepter/refuser'),
                'action' => null,
                'tab'    => 'consent',
            );
        }

        /* 3. ANONYMISATION IP */
        if (!$anon_ip && !$tracking_off) {
            $diag[] = array(
                'level'  => 'warn',
                'cat'    => __('Données personnelles', 'always-analytics'),
                'title'  => __('IP complète utilisée — risque sur donnée personnelle identifiante', 'always-analytics'),
                'detail' => __('L\'adresse IP de vos visiteurs est utilisée en clair pour calculer leur identifiant. Or la CJUE (Cour de Justice de l\'UE) a confirmé en 2016 qu\'une adresse IP est une donnée personnelle car elle permet d\'identifier une personne via son fournisseur d\'accès. La solution est simple et sans impact sur vos stats : tronquez le dernier octet (192.168.1.42 → 192.168.1.0). La géolocalisation fonctionne toujours, l\'identification individuelle devient impossible.', 'always-analytics'),
                'laws'   => array('RGPD art. 4.1 — définition donnée personnelle', 'RGPD art. 25 — privacy by design (protection dès la conception)', 'CJUE arrêt C-582/14 Breyer — IP = donnée personnelle'),
                'action' => __('Activer l\'anonymisation →', 'always-analytics'),
                'tab'    => 'privacy',
            );
        } else {
            $diag[] = array(
                'level'  => 'ok',
                'cat'    => __('Données personnelles', 'always-analytics'),
                'title'  => __('IP anonymisée avant tout traitement', 'always-analytics'),
                'detail' => __('Le dernier octet IPv4 est masqué (ex : 192.168.1.42 → 192.168.1.0) avant de calculer l\'identifiant visiteur. Il est impossible de remonter à une personne précise à partir des données stockées. Vous appliquez le principe de "privacy by design" du RGPD : la protection de la vie privée est intégrée dès la conception, pas ajoutée après coup.', 'always-analytics'),
                'laws'   => array('RGPD art. 25 — privacy by design', 'RGPD art. 5.1.c — minimisation des données'),
                'action' => null,
                'tab'    => 'privacy',
            );
        }

        /* 4. RÉTENTION */
        if (0 === $retention && !$tracking_off) {
            $diag[] = array(
                'level'  => 'danger',
                'cat'    => __('Durée de conservation', 'always-analytics'),
                'title'  => __('Rétention illimitée — les données ne sont jamais supprimées', 'always-analytics'),
                'detail' => __('Le RGPD pose un principe fondamental appelé "limitation de la conservation" : on ne peut garder des données personnelles que le temps nécessaire à la finalité pour laquelle elles ont été collectées. Pour de la mesure d\'audience, cette finalité ne justifie pas une conservation indéfinie. La CNIL fixe un plafond clair à 13 mois (390 jours). Au-delà, chaque enregistrement en base est potentiellement illégal. Choisissez 365 jours : vous avez un historique annuel complet et êtes pleinement conforme.', 'always-analytics'),
                'laws'   => array('RGPD art. 5.1.e — principe de limitation de la conservation', 'CNIL recommandation — 13 mois maximum pour les cookies analytics'),
                'action' => __('Corriger dans Confidentialité →', 'always-analytics'),
                'tab'    => 'privacy',
            );
        } elseif ($ret_warn && !$tracking_off) {
            $diag[] = array(
                'level'  => 'warn',
                'cat'    => __('Durée de conservation', 'always-analytics'),
                'title'  => sprintf(__('Rétention de %d jours — au-delà des 13 mois CNIL', 'always-analytics'), $retention),
                'detail' => sprintf(__('Vous conservez les données de visite pendant %d jours, soit plus de 13 mois. La CNIL a fixé ce seuil en considérant qu\'un an de données suffit amplement pour analyser les tendances de trafic. Au-delà, la conservation doit être justifiée par un besoin spécifique documenté — ce qui est rarement le cas pour de l\'analytics standard. Passer à 365 jours vous donne un historique annuel complet en toute conformité.', 'always-analytics'), $retention),
                'laws'   => array('RGPD art. 5.1.e — limitation de la conservation', 'CNIL recommandation — 13 mois maximum (390 jours)'),
                'action' => __('Corriger dans Confidentialité →', 'always-analytics'),
                'tab'    => 'privacy',
            );
        } else {
            $diag[] = array(
                'level'  => 'ok',
                'cat'    => __('Durée de conservation', 'always-analytics'),
                'title'  => sprintf(__('Rétention de %d jours — dans les limites CNIL', 'always-analytics'), $retention),
                'detail' => __('Vos données brutes de visite sont automatiquement anonymisées après la période configurée. Les agrégats statistiques (nombre de pages vues, taux de rebond, etc.) sont conservés indéfiniment car ils ne contiennent plus aucun identifiant personnel. C\'est exactement ce que préconise la CNIL : anonymiser, pas supprimer, pour garder la valeur analytique des données.', 'always-analytics'),
                'laws'   => array('RGPD art. 5.1.e — limitation de la conservation', 'CNIL recommandation — anonymisation après 13 mois'),
                'action' => null,
                'tab'    => 'privacy',
            );
        }

        /* 5. FENÊTRE D'UNICITÉ */
        if ($is_cookieless && !$tracking_off) {
            if ($is_session) {
                $diag[] = array(
                    'level'  => 'ok',
                    'cat'    => __('Empreinte visiteur', 'always-analytics'),
                    'title'  => __('Fenêtre session — protection maximale, exemption CNIL assurée', 'always-analytics'),
                    'detail' => __('C\'est le mode le plus respectueux de la vie privée. Le hash d\'identification est lié à l\'onglet du navigateur et disparaît à sa fermeture. Un même visiteur qui revient le lendemain est techniquement un nouveau visiteur : aucune persistance, aucune reconnaissance cross-session. La CNIL cite explicitement ce type de fenêtre comme critère de l\'exemption de consentement.', 'always-analytics'),
                    'laws'   => array('CNIL délibération n°2020-091 — critères d\'exemption (fenêtre de session recommandée)'),
                    'action' => null,
                    'tab'    => 'privacy',
                );
            } else {
                $diag[] = array(
                    'level'  => 'info',
                    'cat'    => __('Empreinte visiteur', 'always-analytics'),
                    'title'  => __('Fenêtre journalière — conforme, niveau de protection légèrement moindre', 'always-analytics'),
                    'detail' => __('Le hash est recalculé chaque jour à minuit UTC. Un visiteur peut être reconnu pendant 24h maximum, puis disparaît. C\'est conforme à l\'exemption CNIL et suffisant pour la grande majorité des sites. Si vous voulez aller encore plus loin dans la protection de la vie privée (et potentiellement rassurer des visiteurs sensibles), le mode "Session" supprime toute persistance.', 'always-analytics'),
                    'laws'   => array('CNIL délibération n°2020-091 — exemption valide avec fenêtre courte'),
                    'action' => null,
                    'tab'    => 'privacy',
                );
            }
        }

        /* 6. GÉOLOCALISATION */
        if ($has_geo && !$anon_ip && !$tracking_off) {
            $diag[] = array(
                'level'  => 'warn',
                'cat'    => __('Géolocalisation', 'always-analytics'),
                'title'  => __('Géoloc active sans anonymisation IP — traitement de donnée personnelle', 'always-analytics'),
                'detail' => __('La géolocalisation détermine le pays et la ville du visiteur à partir de son adresse IP. Sans anonymisation, cette IP complète est traitée comme donnée personnelle (la CJUE l\'a confirmé). Vous pouvez continuer à géolocaliser sans risque en activant l\'anonymisation IP : l\'IP tronquée (ex : 1.2.3.0) suffit à déterminer la ville, sans identifier la personne.', 'always-analytics'),
                'laws'   => array('RGPD art. 4.1 — IP = donnée personnelle (CJUE C-582/14)', 'RGPD art. 5.1.c — minimisation des données'),
                'action' => __('Activer l\'anonymisation →', 'always-analytics'),
                'tab'    => 'privacy',
            );
        } elseif ($has_geo && $anon_ip) {
            $diag[] = array(
                'level'  => 'ok',
                'cat'    => __('Géolocalisation', 'always-analytics'),
                'title'  => __('Géoloc active avec IP anonymisée — combinaison conforme', 'always-analytics'),
                'detail' => __('L\'IP est tronquée avant la géolocalisation : la précision est maintenue au niveau ville/région, mais il est impossible de remonter à un foyer ou un individu précis. C\'est la combinaison idéale : statistiques géographiques utiles, sans traitement de donnée personnelle identifiante.', 'always-analytics'),
                'laws'   => array('RGPD art. 25 — privacy by design', 'RGPD art. 5.1.c — minimisation des données'),
                'action' => null,
                'tab'    => 'tracking',
            );
        }

        /* 7. IP NON STOCKÉE */
        $diag[] = array(
            'level'  => 'ok',
            'cat'    => __('Stockage des données', 'always-analytics'),
            'title'  => __('Adresse IP jamais écrite en base de données', 'always-analytics'),
            'detail' => __('Always Analytics utilise l\'adresse IP uniquement en mémoire vive, le temps de calculer le hash ou de géolocaliser le visiteur. Elle n\'est jamais persistée en base. Si votre site était victime d\'une fuite de données, les adresses IP de vos visiteurs ne seraient donc pas compromises — contrairement à ce que fait la majorité des outils analytics.', 'always-analytics'),
            'laws'   => array('RGPD art. 32 — sécurité du traitement', 'RGPD art. 5.1.c — minimisation des données collectées'),
            'action' => null,
            'tab'    => null,
        );

        /* 8. HASH NON RÉVERSIBLE */
        $diag[] = array(
            'level'  => 'ok',
            'cat'    => __('Pseudonymisation', 'always-analytics'),
            'title'  => __('Identifiant visiteur non réversible (SHA-256)', 'always-analytics'),
            'detail' => __('L\'identifiant stocké en base est un hash SHA-256 : une empreinte mathématique à sens unique. Même avec un accès complet à votre base de données, il est impossible de retrouver l\'adresse IP ou l\'identité du visiteur à partir de ce hash. C\'est ce que le RGPD appelle la "pseudonymisation" — une mesure technique qui réduit significativement les risques liés aux données.', 'always-analytics'),
            'laws'   => array('RGPD art. 4.5 — définition de la pseudonymisation', 'RGPD art. 25 — pseudonymisation recommandée comme mesure de privacy by design'),
            'action' => null,
            'tab'    => null,
        );

        /* 9. POLITIQUE DE CONFIDENTIALITÉ */
        if (!$has_policy) {
            $diag[] = array(
                'level'  => 'warn',
                'cat'    => __('Transparence', 'always-analytics'),
                'title'  => __('Politique de confidentialité introuvable', 'always-analytics'),
                'detail' => __('Le RGPD impose d\'informer les visiteurs sur les données que vous collectez, pourquoi, combien de temps, et quels sont leurs droits. Cette information doit figurer dans une politique de confidentialité accessible depuis toutes les pages. WordPress vous aide à la créer : allez dans Réglages → Confidentialité pour désigner ou créer cette page. Sans elle, vous n\'êtes pas transparent sur votre collecte, même si celle-ci est techniquement conforme.', 'always-analytics'),
                'laws'   => array('RGPD art. 13 — obligation d\'information lors de la collecte', 'RGPD art. 14 — information des personnes concernées'),
                'action' => __('Configurer la politique →', 'always-analytics'),
                'tab'    => null,
                'link'   => admin_url('options-privacy.php'),
            );
        } else {
            $diag[] = array(
                'level'  => 'ok',
                'cat'    => __('Transparence', 'always-analytics'),
                'title'  => __('Politique de confidentialité configurée', 'always-analytics'),
                'detail' => __('Une page de politique de confidentialité est désignée dans WordPress. Assurez-vous qu\'elle mentionne explicitement l\'usage d\'Always Analytics : finalité (mesure d\'audience), données collectées (visites anonymes), durée de conservation, et droits des visiteurs (accès, effacement). WordPress propose un guide de rédaction directement accessible ci-dessous.', 'always-analytics'),
                'laws'   => array('RGPD art. 13 — obligation d\'information', 'RGPD art. 15 à 22 — droits des personnes (accès, rectification, effacement)'),
                'action' => __('Consulter le guide de rédaction →', 'always-analytics'),
                'tab'    => null,
                'link'   => admin_url('options-privacy.php?tab=policyguide'),
            );
        }

        /* ── Calcul du score global ──────────────────────────────────────── */
        /* info et exempt ne participent PAS au score (ni bonus ni malus)   */
        $n_danger  = 0; $n_warn = 0; $n_ok = 0; $n_info = 0; $n_exempt = 0;
        foreach ($diag as $d) {
            if ($d['level'] === 'danger')      $n_danger++;
            elseif ($d['level'] === 'warn')    $n_warn++;
            elseif ($d['level'] === 'ok')      $n_ok++;
            elseif ($d['level'] === 'exempt')  $n_exempt++;
            else                               $n_info++;
        }
        /* On ne compte que les checks "réels" : ok + warn + danger */
        $scored_checks = $n_ok + $n_warn + $n_danger;
        if ($scored_checks > 0) {
            $score_pts  = $n_ok * 10 - $n_danger * 20 - $n_warn * 5;
            $score_pct  = max(0, min(100, (int) round(($score_pts / ($scored_checks * 10)) * 100)));
        } else {
            $score_pct = 100;
        }

        if ($n_danger > 0)      { $score_label = __('Non conforme', 'always-analytics');   $score_color = '#dc2626'; $score_bg = 'rgba(220,38,38,.08)'; }
        elseif ($n_warn > 0)    { $score_label = __('À améliorer', 'always-analytics');     $score_color = '#d97706'; $score_bg = 'rgba(217,119,6,.08)'; }
        else                    { $score_label = __('Conforme RGPD', 'always-analytics');   $score_color = '#059669'; $score_bg = 'rgba(5,150,105,.08)'; }

        /* ── SVG icônes inline ───────────────────────────────────────────── */
        $ico_ok     = '<svg class="as-diag-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        $ico_warn   = '<svg class="as-diag-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        $ico_danger = '<svg class="as-diag-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        $ico_info   = '<svg class="as-diag-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="18" x2="15" y2="18"/><line x1="10" y1="22" x2="14" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>';
        $ico_exempt = '<svg class="as-diag-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>';
        ?>

        <!-- Score global -->
        <div class="as-rgpd-score" style="background:<?php echo esc_attr($score_bg); ?>; border-color:<?php echo esc_attr($score_color); ?>30;">
            <div class="as-rgpd-score__gauge">
                <svg viewBox="0 0 120 120" class="as-score-svg">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo esc_attr($score_color); ?>20" stroke-width="10"/>
                    <circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo esc_attr($score_color); ?>" stroke-width="10"
                            stroke-dasharray="<?php echo esc_attr(round(326.726 * $score_pct / 100, 2)); ?> 326.726"
                            stroke-linecap="round" transform="rotate(-90 60 60)"/>
                    <text x="60" y="56" text-anchor="middle" font-size="26" font-weight="700" fill="<?php echo esc_attr($score_color); ?>"><?php echo esc_html($score_pct); ?></text>
                    <text x="60" y="72" text-anchor="middle" font-size="12" fill="<?php echo esc_attr($score_color); ?>">/ 100</text>
                </svg>
            </div>
            <div class="as-rgpd-score__body">
                <div class="as-rgpd-score__label" style="color:<?php echo esc_attr($score_color); ?>"><?php echo esc_html($score_label); ?></div>
                <div class="as-rgpd-score__sub">
                    <?php if ($n_danger > 0): ?>
                        <span class="as-score-chip mod-danger"><?php echo esc_html($n_danger); ?> point<?php echo $n_danger > 1 ? 's' : ''; ?> critique<?php echo $n_danger > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($n_warn > 0): ?>
                        <span class="as-score-chip mod-warn"><?php echo esc_html($n_warn); ?> avertissement<?php echo $n_warn > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($n_ok + $n_exempt > 0): ?>
                        <span class="as-score-chip mod-ok"><?php echo esc_html($n_ok + $n_exempt); ?> point<?php echo ($n_ok + $n_exempt) > 1 ? 's' : ''; ?> conforme<?php echo ($n_ok + $n_exempt) > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($n_info > 0): ?>
                        <span class="as-score-chip mod-info"><?php echo esc_html($n_info); ?> conseil<?php echo $n_info > 1 ? 's' : ''; ?> pour aller plus loin</span>
                    <?php endif; ?>
                </div>
                <p class="as-rgpd-score__desc">
                    <?php if ($n_danger > 0): ?>
                        <?php esc_html_e('Des actions immédiates sont nécessaires pour respecter le RGPD et éviter tout risque de sanction CNIL. Suivez les recommandations ci-dessous.', 'always-analytics'); ?>
                    <?php elseif ($n_warn > 0): ?>
                        <?php esc_html_e('Votre configuration est globalement correcte. Quelques ajustements permettront d\'atteindre la conformité totale recommandée par la CNIL.', 'always-analytics'); ?>
                    <?php else: ?>
                        <?php esc_html_e('Félicitations ! Votre configuration respecte l\'ensemble des recommandations CNIL. Aucune action requise.', 'always-analytics'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <p class="as-rgpd-score__scope">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;flex-shrink:0;vertical-align:middle;margin-right:4px;opacity:.6"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <?php esc_html_e('Ce score évalue uniquement la configuration d\'Always Analytics. Il ne couvre pas les autres traitements de données de votre site (formulaires, commentaires, plugins tiers, publicité…) qui peuvent nécessiter des mesures RGPD complémentaires.', 'always-analytics'); ?>
        </p>

        <?php
        /* ── Groupes : priorités d'abord ────────────────────────────────── */
        $order = array('danger', 'warn', 'ok', 'exempt', 'info');
        usort($diag, function ($a, $b) use ($order) {
            return array_search($a['level'], $order, true) - array_search($b['level'], $order, true);
        });

        $level_labels = array(
            'danger' => __('Action requise', 'always-analytics'),
            'warn'   => __('Avertissement', 'always-analytics'),
            'ok'     => __('Conforme', 'always-analytics'),
            'exempt' => __('Exempté', 'always-analytics'),
            'info'   => __('Pour aller plus loin', 'always-analytics'),
        );
        $level_icons = array(
            'danger' => $ico_danger,
            'warn'   => $ico_warn,
            'ok'     => $ico_ok,
            'exempt' => $ico_exempt,
            'info'   => $ico_info,
        );
        ?>

        <!-- Liste des diagnostics -->
        <div class="as-diag-list">
            <?php foreach ($diag as $d): ?>
            <div class="as-diag-item as-diag-item--<?php echo esc_attr($d['level']); ?>">
                <div class="as-diag-item__icon">
                    <?php echo $level_icons[$d['level']]; ?>
                </div>
                <div class="as-diag-item__body">
                    <div class="as-diag-item__head">
                        <span class="as-diag-item__cat"><?php echo esc_html($d['cat']); ?></span>
                        <span class="as-diag-item__badge as-diag-badge--<?php echo esc_attr($d['level']); ?>"><?php echo esc_html($level_labels[$d['level']]); ?></span>
                    </div>
                    <strong class="as-diag-item__title"><?php echo esc_html($d['title']); ?></strong>
                    <p class="as-diag-item__detail"><?php echo esc_html($d['detail']); ?></p>
                    <?php if (!empty($d['laws'])): ?>
                    <div class="as-diag-item__laws">
                        <?php foreach ($d['laws'] as $law): ?>
                        <span class="as-law-tag as-law-tag--diag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0;vertical-align:middle;margin-right:3px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg><?php echo esc_html($law); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($d['action'])): ?>
                        <?php if (!empty($d['link'])): ?>
                            <a href="<?php echo esc_url($d['link']); ?>" class="as-diag-item__cta" target="_blank">
                                <?php echo esc_html($d['action']); ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:11px;height:11px;vertical-align:middle;margin-left:3px"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        <?php else: ?>
                            <a href="#" class="as-diag-item__cta aa-settings-tab-link" data-tab="<?php echo esc_attr($d['tab']); ?>">
                                <?php echo esc_html($d['action']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Note légale -->
        <div class="as-rgpd-footer-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;flex-shrink:0;opacity:.5;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span><?php esc_html_e('Ce diagnostic est basé sur les recommandations CNIL (délibération n°2020-091) et le RGPD (règlement EU 2016/679). Il ne constitue pas un avis juridique. En cas de doute, consultez un délégué à la protection des données (DPO).', 'always-analytics'); ?></span>
        </div>

    </div>

    <div class="aa-footer">
        <p>Always Analytics v<?php echo esc_html(AA_VERSION); ?> · <a href="<?php echo esc_url(admin_url('admin.php?page=always-analytics')); ?>"><?php esc_html_e('Dashboard', 'always-analytics'); ?></a> · <a href="<?php echo esc_url(admin_url('options-privacy.php')); ?>"><?php esc_html_e('Politique de confidentialité', 'always-analytics'); ?></a></p>
    </div>

</div><!-- .wrap -->

<style>
/* ─── Misc header ──────────────────────────────────────────────────────────── */
.as-version-badge {
    font-size: 12px; font-weight: 500;
    color: var(--aa-text-secondary);
    background: var(--aa-bg);
    border: 1px solid var(--aa-border);
    border-radius: 20px;
    padding: 3px 11px;
}

/* ─── Status banner ────────────────────────────────────────────────────────── */
.as-status-banner {
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid var(--aa-border);
    border-radius: var(--aa-radius);
    box-shadow: var(--aa-shadow);
    margin-bottom: 16px;
}
/* No colored border on banner — chips carry the status signal */
.as-status-banner__icon { font-size: 22px; flex-shrink: 0; }
.as-status-banner__body { flex: 1; font-size: 13px; line-height: 1.5; color: var(--aa-text-secondary); }
.as-status-banner__body strong { display: block; font-size: 13px; font-weight: 700; color: var(--aa-text); margin-bottom: 2px; }
.as-status-banner__chips { display: flex; gap: 8px; flex-wrap: wrap; }

/* ─── Chips ────────────────────────────────────────────────────────────────── */
.as-chip {
    font-size: 11px; font-weight: 500;
    padding: 3px 10px; border-radius: 20px;
    background: var(--aa-bg);
    color: var(--aa-text-secondary);
    border: 1px solid var(--aa-border);
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
    border: 1px solid var(--aa-border);
    border-radius: var(--aa-radius);
    box-shadow: var(--aa-shadow);
    margin-bottom: 14px;
    font-size: 13px;
}
.as-alert.mod-warn   { border-left: 3px solid var(--aa-warning); }
.as-alert.mod-danger { border-left: 3px solid var(--aa-danger); }
.as-alert__icon {
    flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; margin-top: 1px;
}
.as-alert.mod-warn   .as-alert__icon { background: rgba(245,158,11,.12); color: #92400e; }
.as-alert.mod-danger .as-alert__icon { background: rgba(239,68,68,.12);  color: #b91c1c; }
.as-alert__body { flex: 1; color: var(--aa-text-secondary); line-height: 1.5; }
.as-alert__body strong { display: block; font-weight: 700; color: var(--aa-text); margin-bottom: 3px; }
.as-alert__cta {
    align-self: center; flex-shrink: 0;
    font-size: 12px; font-weight: 600;
    color: var(--aa-primary) !important;
    border: 1px solid var(--aa-primary);
    border-radius: 6px; padding: 5px 14px;
    text-decoration: none; transition: all .15s; white-space: nowrap;
}
.as-alert__cta:hover { background: var(--aa-primary); color: #fff !important; }

/* ─── Tab bar ──────────────────────────────────────────────────────────────── */
.as-tabs {
    display: flex; gap: 2px; flex-wrap: wrap;
    background: #fff;
    border: 1px solid var(--aa-border);
    border-radius: var(--aa-radius);
    padding: 5px;
    margin-bottom: 24px;
    box-shadow: var(--aa-shadow);
}
.as-tab {
    padding: 8px 16px;
    border: none; background: transparent;
    border-radius: 7px;
    font-size: 13px; font-weight: 500;
    color: var(--aa-text-secondary);
    cursor: pointer; transition: all .15s;
    position: relative; white-space: nowrap;
}
.as-tab:hover:not(.active) { background: var(--aa-bg); color: var(--aa-text); }
.as-tab.active { background: var(--aa-primary); color: #fff; font-weight: 600; }
.as-tab-dot {
    position: absolute; top: 6px; right: 6px;
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--aa-danger);
}
.as-panel { display: none; }
.as-panel.active { display: block; }

/* ─── Card ─────────────────────────────────────────────────────────────────── */
.as-card {
    background: #fff;
    border: 1px solid var(--aa-border);
    border-radius: var(--aa-radius);
    box-shadow: var(--aa-shadow);
    margin-bottom: 20px;
    overflow: hidden;
}
.as-card__head {
    padding: 20px 28px 16px;
    border-bottom: 1px solid var(--aa-border);
}
.as-card__head h2 {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    font-size: 15px; font-weight: 700; color: var(--aa-text);
    margin: 0 0 5px; padding: 0;
}
.as-card__head p {
    font-size: 13px; color: var(--aa-text-secondary);
    margin: 0; line-height: 1.5;
}
.as-card__body { padding: 24px 28px; }

/* ─── Setting row ──────────────────────────────────────────────────────────── */
.as-row {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0 32px;
    padding: 22px 28px;
    border-bottom: 1px solid var(--aa-border);
    align-items: start;
}
.as-row--last { border-bottom: none; }
.as-row--warn { background: rgba(245,158,11,.04); }
.as-row__label { display: flex; flex-direction: column; gap: 5px; }
.as-row__title { font-size: 13px; font-weight: 600; color: var(--aa-text); display: flex; align-items: center; gap: 8px; }
.as-row__desc  { font-size: 12px; color: var(--aa-text-secondary); line-height: 1.6; }
.as-row__control { padding-top: 2px; }
.as-row__control--inline { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start; }
.as-row__alert {
    grid-column: 1 / -1;
    padding: 0 0 16px;
}
.as-row__alert .as-inline-alert {
    margin-top: 0;
    border-radius: 8px;
}

/* ─── Toggle switch ────────────────────────────────────────────────────────── */
.as-toggle { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
.as-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.as-toggle__track {
    position: relative; width: 38px; height: 22px;
    background: var(--aa-border); border-radius: 11px;
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
.as-toggle input:checked ~ .as-toggle__track { background: var(--aa-primary); }
.as-toggle input:checked ~ .as-toggle__track::after { transform: translateX(16px); }
.as-toggle__label { font-size: 13px; color: var(--aa-text-secondary); }

/* ─── Radio group ──────────────────────────────────────────────────────────── */
.as-radio-group { display: flex; flex-direction: column; gap: 12px; }
.as-radio { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
.as-radio input { position: absolute; opacity: 0; width: 0; height: 0; }
.as-radio__box {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid var(--aa-border);
    flex-shrink: 0; margin-top: 2px;
    background: #fff; transition: border-color .15s;
    position: relative;
}
.as-radio__box::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 8px; height: 8px;
    border-radius: 50%; background: var(--aa-primary);
    opacity: 0; transform: scale(.5); transition: all .15s;
}
.as-radio input:checked ~ .as-radio__box { border-color: var(--aa-primary); }
.as-radio input:checked ~ .as-radio__box::after { opacity: 1; transform: scale(1); }
.as-radio span:last-child { display: flex; flex-direction: column; gap: 2px; }
.as-radio span:last-child strong { font-size: 13px; font-weight: 600; color: var(--aa-text); }
.as-radio span:last-child small  { font-size: 12px; color: var(--aa-text-secondary); }

/* ─── Checkbox group ───────────────────────────────────────────────────────── */
.as-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px 20px; }
.as-checkbox { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--aa-text); }
.as-checkbox input { position: absolute; opacity: 0; width: 0; height: 0; }
.as-checkbox__box {
    width: 16px; height: 16px; border-radius: 4px;
    border: 2px solid var(--aa-border);
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
.as-checkbox input:checked ~ .as-checkbox__box { background: var(--aa-primary); border-color: var(--aa-primary); }
.as-checkbox input:checked ~ .as-checkbox__box::after { transform: rotate(45deg) scale(1); }

/* ─── Form inputs ──────────────────────────────────────────────────────────── */
.as-input, .as-input-full, .as-textarea, .as-select, .as-input-number {
    border: 1px solid var(--aa-border);
    border-radius: 7px;
    padding: 8px 12px;
    font-size: 13px;
    background: #fff;
    color: var(--aa-text);
    transition: border-color .2s, box-shadow .2s;
}
.as-input-full, .as-textarea { width: 100%; max-width: 520px; }
.as-textarea { resize: vertical; }
.as-input-number { width: 90px; }
.as-select { min-width: 180px; cursor: pointer; }
.as-input:focus, .as-input-full:focus, .as-textarea:focus,
.as-select:focus, .as-input-number:focus {
    border-color: var(--aa-primary);
    box-shadow: 0 0 0 3px rgba(108,99,255,.1);
    outline: none;
}

.as-input-group { display: flex; flex-direction: column; gap: 5px; }
.as-input-group__label { font-size: 11px; font-weight: 600; color: var(--aa-text-secondary); text-transform: uppercase; letter-spacing: .4px; }
.as-input-group .as-input { width: 180px; }

.as-input-suffix { display: inline-flex; align-items: center; gap: 8px; }
.as-input-suffix__unit { font-size: 12px; color: var(--aa-text-secondary); }

/* ─── Color pickers ────────────────────────────────────────────────────────── */
.as-color-group { display: flex; flex-direction: column; gap: 5px; align-items: flex-start; }
.as-color-group label { font-size: 11px; font-weight: 600; color: var(--aa-text-secondary); text-transform: uppercase; letter-spacing: .4px; }
.as-color { width: 48px; height: 32px; padding: 2px; border: 1px solid var(--aa-border); border-radius: 6px; cursor: pointer; }

/* ─── Inline badge ─────────────────────────────────────────────────────────── */
.as-inline-badge {
    font-size: 11px; font-weight: 600;
    padding: 2px 9px; border-radius: 20px;
    background: var(--aa-bg); color: var(--aa-text-secondary);
    border: 1px solid var(--aa-border);
}
.as-inline-badge.mod-ok     { background: rgba(16,185,129,.1); color: #065f46; border-color: rgba(16,185,129,.25); }
.as-inline-badge.mod-warn   { background: rgba(245,158,11,.1); color: #92400e; border-color: rgba(245,158,11,.25); }
.as-inline-badge.mod-danger { background: rgba(239,68,68,.1);  color: #b91c1c; border-color: rgba(239,68,68,.25); }

/* ─── Note ─────────────────────────────────────────────────────────────────── */
.as-note {
    display: flex; gap: 12px;
    padding: 14px 18px;
    background: var(--aa-bg);
    border: 1px solid var(--aa-border);
    border-radius: var(--aa-radius);
    font-size: 13px; color: var(--aa-text-secondary);
    line-height: 1.6; margin-bottom: 20px;
}
.as-note__icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; opacity: .6; }
.as-note strong { display: block; font-weight: 600; color: var(--aa-text); margin-bottom: 3px; }

/* ─── Notice banners ────────────────────────────────────────────────────────── */
.as-notice {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 14px 18px;
    border-radius: var(--aa-radius);
    font-size: 13px; line-height: 1.6;
    margin-top: 12px; margin-bottom: 4px;
    border: 1px solid transparent;
}
.as-notice--success {
    background: rgba(16,185,129,.08);
    border-color: rgba(16,185,129,.25);
    color: #065f46;
}
.as-notice__icon {
    width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px;
    stroke: #059669;
}
.as-notice strong { display: block; font-weight: 600; margin-bottom: 3px; color: #065f46; }

/* ─── Save bar ─────────────────────────────────────────────────────────────── */
.as-save-bar {
    position: sticky; bottom: 16px; z-index: 99;
    margin-bottom: 32px;
}
.as-save-btn {
    background: var(--aa-primary) !important;
    border-color: var(--aa-primary-dark) !important;
    border-radius: 8px !important;
    padding: 9px 30px !important;
    font-size: 13px !important; font-weight: 600 !important;
    box-shadow: 0 2px 8px rgba(108,99,255,.3) !important;
}
.as-save-btn:hover { background: var(--aa-primary-dark) !important; }

/* ─── RGPD checklist ───────────────────────────────────────────────────────── */
.as-checklist { }
.as-checklist__row {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 16px 28px;
    border-bottom: 1px solid var(--aa-border);
    transition: background .12s;
}
.as-checklist__row:last-child { border-bottom: none; }
.as-checklist__row:hover { background: var(--aa-bg); }
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
.as-checklist__label  { font-size: 13px; font-weight: 600; color: var(--aa-text); }
.as-checklist__detail { font-size: 12px; color: var(--aa-text-secondary); line-height: 1.5; }
.as-checklist__badge {
    flex-shrink: 0; align-self: flex-start; margin-top: 2px;
    font-size: 11px; font-weight: 600;
    padding: 3px 11px; border-radius: 20px;
    background: var(--aa-bg); color: var(--aa-text-secondary);
    border: 1px solid var(--aa-border); white-space: nowrap;
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

/* ─── RGPD Diagnostic system ────────────────────────────────────────────────── */

/* Score card */
.as-rgpd-score {
    display: flex; align-items: center; gap: 24px;
    padding: 24px 28px;
    border: 1px solid;
    border-radius: var(--aa-radius);
    margin-bottom: 20px;
    background: #fff;
}
.as-rgpd-score__gauge { flex-shrink: 0; }
.as-score-svg { width: 100px; height: 100px; }
.as-rgpd-score__body { flex: 1; }
.as-rgpd-score__label { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
.as-rgpd-score__sub { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.as-rgpd-score__desc { font-size: 12px; color: var(--aa-text-secondary); margin: 0 0 8px; line-height: 1.6; }
.as-rgpd-score__scope {
    font-size: 11px; color: var(--aa-text-secondary);
    line-height: 1.55; margin: 12px 0 0;
    padding: 8px 12px;
    background: var(--aa-bg);
    border: 1px solid var(--aa-border);
    border-radius: 6px;
    display: flex; align-items: flex-start; gap: 4px;
}
.as-score-chip {
    font-size: 11px; font-weight: 600; padding: 2px 10px;
    border-radius: 20px; border: 1px solid transparent;
}
.as-score-chip.mod-danger { background: rgba(220,38,38,.1); color: #b91c1c; border-color: rgba(220,38,38,.2); }
.as-score-chip.mod-warn   { background: rgba(217,119,6,.1);  color: #92400e; border-color: rgba(217,119,6,.2); }
.as-score-chip.mod-ok     { background: rgba(5,150,105,.1);  color: #065f46; border-color: rgba(5,150,105,.2); }
.as-score-chip.mod-info   { background: rgba(100,116,139,.1); color: #475569; border-color: rgba(100,116,139,.2); }
.as-rgpd-score__desc { font-size: 13px; color: var(--aa-text-secondary); line-height: 1.6; margin: 0; }

/* Diagnostic list */
.as-diag-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.as-diag-item {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid var(--aa-border);
    border-left: 4px solid transparent;
    border-radius: var(--aa-radius);
    box-shadow: var(--aa-shadow);
    transition: box-shadow .15s;
}
.as-diag-item:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.as-diag-item--danger { border-left-color: #dc2626; }
.as-diag-item--warn   { border-left-color: #d97706; }
.as-diag-item--ok     { border-left-color: var(--aa-border); }
.as-diag-item--exempt { border-left-color: var(--aa-border); }
.as-diag-item--info   { border-left-color: var(--aa-border); border-left-style: solid; opacity: .85; }

.as-diag-item__icon {
    flex-shrink: 0; width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin-top: 1px;
}
.as-diag-item--danger .as-diag-item__icon { background: rgba(220,38,38,.1); }
.as-diag-item--warn   .as-diag-item__icon { background: rgba(217,119,6,.1); }
.as-diag-item--ok     .as-diag-item__icon { background: rgba(5,150,105,.1); }
.as-diag-item--exempt .as-diag-item__icon { background: rgba(108,99,255,.1); }
.as-diag-item--info   .as-diag-item__icon { background: rgba(100,116,139,.08); }
.as-diag-item--info   .as-diag-icon { stroke: #94a3b8; }
.as-diag-icon { width: 16px; height: 16px; }
.as-diag-item--danger .as-diag-icon { stroke: #dc2626; }
.as-diag-item--warn   .as-diag-icon { stroke: #d97706; }
.as-diag-item--ok     .as-diag-icon { stroke: #059669; }
.as-diag-item--exempt .as-diag-icon { stroke: #6c63ff; }
.as-diag-item--info   .as-diag-icon { stroke: #0ea5e9; }

.as-diag-item__body { flex: 1; min-width: 0; }
.as-diag-item__head {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 4px;
}
.as-diag-item__cat {
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .05em; color: var(--aa-text-secondary);
}
.as-diag-item__badge {
    font-size: 10px; font-weight: 700; padding: 1px 8px;
    border-radius: 20px; border: 1px solid transparent;
    text-transform: uppercase; letter-spacing: .04em;
}
.as-diag-badge--danger { background: rgba(220,38,38,.1); color: #b91c1c; border-color: rgba(220,38,38,.2); }
.as-diag-badge--warn   { background: rgba(217,119,6,.1);  color: #92400e; border-color: rgba(217,119,6,.2); }
.as-diag-badge--ok     { background: rgba(5,150,105,.1);  color: #065f46; border-color: rgba(5,150,105,.2); }
.as-diag-badge--exempt { background: rgba(108,99,255,.1); color: #4c1d95; border-color: rgba(108,99,255,.2); }
.as-diag-badge--info   { background: rgba(100,116,139,.08); color: #475569; border-color: rgba(100,116,139,.15); }

.as-diag-item__title { display: block; font-size: 13px; font-weight: 600; color: var(--aa-text); margin-bottom: 5px; }
.as-diag-item__detail { font-size: 12px; color: var(--aa-text-secondary); line-height: 1.65; margin: 0 0 7px; }
.as-diag-item__laws {
    display: flex; flex-wrap: wrap; gap: 5px;
    margin: 4px 0 8px;
}
.as-law-tag--diag {
    font-size: 10px; font-weight: 500;
    padding: 2px 8px; border-radius: 4px;
    background: var(--aa-bg); color: var(--aa-text-secondary);
    border: 1px solid var(--aa-border);
}
.as-diag-item__cta {
    display: inline-flex; align-items: center;
    font-size: 12px; font-weight: 600;
    color: var(--aa-primary) !important;
    text-decoration: none; transition: opacity .15s;
}
.as-diag-item__cta:hover { opacity: .75; }

/* Footer note */
.as-rgpd-footer-note {
    display: flex; gap: 8px; align-items: flex-start;
    font-size: 11px; color: var(--aa-text-secondary);
    line-height: 1.6; padding: 12px 16px;
    background: var(--aa-bg); border-radius: var(--aa-radius);
    border: 1px solid var(--aa-border);
    margin-bottom: 16px;
}

@media (max-width: 600px) {
    .as-rgpd-score { flex-direction: column; gap: 16px; }
    .as-score-svg { width: 80px; height: 80px; }
    .as-diag-item { padding: 14px 16px; }
}
</style>

<style>
/* ─── Inline RGPD alerts ────────────────────────────────────────────────────── */
.as-inline-alert {
    display: flex; gap: 10px; align-items: flex-start;
    margin-top: 10px; padding: 11px 14px;
    border-radius: 8px; font-size: 12px; line-height: 1.6;
    border: 1px solid transparent;
}
.as-inline-alert.as-inline-alert--hidden { display: none; }
.as-inline-alert.as-inline-alert--danger {
    background: rgba(220,38,38,.07); border-color: rgba(220,38,38,.25); color: #7f1d1d;
}
.as-inline-alert.as-inline-alert--warn {
    background: rgba(217,119,6,.07);  border-color: rgba(217,119,6,.25);  color: #78350f;
}
.as-inline-alert.as-inline-alert--ok-hidden { display: none; }
.as-inline-alert__icon {
    flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px;
}
.as-inline-alert--danger .as-inline-alert__icon { stroke: #dc2626; }
.as-inline-alert--warn   .as-inline-alert__icon { stroke: #d97706; }
.as-inline-alert strong { display: block; font-weight: 700; margin-bottom: 2px; }
.as-inline-alert__laws {
    display: flex; flex-wrap: wrap; gap: 5px;
    margin: 7px 0 5px;
}
.as-law-tag {
    display: inline-flex; align-items: center;
    font-size: 10px; font-weight: 600;
    padding: 2px 8px; border-radius: 4px;
    background: rgba(0,0,0,.05); color: inherit;
    border: 1px solid rgba(0,0,0,.1);
    white-space: nowrap;
}
.as-inline-alert--danger .as-law-tag { background: rgba(220,38,38,.08); border-color: rgba(220,38,38,.15); }
.as-inline-alert--warn   .as-law-tag { background: rgba(217,119,6,.08);  border-color: rgba(217,119,6,.15); }
.as-inline-alert__link {
    display: inline-block; margin-top: 5px;
    font-weight: 600; font-size: 12px;
    color: var(--aa-primary) !important; text-decoration: none;
}
.as-inline-alert__link:hover { text-decoration: underline; }
</style>

<script>
(function () {
    'use strict';
    var tabs    = document.querySelectorAll('.as-tab');
    var panels  = document.querySelectorAll('.as-panel');
    var savebar = document.getElementById('aa-save-bar');
    var tabField = document.getElementById('aa-active-tab-field');
    var formTabs = ['tracking','privacy','consent','performance'];
    var STORE_KEY = 'aa_active_tab';

    function show(tab) {
        tabs.forEach(function(t)   { t.classList.toggle('active', t.dataset.tab === tab); });
        panels.forEach(function(p) { p.classList.toggle('active', p.dataset.panel === tab); });
        if (savebar) savebar.style.display = formTabs.indexOf(tab) !== -1 ? '' : 'none';
        if (tabField) tabField.value = tab;
    }

    tabs.forEach(function(t) {
        t.addEventListener('click', function() {
            show(t.dataset.tab);
            try { sessionStorage.setItem(STORE_KEY, t.dataset.tab); } catch(e) {}
        });
    });

    document.querySelectorAll('.aa-settings-tab-link').forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            if (a.dataset.tab) {
                show(a.dataset.tab);
                try { sessionStorage.setItem(STORE_KEY, a.dataset.tab); } catch(e) {}
                window.scrollTo({top:0,behavior:'smooth'});
            }
        });
    });

    /* Mémoriser l'onglet actif avant soumission du formulaire */
    var form = document.getElementById('aa-settings-form');
    if (form) {
        form.addEventListener('submit', function() {
            var active = document.querySelector('.as-tab.active');
            if (active) {
                try { sessionStorage.setItem(STORE_KEY, active.dataset.tab); } catch(e) {}
            }
        });
    }

    /* Restaurer l'onglet après sauvegarde (settings-updated=true dans l'URL) */
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('settings-updated') === 'true') {
        try {
            var saved = sessionStorage.getItem(STORE_KEY);
            if (saved) { show(saved); sessionStorage.removeItem(STORE_KEY); }
        } catch(e) {}
    } else if (window.location.hash === '#tab-consent') {
        show('consent');
    }
})();
</script>
