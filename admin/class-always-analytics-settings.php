<?php
namespace Always_Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page — registers and manages plugin options.
 */
class Always_Analytics_Settings
{

    /**
     * Register all settings.
     */
    public function register_settings()
    {
        register_setting('always_analytics_settings', 'always_analytics_options', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_options'),
        ));

        // Section: General
        add_settings_section('aa_general', __('Général', 'always-analytics'), null, 'always-analytics-settings');

        add_settings_field('disable_tracking', __('Désactiver le tracking', 'always-analytics'),
            array($this, 'render_checkbox'), 'always-analytics-settings', 'aa_general',
            array('field' => 'disable_tracking', 'desc' => __('Suspendre temporairement la collecte de données', 'always-analytics'))
        );

        add_settings_field('tracking_mode', __('Mode de tracking', 'always-analytics'),
            array($this, 'render_tracking_mode'), 'always-analytics-settings', 'aa_general');

        add_settings_field('excluded_roles', __('Rôles exclus', 'always-analytics'),
            array($this, 'render_excluded_roles'), 'always-analytics-settings', 'aa_general');

        add_settings_field('excluded_ips', __('IPs exclues', 'always-analytics'),
            array($this, 'render_textarea'), 'always-analytics-settings', 'aa_general',
            array('field' => 'excluded_ips', 'desc' => __('Une IP par ligne', 'always-analytics'))
        );

        add_settings_field('trusted_proxy_mode', __('Proxy de confiance', 'always-analytics'),
            array($this, 'render_trusted_proxy_mode'), 'always-analytics-settings', 'aa_general');

        add_settings_field('trusted_proxies', __('IPs des proxys personnalisés', 'always-analytics'),
            array($this, 'render_textarea'), 'always-analytics-settings', 'aa_general',
            array('field' => 'trusted_proxies', 'desc' => __('Si "Proxy spécifique" est sélectionné. Une IP ou CIDR par ligne.', 'always-analytics'))
        );

        // Section: Privacy
        add_settings_section('aa_privacy', __('Confidentialité & RGPD', 'always-analytics'), null, 'always-analytics-settings');

        add_settings_field('anonymize_ip', __('Anonymiser les IPs', 'always-analytics'),
            array($this, 'render_checkbox'), 'always-analytics-settings', 'aa_privacy',
            array('field' => 'anonymize_ip', 'desc' => __('Masquer le dernier octet IPv4 / derniers 80 bits IPv6', 'always-analytics'))
        );

        add_settings_field('cookieless_window', __('Fenêtre d\'unicité (mode sans cookie)', 'always-analytics'),
            array($this, 'render_cookieless_window'), 'always-analytics-settings', 'aa_privacy');

        add_settings_field('retention_days', __('Durée de rétention', 'always-analytics'),
            array($this, 'render_retention'), 'always-analytics-settings', 'aa_privacy');

        add_settings_field('delete_on_uninstall', __('Supprimer à la désinstallation', 'always-analytics'),
            array($this, 'render_checkbox'), 'always-analytics-settings', 'aa_privacy',
            array('field' => 'delete_on_uninstall', 'desc' => __('Supprimer toutes les données quand le plugin est désinstallé', 'always-analytics'))
        );

        // Section: Consent Banner
        add_settings_section('aa_consent', __('Bannière de consentement', 'always-analytics'), null, 'always-analytics-settings');

        add_settings_field('consent_enabled', __('Activer la bannière', 'always-analytics'),
            array($this, 'render_checkbox'), 'always-analytics-settings', 'aa_consent',
            array('field' => 'consent_enabled', 'desc' => __('Afficher un bandeau de consentement aux visiteurs (recommandé en mode cookie)', 'always-analytics'))
        );

        add_settings_field('consent_message', __('Message', 'always-analytics'),
            array($this, 'render_text'), 'always-analytics-settings', 'aa_consent',
            array('field' => 'consent_message', 'class' => 'large-text')
        );

        add_settings_field('consent_accept', __('Texte bouton accepter', 'always-analytics'),
            array($this, 'render_text'), 'always-analytics-settings', 'aa_consent',
            array('field' => 'consent_accept')
        );

        add_settings_field('consent_decline', __('Texte bouton refuser', 'always-analytics'),
            array($this, 'render_text'), 'always-analytics-settings', 'aa_consent',
            array('field' => 'consent_decline')
        );

        add_settings_field('consent_colors', __('Couleurs', 'always-analytics'),
            array($this, 'render_consent_colors'), 'always-analytics-settings', 'aa_consent');

