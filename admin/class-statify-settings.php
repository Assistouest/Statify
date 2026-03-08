<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings page — registers and manages plugin options.
 */
class Statify_Settings {

    /**
     * Register all settings.
     */
    public function register_settings() {
        register_setting( 'statify_settings', 'statify_options', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_options' ),
        ) );

        // Section: General
        add_settings_section( 'statify_general', __( 'Général', 'statify' ), null, 'statify-settings' );

        add_settings_field( 'disable_tracking', __( 'Désactiver le tracking', 'statify' ),
            array( $this, 'render_checkbox' ), 'statify-settings', 'statify_general',
            array( 'field' => 'disable_tracking', 'desc' => __( 'Suspendre temporairement la collecte de données', 'statify' ) )
        );

        add_settings_field( 'tracking_mode', __( 'Mode de tracking', 'statify' ),
            array( $this, 'render_tracking_mode' ), 'statify-settings', 'statify_general' );

        add_settings_field( 'excluded_roles', __( 'Rôles exclus', 'statify' ),
            array( $this, 'render_excluded_roles' ), 'statify-settings', 'statify_general' );

        add_settings_field( 'excluded_ips', __( 'IPs exclues', 'statify' ),
            array( $this, 'render_textarea' ), 'statify-settings', 'statify_general',
            array( 'field' => 'excluded_ips', 'desc' => __( 'Une IP par ligne', 'statify' ) )
        );

        // Section: Privacy
        add_settings_section( 'statify_privacy', __( 'Confidentialité & RGPD', 'statify' ), null, 'statify-settings' );

        add_settings_field( 'anonymize_ip', __( 'Anonymiser les IPs', 'statify' ),
            array( $this, 'render_checkbox' ), 'statify-settings', 'statify_privacy',
            array( 'field' => 'anonymize_ip', 'desc' => __( 'Masquer le dernier octet IPv4 / derniers 80 bits IPv6', 'statify' ) )
        );

        add_settings_field( 'retention_days', __( 'Durée de rétention', 'statify' ),
            array( $this, 'render_retention' ), 'statify-settings', 'statify_privacy' );

        add_settings_field( 'delete_on_uninstall', __( 'Supprimer à la désinstallation', 'statify' ),
            array( $this, 'render_checkbox' ), 'statify-settings', 'statify_privacy',
            array( 'field' => 'delete_on_uninstall', 'desc' => __( 'Supprimer toutes les données quand le plugin est désinstallé', 'statify' ) )
        );

        // Section: Consent Banner
        add_settings_section( 'statify_consent', __( 'Bannière de consentement', 'statify' ), null, 'statify-settings' );

        add_settings_field( 'consent_enabled', __( 'Activer la bannière', 'statify' ),
            array( $this, 'render_checkbox' ), 'statify-settings', 'statify_consent',
            array( 'field' => 'consent_enabled', 'desc' => __( 'Afficher un bandeau de consentement aux visiteurs (recommandé en mode cookie)', 'statify' ) )
        );

        add_settings_field( 'consent_message', __( 'Message', 'statify' ),
            array( $this, 'render_text' ), 'statify-settings', 'statify_consent',
            array( 'field' => 'consent_message', 'class' => 'large-text' )
        );

        add_settings_field( 'consent_accept', __( 'Texte bouton accepter', 'statify' ),
            array( $this, 'render_text' ), 'statify-settings', 'statify_consent',
            array( 'field' => 'consent_accept' )
        );

        add_settings_field( 'consent_decline', __( 'Texte bouton refuser', 'statify' ),
            array( $this, 'render_text' ), 'statify-settings', 'statify_consent',
            array( 'field' => 'consent_decline' )
        );

        add_settings_field( 'consent_colors', __( 'Couleurs', 'statify' ),
            array( $this, 'render_consent_colors' ), 'statify-settings', 'statify_consent' );

        // Section: Geolocation
        add_settings_section( 'statify_geo', __( 'Géolocalisation', 'statify' ), null, 'statify-settings' );

        add_settings_field( 'geo_enabled', __( 'Activer la géolocalisation', 'statify' ),
            array( $this, 'render_checkbox' ), 'statify-settings', 'statify_geo',
            array( 'field' => 'geo_enabled', 'desc' => __( 'Résoudre les adresses IP en localisation géographique', 'statify' ) )
        );

        add_settings_field( 'geo_provider', __( 'Fournisseur', 'statify' ),
            array( $this, 'render_geo_provider' ), 'statify-settings', 'statify_geo' );

        add_settings_field( 'maxmind_db_path', __( 'Chemin base MaxMind', 'statify' ),
            array( $this, 'render_text' ), 'statify-settings', 'statify_geo',
            array( 'field' => 'maxmind_db_path', 'desc' => __( 'Chemin absolu vers GeoLite2-City.mmdb', 'statify' ), 'class' => 'large-text' )
        );

        // Section: Performance
        add_settings_section( 'statify_perf', __( 'Performance', 'statify' ), null, 'statify-settings' );

