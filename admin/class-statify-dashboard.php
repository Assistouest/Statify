<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPress native dashboard widget.
 */
class Statify_Dashboard {

    /**
     * Register dashboard widgets.
     */
    public function register_widgets() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'statify_overview',
            __( '📊 Statistiques — Aperçu', 'statify' ),
            array( $this, 'render_widget' )
        );
    }

    /**
     * Render the overview widget on the WP dashboard.
     */
    public function render_widget() {
        global $wpdb;
        $table = $wpdb->prefix . 'statify_hits';

        // Today's stats
        $today = gmdate( 'Y-m-d' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $today_stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(DISTINCT visitor_hash) as visitors,
                COUNT(*) as page_views
             FROM {$table}
             WHERE DATE(hit_at) = %s",
            $today
        ) );

        // Last 7 days
        $week_ago = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $week_stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(DISTINCT visitor_hash) as visitors,
                COUNT(*) as page_views
             FROM {$table}
             WHERE hit_at >= %s",
            $week_ago . ' 00:00:00'
        ) );

        // Mini sparkline data (last 7 days)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $daily_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(hit_at) as date, COUNT(DISTINCT visitor_hash) as visitors
             FROM {$table}
             WHERE hit_at >= %s
             GROUP BY DATE(hit_at)
             ORDER BY date ASC",
            $week_ago . ' 00:00:00'
        ) );

        $sparkline_values = array();
        for ( $i = 6; $i >= 0; $i-- ) {
            $d = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $found = false;
            foreach ( $daily_data as $row ) {
                if ( $row->date === $d ) {
                    $sparkline_values[] = (int) $row->visitors;
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $sparkline_values[] = 0;
            }
        }

        ?>
        <div style="display:flex;gap:20px;margin-bottom:16px;">
            <div style="flex:1;text-align:center;padding:12px;background:#f0f0f1;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#1d2327;">
                    <?php echo esc_html( number_format_i18n( $today_stats->visitors ?? 0 ) ); ?>
                </div>
                <div style="font-size:12px;color:#646970;margin-top:4px;">
                    <?php esc_html_e( 'Visiteurs aujourd\'hui', 'statify' ); ?>
                </div>
            </div>
            <div style="flex:1;text-align:center;padding:12px;background:#f0f0f1;border-radius:8px;">
                <div style="font-size:24px;font-weight:700;color:#1d2327;">
                    <?php echo esc_html( number_format_i18n( $today_stats->page_views ?? 0 ) ); ?>
                </div>
                <div style="font-size:12px;color:#646970;margin-top:4px;">
                    <?php esc_html_e( 'Pages vues aujourd\'hui', 'statify' ); ?>
                </div>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <strong><?php esc_html_e( '7 derniers jours', 'statify' ); ?></strong>
            <span style="float:right;color:#2271b1;">
                <?php echo esc_html( number_format_i18n( $week_stats->visitors ?? 0 ) ); ?>
                <?php esc_html_e( 'visiteurs', 'statify' ); ?> ·
                <?php echo esc_html( number_format_i18n( $week_stats->page_views ?? 0 ) ); ?>
                <?php esc_html_e( 'pages', 'statify' ); ?>
            </span>
        </div>

        <!-- Mini sparkline via inline SVG -->
        <div style="height:40px;margin-bottom:12px;">
            <?php
            $max = max( 1, max( $sparkline_values ) );
            $points = array();
            foreach ( $sparkline_values as $i => $val ) {
                $x = round( $i * ( 100 / 6 ), 1 );
                $y = round( 40 - ( $val / $max * 36 ), 1 );
                $points[] = "{$x},{$y}";
            }
            $polyline = implode( ' ', $points );
            // Fill area
            $area = "0,40 {$polyline} 100,40";
            ?>
            <svg viewBox="0 0 100 40" preserveAspectRatio="none" style="width:100%;height:100%;">
                <defs>
                    <linearGradient id="statify-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#6c63ff" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#6c63ff" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <polygon points="<?php echo esc_attr( $area ); ?>" fill="url(#statify-grad)"/>
                <polyline points="<?php echo esc_attr( $polyline ); ?>"
                          fill="none" stroke="#6c63ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <p style="text-align:center;margin:0;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify' ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'Voir les statistiques complètes →', 'statify' ); ?>
            </a>
        </p>
        <?php
    }
}
