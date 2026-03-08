<?php
/**
 * Top Pages full view — Advanced Stats.
 *
 * @package Statify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Date params from URL (passed by dashboard links)
$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-d' );
$to   = isset( $_GET['to'] )   ? sanitize_text_field( wp_unslash( $_GET['to'] ) )   : gmdate( 'Y-m-d' );

// Validate date format
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
    $from = gmdate( 'Y-m-d' );
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
    $to = gmdate( 'Y-m-d' );
}

global $wpdb;
$table = $wpdb->prefix . 'statify_hits';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT
        page_url,
        page_title,
        post_id,
        COUNT(*) as views,
        COUNT(DISTINCT visitor_hash) as unique_visitors,
        COUNT(DISTINCT session_id) as sessions
     FROM {$table}
     WHERE hit_at >= %s AND hit_at <= %s
     GROUP BY page_url, page_title, post_id
     ORDER BY views DESC",
    $from . ' 00:00:00',
    $to . ' 23:59:59'
) );

$max_views = ! empty( $rows ) ? (int) $rows[0]->views : 1;

// Human-readable period label
$label_from = wp_date( 'd/m/Y', strtotime( $from ) );
$label_to   = wp_date( 'd/m/Y', strtotime( $to ) );
$period_label = ( $from === $to ) ? $label_from : $label_from . ' → ' . $label_to;
?>
<div class="wrap statify-wrap">

    <!-- Header -->
    <div class="statify-header" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify&from=' . urlencode( $from ) . '&to=' . urlencode( $to ) ) ); ?>" class="statify-back-btn">
                ← <?php esc_html_e( 'Tableau de bord', 'statify' ); ?>
            </a>
            <div>
                <h1 style="margin:0;font-size:22px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    🔝 <?php esc_html_e( 'Top Pages', 'statify' ); ?>
                </h1>
                <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                    <?php echo esc_html( $period_label ); ?> &middot;
                    <strong><?php echo count( $rows ); ?></strong> <?php esc_html_e( 'pages', 'statify' ); ?>
                </p>
            </div>
        </div>

        <!-- Inline date filter -->
        <div class="statify-detail-date-filter">
            <input type="date" id="tp-from" value="<?php echo esc_attr( $from ); ?>" />
            <span style="color:#94a3b8;">→</span>
            <input type="date" id="tp-to" value="<?php echo esc_attr( $to ); ?>" />
            <button id="tp-apply" class="button button-primary"><?php esc_html_e( 'Appliquer', 'statify' ); ?></button>
        </div>
    </div>

    <!-- Summary KPIs -->
    <?php
    $total_views    = array_sum( array_column( $rows, 'views' ) );
    $total_visitors = array_sum( array_column( $rows, 'unique_visitors' ) );
    $total_sessions = array_sum( array_column( $rows, 'sessions' ) );
    ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
        <div class="statify-kpi-card" style="text-align:center;">
            <div class="statify-kpi-icon">📄</div>
            <div class="statify-kpi-value"><?php echo number_format_i18n( $total_views ); ?></div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Pages vues totales', 'statify' ); ?></div>
        </div>
        <div class="statify-kpi-card" style="text-align:center;">
            <div class="statify-kpi-icon">👁️</div>
            <div class="statify-kpi-value"><?php echo number_format_i18n( $total_visitors ); ?></div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Visiteurs uniques', 'statify' ); ?></div>
        </div>
        <div class="statify-kpi-card" style="text-align:center;">
            <div class="statify-kpi-icon">📋</div>
            <div class="statify-kpi-value"><?php echo number_format_i18n( count( $rows ) ); ?></div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Pages distinctes', 'statify' ); ?></div>
        </div>
    </div>

    <!-- Search + Table -->
    <div class="statify-card">
        <div class="statify-card-header" style="gap:12px;flex-wrap:wrap;">
            <h2 style="margin:0;"><?php esc_html_e( 'Toutes les pages', 'statify' ); ?></h2>
            <input type="search" id="tp-search" placeholder="<?php esc_attr_e( 'Rechercher une page…', 'statify' ); ?>"
                   style="padding:6px 12px;border:1px solid #e2e4e7;border-radius:8px;font-size:13px;width:260px;margin-left:auto;" />
        </div>
        <div class="statify-card-body" style="padding:0;">
            <?php if ( empty( $rows ) ) : ?>
                <p class="statify-no-data" style="padding:40px;text-align:center;">
                    <?php esc_html_e( 'Aucune donnée pour cette période.', 'statify' ); ?>
                </p>
            <?php else : ?>
            <table class="statify-table statify-full-table" id="tp-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th><?php esc_html_e( 'Page', 'statify' ); ?></th>
                        <th style="text-align:right;width:120px;"><?php esc_html_e( 'Vues', 'statify' ); ?></th>
                        <th style="text-align:right;width:130px;"><?php esc_html_e( 'Visiteurs uniques', 'statify' ); ?></th>
                        <th style="text-align:right;width:110px;"><?php esc_html_e( 'Sessions', 'statify' ); ?></th>
                        <th style="width:160px;"><?php esc_html_e( 'Popularité', 'statify' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $i => $row ) :
                        $pct    = $max_views > 0 ? round( ( $row->views / $max_views ) * 100 ) : 0;
                        $title  = ! empty( $row->page_title ) ? $row->page_title : $row->page_url;
                        $rank   = $i + 1;
                        $medal  = $rank === 1 ? '🥇' : ( $rank === 2 ? '🥈' : ( $rank === 3 ? '🥉' : '' ) );
                    ?>
                    <tr class="tp-row">
                        <td style="color:#94a3b8;font-weight:600;font-size:13px;">
                            <?php echo $medal ? esc_html( $medal ) : esc_html( $rank ); ?>
                        </td>
                        <td>
                            <div style="font-weight:600;color:#0f172a;margin-bottom:2px;line-height:1.3;">
                                <?php echo esc_html( $title ); ?>
                            </div>
                            <div style="font-size:12px;color:#3b82f6;">
                                <a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank"
                                   style="color:#3b82f6;text-decoration:none;word-break:break-all;">
                                    <?php echo esc_html( $row->page_url ); ?>
                                </a>
                            </div>
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:15px;color:#0f172a;">
                            <?php echo number_format_i18n( (int) $row->views ); ?>
                        </td>
                        <td style="text-align:right;color:#475569;">
                            <?php echo number_format_i18n( (int) $row->unique_visitors ); ?>
                        </td>
                        <td style="text-align:right;color:#475569;">
                            <?php echo number_format_i18n( (int) $row->sessions ); ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;">
                                    <div style="width:<?php echo esc_attr( $pct ); ?>%;height:100%;background:linear-gradient(90deg,#6c63ff,#8b83ff);border-radius:4px;transition:width .3s;"></div>
                                </div>
                                <span style="font-size:11px;color:#94a3b8;width:32px;text-align:right;"><?php echo esc_html( $pct ); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="statify-footer">
        <p>Statify v<?php echo esc_html( STATIFY_VERSION ); ?></p>
    </div>
</div>

<script>
(function () {
    // Date filter redirect
    var applyBtn = document.getElementById('tp-apply');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var from = document.getElementById('tp-from').value;
            var to   = document.getElementById('tp-to').value;
            if (from && to) {
                window.location.href = '<?php echo esc_js( admin_url( 'admin.php?page=statify-top-pages' ) ); ?>&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
            }
        });
    }

    // Live search filter
    var searchInput = document.getElementById('tp-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            var rows = document.querySelectorAll('#tp-table .tp-row');
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }
})();
</script>
