<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page controller.
 */
class Statify_Admin {

    /**
     * Register admin menu pages.
     */
 public function add_menu_pages() {
        add_menu_page(
            __( 'Statify', 'statify' ),
            __( 'Statify', 'statify' ),
            'manage_options',
            'statify',
            array( $this, 'render_dashboard_page' ),
            // Utilisation du SVG à la racine du plugin
            STATIFY_PLUGIN_URL . 'Statify.svg', 
            30
        );

        add_submenu_page(
            'statify',
            __( 'Dashboard', 'statify' ),
            __( 'Dashboard', 'statify' ),
            'manage_options',
            'statify',
            array( $this, 'render_dashboard_page' )
        );

        // Engagement — visible submenu entry, before Réglages
        add_submenu_page(
            'statify',
            __( 'Engagement', 'statify' ),
            __( 'Engagement', 'statify' ),
            'manage_options',
            'statify-engagement',
            array( $this, 'render_engagement_page' )
        );

        add_submenu_page(
            'statify',
            __( 'Réglages', 'statify' ),
            __( 'Réglages', 'statify' ),
            'manage_options',
            'statify-settings',
            array( $this, 'render_settings_page' )
        );

        // Hidden pages (accessible via link only)
        add_submenu_page(
            null,
            __( 'Statify — Détail', 'statify' ),
            __( 'Détail', 'statify' ),
            'manage_options',
            'statify-visitor',
            array( $this, 'render_visitor_page' )
        );

        add_submenu_page(
            null,
            __( 'Top Pages', 'statify' ),
            __( 'Top Pages', 'statify' ),
            'manage_options',
            'statify-top-pages',
            array( $this, 'render_top_pages_page' )
        );

        add_submenu_page(
            null,
            __( 'Pays', 'statify' ),
            __( 'Pays', 'statify' ),
            'manage_options',
            'statify-countries',
            array( $this, 'render_countries_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_assets( $hook ) {
        
        $custom_icon_css = "
        #toplevel_page_statify .wp-menu-image img {
            width: 20px !important;
            height: auto !important;
            padding: 0 !important;
            max-height: 20px;
        }
        #toplevel_page_statify .wp-menu-image {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
    ";
    // On l'attache à 'admin-bar' car ce style est chargé sur toutes les pages
    wp_add_inline_style( 'admin-bar', $custom_icon_css );
        // Only load on our pages
        if ( strpos( $hook, 'statify' ) === false && 'index.php' !== $hook ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'statify-admin',
            STATIFY_PLUGIN_URL . 'admin/css/statify-admin.css',
            array(),
            STATIFY_VERSION
        );

        // Chart.js (from CDN — lightweight)
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
            array(),
            '4.4.7',
            true
        );

        // Admin charts
        wp_enqueue_script(
            'statify-charts',
            STATIFY_PLUGIN_URL . 'admin/js/statify-charts.js',
            array( 'chartjs' ),
            STATIFY_VERSION,
            true
        );

        // Admin interactions
        wp_enqueue_script(
            'statify-admin',
            STATIFY_PLUGIN_URL . 'admin/js/statify-admin.js',
            array( 'statify-charts', 'wp-api-fetch' ),
            STATIFY_VERSION,
            true
        );

        wp_localize_script( 'statify-admin', 'statifyAdmin', array(
            'restBase'    => esc_url_raw( rest_url( 'statify/v1/' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'exportNonce' => wp_create_nonce( 'statify_export' ),
            'i18n'        => array(
                'visitors'   => __( 'Visiteurs', 'statify' ),
                'pageViews'  => __( 'Pages vues', 'statify' ),
                'sessions'   => __( 'Sessions', 'statify' ),
                'noData'     => __( 'Aucune donnée pour cette période', 'statify' ),
                'loading'    => __( 'Chargement…', 'statify' ),
                'export'     => __( 'Exporter', 'statify' ),
            ),
        ) );
        
     

    }

    /**
     * Render floating refresh button on all Statify pages.
     */
    public function render_refresh_button() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'statify' ) === false ) {
            return;
        }
        $rest_base = esc_url_raw( rest_url( 'statify/v1/' ) );
        $nonce     = wp_create_nonce( 'wp_rest' );
        ?>
        <style>
            #statify-refresh-btn {
                position: fixed;
                bottom: 32px;
                right: 32px;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 8px;
                background: #6c63ff;
                color: #fff;
                border: none;
                border-radius: 50px;
                padding: 12px 22px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 16px rgba(108,99,255,0.35);
                transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            }
            #statify-refresh-btn:hover  { background:#5549e0; transform:translateY(-1px); color:#fff; }
            #statify-refresh-btn:active { transform:scale(0.97); }
            #statify-refresh-btn:disabled { opacity:.6; cursor:default; transform:none; }
            #statify-refresh-btn.loading svg { animation:statify-spin .7s linear infinite; }
            #statify-refresh-btn.done { background:#16a34a; }
            #statify-refresh-btn.error { background:#dc2626; }
            @keyframes statify-spin { to { transform:rotate(360deg); } }
        </style>

        <button id="statify-refresh-btn">
            <svg id="statify-refresh-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            <span id="statify-refresh-label"><?php esc_html_e( 'Actualiser', 'statify' ); ?></span>
        </button>

        <script>
        (function () {
            var btn   = document.getElementById('statify-refresh-btn');
            var label = document.getElementById('statify-refresh-label');

            btn.addEventListener('click', function () {
                btn.disabled = true;
                btn.classList.add('loading');
                btn.classList.remove('done','error');
                label.textContent = 'Chargement…';

                // On appelle directement loadAllData() qui utilise désormais
                // cache:'no-store' + _t=timestamp sur CHAQUE requête.
                // Plus besoin de "pre-bust" séparé qui causait une double requête.
                if (typeof window.loadAllData === 'function') {
                    window.loadAllData();
                    // Petit délai pour que les fetches aient le temps de partir
                    setTimeout(function () {
                        btn.classList.remove('loading');
                        btn.classList.add('done');
                        label.textContent = '✓ Mis à jour';
                        setTimeout(function () {
                            btn.classList.remove('done');
                            label.textContent = 'Actualiser';
                            btn.disabled = false;
                        }, 2000);
                    }, 800);
                } else {
                    // Fallback : recharger la page
                    window.location.reload();
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Render the main dashboard page.
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès non autorisé.', 'statify' ) );
        }
        include STATIFY_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once STATIFY_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render the visitor detail page.
     */
    public function render_visitor_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once STATIFY_PLUGIN_DIR . 'admin/views/visitor-detail.php';
    }

    /**
     * Render the top pages full view.
     */
    public function render_top_pages_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once STATIFY_PLUGIN_DIR . 'admin/views/top-pages.php';
    }

    /**
     * Render the countries full view.
     */
    public function render_countries_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once STATIFY_PLUGIN_DIR . 'admin/views/countries.php';
    }

    /**
     * Render the engagement full view.
     */
    public function render_engagement_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once STATIFY_PLUGIN_DIR . 'admin/views/engagement.php';
    }
}