        // Section: Geolocation
        add_settings_section('aa_geo', __('Géolocalisation', 'always-analytics'), null, 'always-analytics-settings');

        add_settings_field('geo_enabled', __('Activer la géolocalisation', 'always-analytics'),
            array($this, 'render_checkbox'), 'always-analytics-settings', 'aa_geo',
            array('field' => 'geo_enabled', 'desc' => __('Résoudre les adresses IP en localisation géographique', 'always-analytics'))
        );

        // Section: Performance
        add_settings_section('aa_perf', __('Performance', 'always-analytics'), null, 'always-analytics-settings');

        add_settings_field('cache_ttl', __('Durée du cache (secondes)', 'always-analytics'),
            array($this, 'render_number'), 'always-analytics-settings', 'aa_perf',
            array('field' => 'cache_ttl', 'min' => 60, 'max' => 3600)
        );

        add_settings_field('bot_filter_mode', __('Filtrage des bots', 'always-analytics'),
            array($this, 'render_bot_filter'), 'always-analytics-settings', 'aa_perf');

        // Section: Export
        add_settings_section('always_analytics_export', __('Export', 'always-analytics'), null, 'always-analytics-settings');

        add_settings_field('export_format', __('Format par défaut', 'always-analytics'),
            array($this, 'render_export_format'), 'always-analytics-settings', 'always_analytics_export');
    }

    /**
     * Sanitize all options before saving.
     */
    public function sanitize_options($input)
    {
        $output = get_option('always_analytics_options', array());

        // Checkboxes
        foreach (array('disable_tracking', 'anonymize_ip', 'delete_on_uninstall', 'geo_enabled', 'consent_enabled') as $key) {
            $output[$key] = !empty($input[$key]);
        }

        // Text fields
        foreach (array('consent_message', 'consent_accept', 'consent_decline') as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
        }

        // geo_provider is always 'native' — MaxMind and external providers removed.
        $output['geo_provider']    = 'native';
        $output['maxmind_db_path'] = '';

        // Colors
        foreach (array('consent_bg_color', 'consent_text_color', 'consent_btn_color') as $key) {
            $output[$key] = isset($input[$key]) ? sanitize_hex_color($input[$key]) : '';
        }

        // Selects
        $output['tracking_mode'] = isset($input['tracking_mode']) && in_array($input['tracking_mode'], array('cookieless', 'cookie'), true) ? $input['tracking_mode'] : 'cookieless';
        $output['bot_filter_mode'] = isset($input['bot_filter_mode']) && in_array($input['bot_filter_mode'], array('strict', 'normal', 'off'), true) ? $input['bot_filter_mode'] : 'normal';
        $output['export_format'] = isset($input['export_format']) && in_array($input['export_format'], array('csv', 'json'), true) ? $input['export_format'] : 'csv';
        $output['cookieless_window'] = isset($input['cookieless_window']) && in_array($input['cookieless_window'], array('daily', 'session'), true) ? $input['cookieless_window'] : 'daily';

        // Numbers
        $output['retention_days'] = isset($input['retention_days']) ? absint($input['retention_days']) : 90;
        $output['cache_ttl'] = isset($input['cache_ttl']) ? min(3600, max(60, absint($input['cache_ttl']))) : 300;

        // Textarea
        $output['excluded_ips'] = isset($input['excluded_ips']) ? sanitize_textarea_field($input['excluded_ips']) : '';
        $output['trusted_proxies'] = isset($input['trusted_proxies']) ? sanitize_textarea_field($input['trusted_proxies']) : '';

        // Proxy mode
        $output['trusted_proxy_mode'] = isset($input['trusted_proxy_mode']) && in_array($input['trusted_proxy_mode'], array('none', 'custom'), true) ? $input['trusted_proxy_mode'] : 'none';

        // Roles (multi-checkbox)
        $output['excluded_roles'] = isset($input['excluded_roles']) && is_array($input['excluded_roles'])
            ? array_map('sanitize_key', $input['excluded_roles'])
            : array();

        return $output;
    }

    // ──────────────────────────────────────────────────
    // Field renderers
    // ──────────────────────────────────────────────────

    public function render_checkbox($args)
    {
        $options = get_option('always_analytics_options', array());
        $checked = !empty($options[$args['field']]);
?>
        <label>
            <input type="checkbox" name="always_analytics_options[<?php echo esc_attr($args['field']); ?>]" value="1" <?php checked($checked); ?> />
            <?php if (!empty($args['desc'])): ?>
                <span class="description"><?php echo esc_html($args['desc']); ?></span>
            <?php
        endif; ?>
        </label>
        <?php
    }

    public function render_text($args)
    {
        $options = get_option('always_analytics_options', array());
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        $class = isset($args['class']) ? $args['class'] : 'regular-text';
?>
        <input type="text" name="always_analytics_options[<?php echo esc_attr($args['field']); ?>]"
               value="<?php echo esc_attr($value); ?>" class="<?php echo esc_attr($class); ?>" />
        <?php if (!empty($args['desc'])): ?>
            <p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php
        endif; ?>
        <?php
    }

    public function render_textarea($args)
    {
        $options = get_option('always_analytics_options', array());
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
?>
        <textarea name="always_analytics_options[<?php echo esc_attr($args['field']); ?>]"
                  rows="4" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['desc'])): ?>
            <p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php
        endif; ?>
        <?php
    }

    public function render_number($args)
    {
        $options = get_option('always_analytics_options', array());
        $value = isset($options[$args['field']]) ? absint($options[$args['field']]) : 0;
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 99999;
?>
        <input type="number" name="always_analytics_options[<?php echo esc_attr($args['field']); ?>]"
               value="<?php echo esc_attr($value); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>"
               class="small-text" />
        <?php
    }

    public function render_tracking_mode()
    {
        $options = get_option('always_analytics_options', array());
        $mode = isset($options['tracking_mode']) ? $options['tracking_mode'] : 'cookieless';
?>
        <fieldset>
            <label>
                <input type="radio" name="always_analytics_options[tracking_mode]" value="cookieless" <?php checked($mode, 'cookieless'); ?> />
                <?php esc_html_e('Sans cookie (respectueux de la vie privée, visiteur identifié par jour uniquement)', 'always-analytics'); ?>
            </label><br/>
            <label>
                <input type="radio" name="always_analytics_options[tracking_mode]" value="cookie" <?php checked($mode, 'cookie'); ?> />
                <?php esc_html_e('Avec cookie (meilleur suivi, nécessite consentement)', 'always-analytics'); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Le mode cookie permet de suivre les visiteurs sur plusieurs jours. Activez la bannière de consentement ci-dessous si vous utilisez ce mode.', 'always-analytics'); ?>
        </p>
        <?php
    }

    public function render_trusted_proxy_mode()
    {
        $options = get_option('always_analytics_options', array());
        $mode = isset($options['trusted_proxy_mode']) ? $options['trusted_proxy_mode'] : 'none';
?>
        <fieldset>
            <label>
                <input type="radio" name="always_analytics_options[trusted_proxy_mode]" value="none" <?php checked($mode, 'none'); ?> />
                <?php esc_html_e('Aucun (recommandé si pas de proxy)', 'always-analytics'); ?>
            </label><br/>

            <label>
                <input type="radio" name="always_analytics_options[trusted_proxy_mode]" value="custom" <?php checked($mode, 'custom'); ?> />
                <?php esc_html_e('Proxy spécifique (load balancer, Nginx, etc.)', 'always-analytics'); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Configurez comment Always Analytics détecte l\'adresse IP des visiteurs quand votre site est derrière un proxy.', 'always-analytics'); ?>
        </p>
        <?php
    }

    public function render_excluded_roles()
    {
        $options = get_option('always_analytics_options', array());
        $excluded_roles = isset($options['excluded_roles']) ? (array)$options['excluded_roles'] : array();
        $wp_roles = wp_roles()->get_names();
?>
        <fieldset>
            <?php foreach ($wp_roles as $role_key => $role_name): ?>
                <label>
                    <input type="checkbox" name="always_analytics_options[excluded_roles][]"
                           value="<?php echo esc_attr($role_key); ?>"
                           <?php checked(in_array($role_key, $excluded_roles, true)); ?> />
                    <?php echo esc_html(translate_user_role($role_name)); ?>
                </label><br/>
            <?php
        endforeach; ?>
        </fieldset>
        <?php
    }

    public function render_retention()
    {
        $options = get_option('always_analytics_options', array());
        $days = isset($options['retention_days']) ? absint($options['retention_days']) : 90;
?>
        <select name="always_analytics_options[retention_days]">
            <option value="30" <?php selected($days, 30); ?>>30 <?php esc_html_e('jours', 'always-analytics'); ?></option>
            <option value="90" <?php selected($days, 90); ?>>90 <?php esc_html_e('jours', 'always-analytics'); ?></option>
            <option value="180" <?php selected($days, 180); ?>>180 <?php esc_html_e('jours', 'always-analytics'); ?></option>
            <option value="365" <?php selected($days, 365); ?>>1 <?php esc_html_e('an', 'always-analytics'); ?></option>
            <option value="0" <?php selected($days, 0); ?>><?php esc_html_e('Illimité', 'always-analytics'); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e('Après cette période, les données sont anonymisées (pas supprimées). Les statistiques agrégées et les métriques de distribution sont conservées indéfiniment.', 'always-analytics'); ?>
        </p>
        <?php
    }

    public function render_cookieless_window()
    {
        $options = get_option('always_analytics_options', array());
        $window  = isset($options['cookieless_window']) ? $options['cookieless_window'] : 'daily';
?>
        <fieldset>
            <label style="display:block;margin-bottom:8px;">
                <input type="radio" name="always_analytics_options[cookieless_window]" value="daily" <?php checked($window, 'daily'); ?> />
                <strong><?php esc_html_e('Journalière (Y-m-d)', 'always-analytics'); ?></strong>
                &nbsp;—&nbsp;
                <span class="description"><?php esc_html_e('Un visiteur unique par jour. Hash recalculé chaque minuit UTC. Meilleure précision des métriques.', 'always-analytics'); ?></span>
            </label>
            <label style="display:block;">
                <input type="radio" name="always_analytics_options[cookieless_window]" value="session" <?php checked($window, 'session'); ?> />
                <strong><?php esc_html_e('Session uniquement', 'always-analytics'); ?></strong>
                &nbsp;—&nbsp;
                <span class="description"><?php esc_html_e('Hash lié à la session navigateur (sessionStorage). Aucune persistance entre onglets ou après fermeture. Recommandé par la CNIL pour le mode sans cookie.', 'always-analytics'); ?></span>
            </label>
        </fieldset>
        <p class="description" style="margin-top:8px;color:#b32d2e;">
            ⚠️ <?php esc_html_e('S\'applique au mode sans cookie et au mode pré-consentement RGPD. Sans effet si un cookie visitorId est présent.', 'always-analytics'); ?>
        </p>
        <?php
    }

    public function render_bot_filter()
    {
        $options = get_option('always_analytics_options', array());
        $mode = isset($options['bot_filter_mode']) ? $options['bot_filter_mode'] : 'normal';
?>
        <select name="always_analytics_options[bot_filter_mode]">
            <option value="normal" <?php selected($mode, 'normal'); ?>><?php esc_html_e('Normal', 'always-analytics'); ?></option>
            <option value="strict" <?php selected($mode, 'strict'); ?>><?php esc_html_e('Strict', 'always-analytics'); ?></option>
            <option value="off" <?php selected($mode, 'off'); ?>><?php esc_html_e('Désactivé', 'always-analytics'); ?></option>
        </select>
        <?php
    }

    public function render_export_format()
    {
        $options = get_option('always_analytics_options', array());
        $format = isset($options['export_format']) ? $options['export_format'] : 'csv';
?>
        <select name="always_analytics_options[export_format]">
            <option value="csv" <?php selected($format, 'csv'); ?>>CSV</option>
            <option value="json" <?php selected($format, 'json'); ?>>JSON</option>
        </select>
        <?php
    }

    public function render_consent_colors()
    {
        $options = get_option('always_analytics_options', array());
        $bg_color = isset($options['consent_bg_color']) ? $options['consent_bg_color'] : '#1a1a2e';
        $text_color = isset($options['consent_text_color']) ? $options['consent_text_color'] : '#ffffff';
        $btn_color = isset($options['consent_btn_color']) ? $options['consent_btn_color'] : '#6c63ff';
?>
        <div style="display:flex;gap:24px;flex-wrap:wrap;">
            <div>
                <label><?php esc_html_e('Fond', 'always-analytics'); ?></label><br/>
                <input type="color" name="always_analytics_options[consent_bg_color]" value="<?php echo esc_attr($bg_color); ?>" />
            </div>
            <div>
                <label><?php esc_html_e('Texte', 'always-analytics'); ?></label><br/>
                <input type="color" name="always_analytics_options[consent_text_color]" value="<?php echo esc_attr($text_color); ?>" />
            </div>
            <div>
                <label><?php esc_html_e('Bouton', 'always-analytics'); ?></label><br/>
                <input type="color" name="always_analytics_options[consent_btn_color]" value="<?php echo esc_attr($btn_color); ?>" />
            </div>
        </div>
        <?php
    }
}
