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
            <button id="aa-add-campaign-btn" class="aa-btn-campaign" title="<?php esc_attr_e( 'Ajouter un événement', 'always-analytics' ); ?>">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.5 1v11M1 6.5h11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <?php esc_html_e( 'Événement', 'always-analytics' ); ?>
            </button>
        </div>
        <div class="aa-chart-container">
            <canvas id="aa-visits-chart"></canvas>
        </div>
    </div>

    <!-- Modal Campagne -->
    <div id="aa-campaign-modal" class="aa-modal" aria-modal="true" role="dialog">
        <div class="aa-modal-overlay"></div>
        <div class="aa-modal-box">
            <div class="aa-modal-header">
                <h3><?php esc_html_e( 'Ajouter un événement', 'always-analytics' ); ?></h3>
                <button class="aa-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'always-analytics' ); ?>">✕</button>
            </div>
            <div class="aa-modal-body">
                <div class="aa-field">
                    <label for="aa-camp-date"><?php esc_html_e( 'Date', 'always-analytics' ); ?> <span class="aa-req">*</span></label>
                    <input type="date" id="aa-camp-date" />
                    <small><?php esc_html_e( 'Maximum 1 événement par jour.', 'always-analytics' ); ?></small>
                </div>
                <div class="aa-field">
                    <label for="aa-camp-label"><?php esc_html_e( 'Label', 'always-analytics' ); ?> <span class="aa-req">*</span></label>
                    <input type="text" id="aa-camp-label" placeholder="<?php esc_attr_e( 'Ex : Campagne backlinks, Refonte header…', 'always-analytics' ); ?>" maxlength="100" />
                </div>
                <div class="aa-field">
                    <label for="aa-camp-desc"><?php esc_html_e( 'Description (optionnel)', 'always-analytics' ); ?></label>
                    <textarea id="aa-camp-desc" rows="2" placeholder="<?php esc_attr_e( 'Détails, URL, notes…', 'always-analytics' ); ?>"></textarea>
                </div>
                <div class="aa-field aa-field--color">
                    <label><?php esc_html_e( 'Couleur', 'always-analytics' ); ?></label>
                    <div class="aa-color-swatches">
                        <span class="aa-swatch aa-swatch--active" data-color="#6c63ff" style="background:#6c63ff;" title="Violet"></span>
                        <span class="aa-swatch" data-color="#10b981" style="background:#10b981;" title="Vert"></span>
                        <span class="aa-swatch" data-color="#f59e0b" style="background:#f59e0b;" title="Orange"></span>
                        <span class="aa-swatch" data-color="#ef4444" style="background:#ef4444;" title="Rouge"></span>
                        <span class="aa-swatch" data-color="#3b82f6" style="background:#3b82f6;" title="Bleu"></span>
                        <span class="aa-swatch" data-color="#ec4899" style="background:#ec4899;" title="Rose"></span>
                        <input type="color" id="aa-camp-color" value="#6c63ff" class="aa-color-custom" title="<?php esc_attr_e( 'Couleur personnalisée', 'always-analytics' ); ?>" />
                    </div>
                </div>
                <div id="aa-camp-error" class="aa-camp-error" style="display:none;"></div>
            </div>
            <div class="aa-modal-footer">
                <button id="aa-camp-save" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'always-analytics' ); ?></button>
                <button class="button aa-modal-cancel"><?php esc_html_e( 'Annuler', 'always-analytics' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Grid: Derniers visiteurs + Événements -->
    <div class="aa-grid aa-grid--visitors-campaigns">

        <!-- Recent Visitors -->
        <div class="aa-card">
            <div class="aa-card-header">
                <h2><?php esc_html_e( 'Derniers visiteurs', 'always-analytics' ); ?></h2>
            </div>
            <div class="aa-card-body">
                <table class="aa-table aa-table--compact" id="aa-recent-visitors">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Visiteur', 'always-analytics' ); ?></th>
                            <th style="text-align:right"><?php esc_html_e( 'Action', 'always-analytics' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Événements campagne -->
        <div class="aa-card" id="aa-campaigns-card">
            <div class="aa-card-header">
                <h2><?php esc_html_e( 'Événements', 'always-analytics' ); ?></h2>
                <button id="aa-add-campaign-btn2" class="aa-btn-campaign aa-btn-campaign--sm" title="<?php esc_attr_e( 'Ajouter un événement', 'always-analytics' ); ?>">
                    <svg width="11" height="11" viewBox="0 0 13 13" fill="none"><path d="M6.5 1v11M1 6.5h11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <?php esc_html_e( 'Ajouter', 'always-analytics' ); ?>
                </button>
            </div>
            <div class="aa-card-body">
                <div id="aa-campaigns-list">
                    <p class="aa-no-data" style="display:none;"><?php esc_html_e( 'Aucun événement enregistré.', 'always-analytics' ); ?></p>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal édition événement -->
    <div id="aa-campaign-edit-modal" class="aa-modal" aria-modal="true" role="dialog">
        <div class="aa-modal-overlay"></div>
        <div class="aa-modal-box">
            <div class="aa-modal-header">
                <h3><?php esc_html_e( 'Modifier l\'événement', 'always-analytics' ); ?></h3>
                <button class="aa-modal-close" aria-label="Fermer">✕</button>
            </div>
            <div class="aa-modal-body">
                <input type="hidden" id="aa-edit-camp-id" value="" />
                <div class="aa-field">
                    <label for="aa-edit-camp-date"><?php esc_html_e( 'Date', 'always-analytics' ); ?> <span class="aa-req">*</span></label>
                    <input type="date" id="aa-edit-camp-date" />
                </div>
                <div class="aa-field">
                    <label for="aa-edit-camp-label"><?php esc_html_e( 'Label', 'always-analytics' ); ?> <span class="aa-req">*</span></label>
                    <input type="text" id="aa-edit-camp-label" maxlength="100" />
                </div>
                <div class="aa-field">
                    <label for="aa-edit-camp-desc"><?php esc_html_e( 'Description', 'always-analytics' ); ?></label>
                    <textarea id="aa-edit-camp-desc" rows="2"></textarea>
                </div>
                <div class="aa-field aa-field--color">
                    <label><?php esc_html_e( 'Couleur', 'always-analytics' ); ?></label>
                    <div class="aa-color-swatches" id="aa-edit-swatches">
                        <span class="aa-swatch" data-color="#6c63ff" style="background:#6c63ff;"></span>
                        <span class="aa-swatch" data-color="#10b981" style="background:#10b981;"></span>
                        <span class="aa-swatch" data-color="#f59e0b" style="background:#f59e0b;"></span>
                        <span class="aa-swatch" data-color="#ef4444" style="background:#ef4444;"></span>
                        <span class="aa-swatch" data-color="#3b82f6" style="background:#3b82f6;"></span>
                        <span class="aa-swatch" data-color="#ec4899" style="background:#ec4899;"></span>
                        <input type="color" id="aa-edit-camp-color" value="#6c63ff" class="aa-color-custom" />
                    </div>
                </div>
                <div id="aa-edit-camp-error" class="aa-camp-error" style="display:none;"></div>
            </div>
            <div class="aa-modal-footer">
                <button id="aa-edit-camp-save" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'always-analytics' ); ?></button>
                <button class="button aa-modal-cancel"><?php esc_html_e( 'Annuler', 'always-analytics' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Card Sources de Tracking -->
    <div class="aa-card aa-sources-card" id="aa-sources-card">
        <div class="aa-card-header">
            <h2><?php esc_html_e( 'Sources de tracking', 'always-analytics' ); ?></h2>
            <span class="aa-sources-badge" id="aa-sources-total-badge"></span>
        </div>
        <div class="aa-card-body aa-sources-body">

            <!-- Barre de répartition globale -->
            <div class="aa-sources-bar-wrap">
                <div class="aa-sources-bar" id="aa-sources-bar" title="<?php esc_attr_e( 'Répartition des hits par source', 'always-analytics' ); ?>"></div>
                <div class="aa-sources-bar-legend" id="aa-sources-bar-legend"></div>
            </div>

            <!-- Tableau détaillé par source -->
            <table class="aa-table aa-sources-table" id="aa-sources-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Source', 'always-analytics' ); ?></th>
                        <th class="aa-num"><?php esc_html_e( 'Hits', 'always-analytics' ); ?></th>
                        <th class="aa-num"><?php esc_html_e( 'Visiteurs uniques', 'always-analytics' ); ?></th>
                        <th class="aa-num"><?php esc_html_e( 'Sessions', 'always-analytics' ); ?></th>
                        <th class="aa-num"><?php esc_html_e( 'Nouveaux visiteurs', 'always-analytics' ); ?></th>
                        <th class="aa-num"><?php esc_html_e( '% du total', 'always-analytics' ); ?></th>
                        <th class="aa-num"><?php esc_html_e( 'Fusionnés (pre_consent)', 'always-analytics' ); ?></th>
                        <th><?php esc_html_e( 'Tendance', 'always-analytics' ); ?></th>
                    </tr>
                </thead>
                <tbody id="aa-sources-tbody"></tbody>
            </table>

            <!-- Bloc informatif -->
            <div class="aa-sources-info" id="aa-sources-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="aa-sources-info-text"></span>
            </div>

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
                <h2><?php esc_html_e( 'Appareils', 'always-analytics' ); ?></h2>
                <div class="aa-dev-tabs" role="tablist">
                    <span class="aa-dev-tab aa-dev-tab--active" data-device="all"     role="tab" tabindex="0"><?php esc_html_e( 'Tous', 'always-analytics' ); ?></span>
                    <span class="aa-dev-tab" data-device="desktop" role="tab" tabindex="0">
                        <svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>
                        <?php esc_html_e( 'Desktop', 'always-analytics' ); ?>
                    </span>
                    <span class="aa-dev-tab" data-device="mobile"  role="tab" tabindex="0">
                        <svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>
                        <?php esc_html_e( 'Mobile', 'always-analytics' ); ?>
                    </span>
                    <span class="aa-dev-tab" data-device="tablet"  role="tab" tabindex="0">
                        <svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>
                        <?php esc_html_e( 'Tablette', 'always-analytics' ); ?>
                    </span>
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
