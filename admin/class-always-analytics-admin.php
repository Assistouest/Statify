<?php
namespace Always_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page controller.
 */
class Always_Analytics_Admin {

    /**
     * Register admin menu pages.
     */
 public function add_menu_pages() {
        add_menu_page(
            __( 'Always Analytics', 'always-analytics' ),
            __( 'Always Analytics', 'always-analytics' ),
            'manage_options',
            'always-analytics',
            array( $this, 'render_dashboard_page' ),
            // Utilisation du SVG à la racine du plugin
            AA_PLUGIN_URL . 'always-analytics.svg', 
            30
        );

        add_submenu_page(
            'always-analytics',
            __( 'Dashboard', 'always-analytics' ),
            __( 'Dashboard', 'always-analytics' ),
            'manage_options',
            'always-analytics',
            array( $this, 'render_dashboard_page' )
        );

        // Engagement — visible submenu entry, before Réglages
        add_submenu_page(
            'always-analytics',
            __( 'Engagement', 'always-analytics' ),
            __( 'Engagement', 'always-analytics' ),
            'manage_options',
            'always-analytics-engagement',
            array( $this, 'render_engagement_page' )
        );

        add_submenu_page(
            'always-analytics',
            __( 'Réglages', 'always-analytics' ),
            __( 'Réglages', 'always-analytics' ),
            'manage_options',
            'always-analytics-settings',
            array( $this, 'render_settings_page' )
        );

        // Hidden pages (accessible via link only)
        add_submenu_page(
            null,
            __( 'Always Analytics — Détail', 'always-analytics' ),
            __( 'Détail', 'always-analytics' ),
            'manage_options',
            'always-analytics-visitor',
            array( $this, 'render_visitor_page' )
        );

        add_submenu_page(
            null,
            __( 'Top Pages', 'always-analytics' ),
            __( 'Top Pages', 'always-analytics' ),
            'manage_options',
            'always-analytics-top-pages',
            array( $this, 'render_top_pages_page' )
        );

        add_submenu_page(
            null,
            __( 'Pays', 'always-analytics' ),
            __( 'Pays', 'always-analytics' ),
            'manage_options',
            'always-analytics-countries',
            array( $this, 'render_countries_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_assets( $hook ) {
        
        $custom_icon_css = "
        #toplevel_page_always-analytics .wp-menu-image img {
            width: 20px !important;
            height: auto !important;
            padding: 0 !important;
            max-height: 20px;
        }
        #toplevel_page_always-analytics .wp-menu-image {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
    ";
    // On l'attache à 'admin-bar' car ce style est chargé sur toutes les pages
    wp_add_inline_style( 'admin-bar', $custom_icon_css );
        // Only load on our pages
        if ( strpos( $hook, 'always-analytics' ) === false && 'index.php' !== $hook ) {
            return;
        }

        // CSS — version = timestamp fichier (cache busting automatique à chaque déploiement)
        wp_enqueue_style(
            'always-analytics-admin',
            AA_PLUGIN_URL . 'admin/css/always-analytics-admin.css',
            array(),
            filemtime( AA_PLUGIN_DIR . 'admin/css/always-analytics-admin.css' )
        );

        // Chart.js (local)
        wp_enqueue_script(
            'chartjs',
            AA_PLUGIN_URL . 'admin/js/chart.js',
            array(),
            '4.4.7',
            true
        );

        // Admin charts
        wp_enqueue_script(
            'always-analytics-charts',
            AA_PLUGIN_URL . 'admin/js/always-analytics-charts.js',
            array( 'chartjs' ),
            filemtime( AA_PLUGIN_DIR . 'admin/js/always-analytics-charts.js' ),
            true
        );

        // Admin interactions
        wp_enqueue_script(
            'always-analytics-admin',
            AA_PLUGIN_URL . 'admin/js/always-analytics-admin.js',
            array( 'always-analytics-charts', 'wp-api-fetch' ),
            filemtime( AA_PLUGIN_DIR . 'admin/js/always-analytics-admin.js' ),
            true
        );

        // Chargement des sources référents depuis le fichier de données
        $referrer_sources_file = AA_PLUGIN_DIR . 'data/referrer-sources.php';
        $referrer_sources = file_exists( $referrer_sources_file ) ? include $referrer_sources_file : array();

        wp_localize_script( 'always-analytics-admin', 'alwaysAnalyticsAdmin', array(
            'restBase'        => esc_url_raw( rest_url( 'always-analytics/v1/' ) ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'exportNonce'     => wp_create_nonce( 'always_analytics_export' ),
            'purgeNonce'      => wp_create_nonce( 'always_analytics_manual_purge' ),
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'referrerSources' => $referrer_sources,
            'i18n'            => array(
                'visitors'      => __( 'Visiteurs', 'always-analytics' ),
                'pageViews'     => __( 'Pages vues', 'always-analytics' ),
                'sessions'      => __( 'Sessions', 'always-analytics' ),
                'noData'        => __( 'Aucune donnée pour cette période', 'always-analytics' ),
                'loading'       => __( 'Chargement…', 'always-analytics' ),
                'export'        => __( 'Exporter', 'always-analytics' ),
                'purgeConfirm'  => __( 'Cela va anonymiser les données plus anciennes que la période de rétention. Continuer ?', 'always-analytics' ),
                'purgeSuccess'  => __( 'Anonymisation terminée avec succès.', 'always-analytics' ),
                'purgeError'    => __( 'Erreur lors de l\'anonymisation.', 'always-analytics' ),
            ),
        ) );
        
     

    }

    /**
     * Render floating "Buy Me a Coffee" button on all Always Analytics pages.
     */
    public function render_refresh_button() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'always-analytics' ) === false ) {
            return;
        }
        ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <style>
            #aa-coffee-btn {
                position: fixed;
                bottom: 32px;
                right: 32px;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 8px;
                background: #FFDD00;
                color: #000;
                border: none;
                border-radius: 50px;
                padding: 12px 22px;
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 4px 16px rgba(255,221,0,0.45);
                text-decoration: none;
                transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            }
            #aa-coffee-btn:hover  { background: #f5d400; transform: translateY(-1px); color: #000; box-shadow: 0 6px 20px rgba(255,221,0,0.55); }
            #aa-coffee-btn:active { transform: scale(0.97); }
            #aa-coffee-btn i { font-size: 15px; }
        </style>

        <a id="aa-coffee-btn" href="https://buymeacoffee.com/assistouest" target="_blank" rel="noopener noreferrer">
            <i class="fa-solid fa-mug-hot"></i>
            <?php esc_html_e( 'Offrir un café au créateur', 'always-analytics' ); ?>
        </a>
        <?php
    }

    /**
     * Render the main dashboard page.
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès non autorisé.', 'always-analytics' ) );
        }
        include AA_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AA_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render the visitor detail page.
     */
    public function render_visitor_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AA_PLUGIN_DIR . 'admin/views/visitor-detail.php';
    }

    /**
     * Render the top pages full view.
     */
    public function render_top_pages_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AA_PLUGIN_DIR . 'admin/views/top-pages.php';
    }

    /**
     * Render the countries full view.
     */
    public function render_countries_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AA_PLUGIN_DIR . 'admin/views/countries.php';
    }

    /**
     * Render the engagement full view.
     */
    /**
     * AJAX handler — manual anonymization trigger.
     * Hooked on: wp_ajax_always_analytics_manual_purge
     */
    public function ajax_manual_purge() {
        check_ajax_referer( 'always_analytics_manual_purge', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'always-analytics' ) ), 403 );
        }

        $privacy = new Always_Analytics_Privacy();
        $privacy->purge_old_data();

        wp_send_json_success( array( 'message' => __( 'Anonymisation terminée avec succès.', 'always-analytics' ) ) );
    }

    public function render_engagement_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AA_PLUGIN_DIR . 'admin/views/engagement.php';
    }
}
