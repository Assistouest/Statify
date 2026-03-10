<?php
/**
 * Dashboard view template — Advanced Stats.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap aa-wrap">
    <div class="aa-header">
        <h1>
<img src="<?php echo esc_url( AA_PLUGIN_URL . 'always-analytics.svg' ); ?>" 
                 alt="Always Analytics Logo" 
                 style="width: 32px; height: 32px; vertical-align: middle;">            <?php esc_html_e( 'Always Analytics', 'always-analytics' ); ?>
        </h1>
        <div class="aa-header-actions">
            <div class="aa-date-filter">
                <select id="aa-period">
                    <option value="today" selected><?php esc_html_e( 'Aujourd\'hui', 'always-analytics' ); ?></option>
                    <option value="7days"><?php esc_html_e( '7 derniers jours', 'always-analytics' ); ?></option>
                    <option value="30days"><?php esc_html_e( '30 derniers jours', 'always-analytics' ); ?></option>
                    <option value="90days"><?php esc_html_e( '90 derniers jours', 'always-analytics' ); ?></option>
                    <option value="year"><?php esc_html_e( 'Cette année', 'always-analytics' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Personnalisé', 'always-analytics' ); ?></option>
                </select>
                <div id="aa-custom-dates" style="display:none;">
                    <input type="date" id="aa-from" />
                    <span>→</span>
                    <input type="date" id="aa-to" />
                    <button id="aa-apply-dates" class="button"><?php esc_html_e( 'Appliquer', 'always-analytics' ); ?></button>
                </div>
            </div>
            <div class="aa-filters">
                <select id="aa-device-filter">
                    <option value=""><?php esc_html_e( 'Tous les appareils', 'always-analytics' ); ?></option>
                    <option value="desktop"><?php esc_html_e( 'Desktop', 'always-analytics' ); ?></option>
                    <option value="mobile"><?php esc_html_e( 'Mobile', 'always-analytics' ); ?></option>
                    <option value="tablet"><?php esc_html_e( 'Tablette', 'always-analytics' ); ?></option>
                </select>
            </div>

        </div>
    </div>

    <!-- KPI Cards -->
    <div class="aa-kpis" id="aa-kpis">
        <div class="aa-kpi-card" data-metric="unique_visitors">
            <div class="aa-kpi-value" id="kpi-visitors"></div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Visiteurs uniques', 'always-analytics' ); ?></div>
            <div class="aa-kpi-change" id="kpi-visitors-change"></div>
        </div>
        <div class="aa-kpi-card" data-metric="page_views">
            <div class="aa-kpi-value" id="kpi-pageviews"></div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Pages vues', 'always-analytics' ); ?></div>
            <div class="aa-kpi-change" id="kpi-pageviews-change"></div>
        </div>
        <div class="aa-kpi-card" data-metric="sessions">
            <div class="aa-kpi-value" id="kpi-sessions">—</div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></div>
            <div class="aa-kpi-change" id="kpi-sessions-change"></div>
        </div>
        <div class="aa-kpi-card" data-metric="avg_duration">
            <div class="aa-kpi-value" id="kpi-duration">—</div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Durée moyenne', 'always-analytics' ); ?></div>
            <div class="aa-kpi-change" id="kpi-duration-change"></div>
        </div>
        <div class="aa-kpi-card" data-metric="engagement_rate">
            <div class="aa-kpi-value" id="kpi-bounce">—</div>
            <div class="aa-kpi-label"><?php esc_html_e( 'Engagement', 'always-analytics' ); ?></div>
            <div class="aa-kpi-change" id="kpi-bounce-change"></div>
            <div style="margin-top:10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics-engagement' ) ); ?>"
                   style="font-size:11px;color:#6c63ff;text-decoration:none;font-weight:600;letter-spacing:0.3px;">
                    Plus d'infos →
                </a>
            </div>
        </div>
    </div>

    <!-- Main Chart -->
    <div class="aa-card aa-chart-card">
        <div class="aa-card-header">
            <h2><?php esc_html_e( 'Visites', 'always-analytics' ); ?></h2>
            <div class="aa-chart-toggles">
                <button class="aa-toggle active" data-dataset="visitors"><?php esc_html_e( 'Visiteurs', 'always-analytics' ); ?></button>
                <button class="aa-toggle" data-dataset="page_views"><?php esc_html_e( 'Pages vues', 'always-analytics' ); ?></button>
                <button class="aa-toggle" data-dataset="sessions"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></button>
            </div>
        </div>
        <div class="aa-chart-container">
            <canvas id="aa-visits-chart"></canvas>
        </div>
    </div>

    <!-- Recent Visitors -->
    <div class="aa-card" style="margin-bottom: 24px;">
        <div class="aa-card-header">
            <h2><?php esc_html_e( 'Derniers visiteurs', 'always-analytics' ); ?></h2>
        </div>
        <div class="aa-card-body">
            <table class="aa-table" id="aa-recent-visitors">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Visiteur', 'always-analytics' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'always-analytics' ); ?></th>
                        <th style="text-align:center"><?php esc_html_e( 'Pages vues', 'always-analytics' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'Détails', 'always-analytics' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Grid: Top Pages + Countries -->
    <div class="aa-grid">
        <div class="aa-card">
            <div class="aa-card-header">
                <h2><?php esc_html_e( 'Contenus les plus vus', 'always-analytics' ); ?></h2>
                <a id="aa-all-pages-link" href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics-top-pages' ) ); ?>" class="aa-see-all-btn">
                    <?php esc_html_e( 'Voir tout', 'always-analytics' ); ?> 
                </a>
            </div>
            <div class="aa-card-body">
                <table class="aa-table" id="aa-top-pages">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Page', 'always-analytics' ); ?></th>
                            <th><?php esc_html_e( 'Vues', 'always-analytics' ); ?></th>
                            <th><?php esc_html_e( 'Visiteurs', 'always-analytics' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="aa-card">
            <div class="aa-card-header">
                <h2><?php esc_html_e( 'Pays', 'always-analytics' ); ?></h2>
                <a id="aa-all-countries-link" href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics-countries' ) ); ?>" class="aa-see-all-btn">
                    <?php esc_html_e( 'Voir tout', 'always-analytics' ); ?> →
                </a>
            </div>
            <div class="aa-card-body">
                <table class="aa-table" id="aa-countries">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Pays', 'always-analytics' ); ?></th>
                            <th><?php esc_html_e( 'Visites', 'always-analytics' ); ?></th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Grid: Referrers + Devices -->
    <div class="aa-grid">
        <div class="aa-card aa-referrers-card">
            <div class="aa-card-header aa-ref-header">
                <h2><?php esc_html_e( 'Référents', 'always-analytics' ); ?></h2>
                <div class="aa-ref-tabs" role="tablist">
                    <span class="aa-ref-tab aa-ref-tab--active" data-cat="all"    role="tab" tabindex="0"><?php esc_html_e( 'Tous', 'always-analytics' ); ?></span>
                    <span class="aa-ref-tab" data-cat="search" role="tab" tabindex="0"><?php esc_html_e( 'Moteurs', 'always-analytics' ); ?></span>
                    <span class="aa-ref-tab" data-cat="social" role="tab" tabindex="0"><?php esc_html_e( 'Réseaux', 'always-analytics' ); ?></span>
                    <span class="aa-ref-tab" data-cat="ai"     role="tab" tabindex="0"><?php esc_html_e( 'IA', 'always-analytics' ); ?></span>
                    <span class="aa-ref-tab" data-cat="site"   role="tab" tabindex="0"><?php esc_html_e( 'Sites', 'always-analytics' ); ?></span>
                </div>
            </div>
            <div class="aa-ref-body">
                <div id="aa-referrers-list"></div>
            </div>
        </div>

        <div class="aa-card aa-devices-card">
            <div class="aa-card-header aa-dev-header">
                <h2><?php esc_html_e( 'Appareils & Navigateurs', 'always-analytics' ); ?></h2>
                <div class="aa-dev-tabs" role="tablist">
                    <span class="aa-dev-tab aa-dev-tab--active" data-device="all"     role="tab" tabindex="0"><?php esc_html_e( 'Tous', 'always-analytics' ); ?></span>
                    <span class="aa-dev-tab" data-device="desktop" role="tab" tabindex="0">🖥 <?php esc_html_e( 'Desktop', 'always-analytics' ); ?></span>
                    <span class="aa-dev-tab" data-device="mobile"  role="tab" tabindex="0">📱 <?php esc_html_e( 'Mobile', 'always-analytics' ); ?></span>
                    <span class="aa-dev-tab" data-device="tablet"  role="tab" tabindex="0">⬜ <?php esc_html_e( 'Tablette', 'always-analytics' ); ?></span>
                </div>
            </div>
            <div class="aa-dev-body">
                <div id="aa-devices-list"></div>
            </div>
        </div>
    </div>

    <div class="aa-footer">
        <p>
            Always Analytics v<?php echo esc_html( AA_VERSION ); ?> 
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics-settings' ) ); ?>">
                <?php esc_html_e( 'Réglages', 'always-analytics' ); ?>
            </a>
        </p>
    </div>
</div>