        add_settings_field( 'cache_ttl', __( 'Durée du cache (secondes)', 'statify' ),
            array( $this, 'render_number' ), 'statify-settings', 'statify_perf',
            array( 'field' => 'cache_ttl', 'min' => 60, 'max' => 3600 )
        );

        add_settings_field( 'bot_filter_mode', __( 'Filtrage des bots', 'statify' ),
            array( $this, 'render_bot_filter' ), 'statify-settings', 'statify_perf' );

        // Section: Export
        add_settings_section( 'statify_export', __( 'Export', 'statify' ), null, 'statify-settings' );

        add_settings_field( 'export_format', __( 'Format par défaut', 'statify' ),
            array( $this, 'render_export_format' ), 'statify-settings', 'statify_export' );
    }

    /**
     * Sanitize all options before saving.
     */
    public function sanitize_options( $input ) {
        $output = get_option( 'statify_options', array() );

        // Checkboxes
        foreach ( array( 'disable_tracking', 'anonymize_ip', 'delete_on_uninstall', 'geo_enabled', 'consent_enabled' ) as $key ) {
            $output[ $key ] = ! empty( $input[ $key ] );
        }

        // Text fields
        foreach ( array( 'consent_message', 'consent_accept', 'consent_decline', 'maxmind_db_path' ) as $key ) {
            $output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
        }

        // Colors
        foreach ( array( 'consent_bg_color', 'consent_text_color', 'consent_btn_color' ) as $key ) {
            $output[ $key ] = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
        }

        // Selects
        $output['tracking_mode']   = isset( $input['tracking_mode'] ) && in_array( $input['tracking_mode'], array( 'cookieless', 'cookie' ), true ) ? $input['tracking_mode'] : 'cookieless';
        $output['geo_provider']    = isset( $input['geo_provider'] ) && in_array( $input['geo_provider'], array( 'native', 'maxmind' ), true ) ? $input['geo_provider'] : 'native';
        $output['bot_filter_mode'] = isset( $input['bot_filter_mode'] ) && in_array( $input['bot_filter_mode'], array( 'strict', 'normal', 'off' ), true ) ? $input['bot_filter_mode'] : 'normal';
        $output['export_format']   = isset( $input['export_format'] ) && in_array( $input['export_format'], array( 'csv', 'json' ), true ) ? $input['export_format'] : 'csv';

        // Numbers
        $output['retention_days'] = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 90;
        $output['cache_ttl']      = isset( $input['cache_ttl'] ) ? min( 3600, max( 60, absint( $input['cache_ttl'] ) ) ) : 300;

        // Textarea
        $output['excluded_ips'] = isset( $input['excluded_ips'] ) ? sanitize_textarea_field( $input['excluded_ips'] ) : '';

        // Roles (multi-checkbox)
        $output['excluded_roles'] = isset( $input['excluded_roles'] ) && is_array( $input['excluded_roles'] )
            ? array_map( 'sanitize_key', $input['excluded_roles'] )
            : array();

        return $output;
    }

    // ──────────────────────────────────────────────────
    // Field renderers
    // ──────────────────────────────────────────────────

    public function render_checkbox( $args ) {
        $options = get_option( 'statify_options', array() );
        $checked = ! empty( $options[ $args['field'] ] );
        ?>
        <label>
            <input type="checkbox" name="statify_options[<?php echo esc_attr( $args['field'] ); ?>]" value="1" <?php checked( $checked ); ?> />
            <?php if ( ! empty( $args['desc'] ) ) : ?>
                <span class="description"><?php echo esc_html( $args['desc'] ); ?></span>
            <?php endif; ?>
        </label>
        <?php
    }

    public function render_text( $args ) {
        $options = get_option( 'statify_options', array() );
        $value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';
        $class   = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        ?>
        <input type="text" name="statify_options[<?php echo esc_attr( $args['field'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>" class="<?php echo esc_attr( $class ); ?>" />
        <?php if ( ! empty( $args['desc'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_textarea( $args ) {
        $options = get_option( 'statify_options', array() );
        $value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';
        ?>
        <textarea name="statify_options[<?php echo esc_attr( $args['field'] ); ?>]"
                  rows="4" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <?php if ( ! empty( $args['desc'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function render_number( $args ) {
        $options = get_option( 'statify_options', array() );
        $value   = isset( $options[ $args['field'] ] ) ? absint( $options[ $args['field'] ] ) : 0;
        $min     = isset( $args['min'] ) ? $args['min'] : 0;
        $max     = isset( $args['max'] ) ? $args['max'] : 99999;
        ?>
        <input type="number" name="statify_options[<?php echo esc_attr( $args['field'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>"
               class="small-text" />
        <?php
    }

    public function render_tracking_mode() {
        $options = get_option( 'statify_options', array() );
        $mode    = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
        ?>
        <fieldset>
            <label>
                <input type="radio" name="statify_options[tracking_mode]" value="cookieless" <?php checked( $mode, 'cookieless' ); ?> />
                <?php esc_html_e( 'Sans cookie (respectueux de la vie privée, visiteur identifié par jour uniquement)', 'statify' ); ?>
            </label><br/>
            <label>
                <input type="radio" name="statify_options[tracking_mode]" value="cookie" <?php checked( $mode, 'cookie' ); ?> />
                <?php esc_html_e( 'Avec cookie (meilleur suivi, nécessite consentement)', 'statify' ); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'Le mode cookie permet de suivre les visiteurs sur plusieurs jours. Activez la bannière de consentement ci-dessous si vous utilisez ce mode.', 'statify' ); ?>
        </p>
        <?php
    }

    public function render_excluded_roles() {
        $options        = get_option( 'statify_options', array() );
        $excluded_roles = isset( $options['excluded_roles'] ) ? (array) $options['excluded_roles'] : array();
        $wp_roles       = wp_roles()->get_names();
        ?>
        <fieldset>
            <?php foreach ( $wp_roles as $role_key => $role_name ) : ?>
                <label>
                    <input type="checkbox" name="statify_options[excluded_roles][]"
                           value="<?php echo esc_attr( $role_key ); ?>"
                           <?php checked( in_array( $role_key, $excluded_roles, true ) ); ?> />
                    <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                </label><br/>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    public function render_retention() {
        $options = get_option( 'statify_options', array() );
        $days    = isset( $options['retention_days'] ) ? absint( $options['retention_days'] ) : 90;
        ?>
        <select name="statify_options[retention_days]">
            <option value="30" <?php selected( $days, 30 ); ?>>30 <?php esc_html_e( 'jours', 'statify' ); ?></option>
            <option value="90" <?php selected( $days, 90 ); ?>>90 <?php esc_html_e( 'jours', 'statify' ); ?></option>
            <option value="180" <?php selected( $days, 180 ); ?>>180 <?php esc_html_e( 'jours', 'statify' ); ?></option>
            <option value="365" <?php selected( $days, 365 ); ?>>1 <?php esc_html_e( 'an', 'statify' ); ?></option>
            <option value="0" <?php selected( $days, 0 ); ?>><?php esc_html_e( 'Illimité', 'statify' ); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Après cette période, les données sont anonymisées (pas supprimées). Les statistiques agrégées et les métriques de distribution sont conservées indéfiniment.', 'statify' ); ?>
        </p>
        <?php
    }

    public function render_geo_provider() {
        $options  = get_option( 'statify_options', array() );
        $provider = isset( $options['geo_provider'] ) ? $options['geo_provider'] : 'native';
        ?>
        <select name="statify_options[geo_provider]">
            <option value="native" <?php selected( $provider, 'native' ); ?>>
                <?php esc_html_e( 'Natif (sans clé API)', 'statify' ); ?>
            </option>
            <option value="maxmind" <?php selected( $provider, 'maxmind' ); ?>>
                <?php esc_html_e( 'MaxMind GeoLite2 (précision avancée)', 'statify' ); ?>
            </option>
        </select>
        <?php
    }

    public function render_bot_filter() {
        $options = get_option( 'statify_options', array() );
        $mode    = isset( $options['bot_filter_mode'] ) ? $options['bot_filter_mode'] : 'normal';
        ?>
        <select name="statify_options[bot_filter_mode]">
            <option value="normal" <?php selected( $mode, 'normal' ); ?>><?php esc_html_e( 'Normal', 'statify' ); ?></option>
            <option value="strict" <?php selected( $mode, 'strict' ); ?>><?php esc_html_e( 'Strict', 'statify' ); ?></option>
            <option value="off" <?php selected( $mode, 'off' ); ?>><?php esc_html_e( 'Désactivé', 'statify' ); ?></option>
        </select>
        <?php
    }

    public function render_export_format() {
        $options = get_option( 'statify_options', array() );
        $format  = isset( $options['export_format'] ) ? $options['export_format'] : 'csv';
        ?>
        <select name="statify_options[export_format]">
            <option value="csv" <?php selected( $format, 'csv' ); ?>>CSV</option>
            <option value="json" <?php selected( $format, 'json' ); ?>>JSON</option>
        </select>
        <?php
    }

    public function render_consent_colors() {
        $options   = get_option( 'statify_options', array() );
        $bg_color   = isset( $options['consent_bg_color'] ) ? $options['consent_bg_color'] : '#1a1a2e';
        $text_color = isset( $options['consent_text_color'] ) ? $options['consent_text_color'] : '#ffffff';
        $btn_color  = isset( $options['consent_btn_color'] ) ? $options['consent_btn_color'] : '#6c63ff';
        ?>
        <div style="display:flex;gap:24px;flex-wrap:wrap;">
            <div>
                <label><?php esc_html_e( 'Fond', 'statify' ); ?></label><br/>
                <input type="color" name="statify_options[consent_bg_color]" value="<?php echo esc_attr( $bg_color ); ?>" />
            </div>
            <div>
                <label><?php esc_html_e( 'Texte', 'statify' ); ?></label><br/>
                <input type="color" name="statify_options[consent_text_color]" value="<?php echo esc_attr( $text_color ); ?>" />
            </div>
            <div>
                <label><?php esc_html_e( 'Bouton', 'statify' ); ?></label><br/>
                <input type="color" name="statify_options[consent_btn_color]" value="<?php echo esc_attr( $btn_color ); ?>" />
            </div>
        </div>
        <?php
    }
}
