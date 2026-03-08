<?php
/**
 * Dashboard view template — Advanced Stats.
 *
 * @package Statify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap statify-wrap">
    <div class="statify-header">
        <h1>
<img src="<?php echo esc_url( STATIFY_PLUGIN_URL . 'Statify.svg' ); ?>" 
                 alt="Statify Logo" 
                 style="width: 32px; height: 32px; vertical-align: middle;">            <?php esc_html_e( 'Statify', 'statify' ); ?>
        </h1>
        <div class="statify-header-actions">
            <div class="statify-date-filter">
                <select id="statify-period">
                    <option value="today" selected><?php esc_html_e( 'Aujourd\'hui', 'statify' ); ?></option>
                    <option value="7days"><?php esc_html_e( '7 derniers jours', 'statify' ); ?></option>
                    <option value="30days"><?php esc_html_e( '30 derniers jours', 'statify' ); ?></option>
                    <option value="90days"><?php esc_html_e( '90 derniers jours', 'statify' ); ?></option>
                    <option value="year"><?php esc_html_e( 'Cette année', 'statify' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Personnalisé', 'statify' ); ?></option>
                </select>
                <div id="statify-custom-dates" style="display:none;">
                    <input type="date" id="statify-from" />
                    <span>→</span>
                    <input type="date" id="statify-to" />
                    <button id="statify-apply-dates" class="button"><?php esc_html_e( 'Appliquer', 'statify' ); ?></button>
                </div>
            </div>
            <div class="statify-filters">
                <select id="statify-device-filter">
                    <option value=""><?php esc_html_e( 'Tous les appareils', 'statify' ); ?></option>
                    <option value="desktop"><?php esc_html_e( 'Desktop', 'statify' ); ?></option>
                    <option value="mobile"><?php esc_html_e( 'Mobile', 'statify' ); ?></option>
                    <option value="tablet"><?php esc_html_e( 'Tablette', 'statify' ); ?></option>
                </select>
            </div>

        </div>
    </div>

    <!-- KPI Cards -->
    <div class="statify-kpis" id="statify-kpis">
        <div class="statify-kpi-card" data-metric="unique_visitors">
            <div class="statify-kpi-value" id="kpi-visitors"></div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Visiteurs uniques', 'statify' ); ?></div>
            <div class="statify-kpi-change" id="kpi-visitors-change"></div>
        </div>
        <div class="statify-kpi-card" data-metric="page_views">
            <div class="statify-kpi-value" id="kpi-pageviews"></div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Pages vues', 'statify' ); ?></div>
            <div class="statify-kpi-change" id="kpi-pageviews-change"></div>
        </div>
        <div class="statify-kpi-card" data-metric="sessions">
            <div class="statify-kpi-value" id="kpi-sessions">—</div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Sessions', 'statify' ); ?></div>
            <div class="statify-kpi-change" id="kpi-sessions-change"></div>
        </div>
        <div class="statify-kpi-card" data-metric="avg_duration">
            <div class="statify-kpi-value" id="kpi-duration">—</div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Durée moyenne', 'statify' ); ?></div>
            <div class="statify-kpi-change" id="kpi-duration-change"></div>
        </div>
        <div class="statify-kpi-card" data-metric="engagement_rate">
            <div class="statify-kpi-value" id="kpi-bounce">—</div>
            <div class="statify-kpi-label"><?php esc_html_e( 'Engagement', 'statify' ); ?></div>
            <div class="statify-kpi-change" id="kpi-bounce-change"></div>
            <div style="margin-top:10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify-engagement' ) ); ?>"
                   style="font-size:11px;color:#6c63ff;text-decoration:none;font-weight:600;letter-spacing:0.3px;">
                    Plus d'infos →
                </a>
            </div>
        </div>
    </div>

    <!-- Main Chart -->
    <div class="statify-card statify-chart-card">
        <div class="statify-card-header">
            <h2><?php esc_html_e( 'Visites', 'statify' ); ?></h2>
            <div class="statify-chart-toggles">
                <button class="statify-toggle active" data-dataset="visitors"><?php esc_html_e( 'Visiteurs', 'statify' ); ?></button>
                <button class="statify-toggle" data-dataset="page_views"><?php esc_html_e( 'Pages vues', 'statify' ); ?></button>
                <button class="statify-toggle" data-dataset="sessions"><?php esc_html_e( 'Sessions', 'statify' ); ?></button>
            </div>
        </div>
        <div class="statify-chart-container">
            <canvas id="statify-visits-chart"></canvas>
        </div>
    </div>

    <!-- Recent Visitors -->
    <div class="statify-card" style="margin-bottom: 24px;">
        <div class="statify-card-header">
            <h2><?php esc_html_e( 'Derniers visiteurs', 'statify' ); ?></h2>
        </div>
        <div class="statify-card-body">
            <table class="statify-table" id="statify-recent-visitors">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Visiteur', 'statify' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'statify' ); ?></th>
                        <th style="text-align:center"><?php esc_html_e( 'Pages vues', 'statify' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'Détails', 'statify' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Grid: Top Pages + Countries -->
    <div class="statify-grid">
        <div class="statify-card">
            <div class="statify-card-header">
                <h2><?php esc_html_e( 'Contenus les plus vus', 'statify' ); ?></h2>
                <a id="statify-all-pages-link" href="<?php echo esc_url( admin_url( 'admin.php?page=statify-top-pages' ) ); ?>" class="statify-see-all-btn">
                    <?php esc_html_e( 'Voir tout', 'statify' ); ?> 
                </a>
            </div>
            <div class="statify-card-body">
                <table class="statify-table" id="statify-top-pages">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Page', 'statify' ); ?></th>
                            <th><?php esc_html_e( 'Vues', 'statify' ); ?></th>
                            <th><?php esc_html_e( 'Visiteurs', 'statify' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="statify-card">
            <div class="statify-card-header">
                <h2><?php esc_html_e( 'Pays', 'statify' ); ?></h2>
                <a id="statify-all-countries-link" href="<?php echo esc_url( admin_url( 'admin.php?page=statify-countries' ) ); ?>" class="statify-see-all-btn">
                    <?php esc_html_e( 'Voir tout', 'statify' ); ?> →
                </a>
            </div>
            <div class="statify-card-body">
                <table class="statify-table" id="statify-countries">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Pays', 'statify' ); ?></th>
                            <th><?php esc_html_e( 'Visites', 'statify' ); ?></th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Grid: Referrers + Devices -->
    <div class="statify-grid">
        <div class="statify-card">
            <div class="statify-card-header">
                <h2><?php esc_html_e( 'Référents', 'statify' ); ?></h2>
            </div>
            <div class="statify-card-body">
                <table class="statify-table" id="statify-referrers">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Source', 'statify' ); ?></th>
                            <th><?php esc_html_e( 'Visites', 'statify' ); ?></th>
                            <th><?php esc_html_e( 'Visiteurs', 'statify' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="statify-card">
            <div class="statify-card-header">
                <h2><?php esc_html_e( 'Appareils & Navigateurs', 'statify' ); ?></h2>
            </div>
            <div class="statify-card-body statify-devices-body">
                <div class="statify-chart-small">
                    <canvas id="statify-devices-chart"></canvas>
                </div>
                <div class="statify-devices-lists">
                    <div>
                        <h4><?php esc_html_e( 'Navigateurs', 'statify' ); ?></h4>
                        <ul id="statify-browsers-list"></ul>
                    </div>
                    <div>
                        <h4><?php esc_html_e( 'Systèmes', 'statify' ); ?></h4>
                        <ul id="statify-os-list"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="statify-footer">
        <p>
            Statify v<?php echo esc_html( STATIFY_VERSION ); ?> 
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify-settings' ) ); ?>">
                <?php esc_html_e( 'Réglages', 'statify' ); ?>
            </a>
        </p>
    </div>
</div>
