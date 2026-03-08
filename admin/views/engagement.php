<?php
/**
 * Engagement — vue complète.
 *
 * @package Statify
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$back_url = admin_url( 'admin.php?page=statify' );
?>
<div class="wrap statify-wrap">

    <!-- Header -->
    <div class="statify-header">
        <h1>
            <img src="<?php echo esc_url( STATIFY_PLUGIN_URL . 'Statify.svg' ); ?>" alt="" style="width:28px;height:28px;vertical-align:middle;margin-right:6px;">
            <?php esc_html_e( 'Engagement', 'statify' ); ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="statify-back-btn" style="font-size:13px;margin-left:16px;">
                ← <?php esc_html_e( 'Retour', 'statify' ); ?>
            </a>
        </h1>
        <div class="statify-header-actions">
            <div class="statify-date-filter">
                <select id="eng-period">
                    <option value="today"  selected><?php esc_html_e( "Aujourd'hui", 'statify' ); ?></option>
                    <option value="7days"><?php esc_html_e( '7 derniers jours', 'statify' ); ?></option>
                    <option value="30days"><?php esc_html_e( '30 derniers jours', 'statify' ); ?></option>
                    <option value="90days"><?php esc_html_e( '90 derniers jours', 'statify' ); ?></option>
                    <option value="year"><?php esc_html_e( 'Cette année', 'statify' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Personnalisé', 'statify' ); ?></option>
                </select>
                <div id="eng-custom-dates" style="display:none;align-items:center;gap:6px;flex-wrap:wrap;">
                    <input type="date" id="eng-from">
                    <span>→</span>
                    <input type="date" id="eng-to">
                    <button id="eng-apply" class="button"><?php esc_html_e( 'Appliquer', 'statify' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="statify-kpis" id="eng-kpis">
        <div class="statify-kpi-card">
            <div class="statify-kpi-value" id="eng-kpi-rate">—</div>
            <div class="statify-kpi-label">Taux d'engagement</div>
        </div>
        <div class="statify-kpi-card">
            <div class="statify-kpi-value" id="eng-kpi-duration">—</div>
            <div class="statify-kpi-label">Durée moy. session</div>
        </div>
        <div class="statify-kpi-card">
            <div class="statify-kpi-value" id="eng-kpi-pages">—</div>
            <div class="statify-kpi-label">Pages / session</div>
        </div>
        <div class="statify-kpi-card">
            <div class="statify-kpi-value" id="eng-kpi-scroll">—</div>
            <div class="statify-kpi-label">Scroll moyen</div>
        </div>
        <div class="statify-kpi-card">
            <div class="statify-kpi-value" id="eng-kpi-deepread">—</div>
            <div class="statify-kpi-label">Lecteurs profonds ≥ 75%</div>
        </div>
    </div>

    <!-- Graphique temporel -->
    <div class="statify-card statify-chart-card" style="margin-bottom:24px;">
        <div class="statify-card-header">
            <h2>Engagement dans le temps</h2>
            <div class="statify-chart-toggles">
                <button class="statify-toggle active" data-eng-dataset="engaged">Sessions engagées</button>
                <button class="statify-toggle" data-eng-dataset="avg_dur">Durée moy.</button>
                <button class="statify-toggle" data-eng-dataset="avg_scroll">Scroll moyen</button>
            </div>
        </div>
        <div class="statify-chart-container">
            <canvas id="eng-chart"></canvas>
        </div>
    </div>

    <!-- 2 colonnes : Scroll distribution + Répartition sessions -->
    <div class="statify-grid" style="margin-bottom:24px;">

        <!-- Funnel scroll -->
        <div class="statify-card">
            <div class="statify-card-header">
                <h2>Profondeur de scroll</h2>
            </div>
            <div class="statify-card-body" id="eng-scroll-dist" style="padding:20px;">
                <!-- rempli par JS -->
                <div class="statify-skeleton" style="height:180px;border-radius:8px;"></div>
            </div>
        </div>

        <!-- Distribution durée sessions -->
        <div class="statify-card">
            <div class="statify-card-header">
                <h2>Durée des sessions</h2>
            </div>
            <div class="statify-card-body" style="padding:20px;">
                <canvas id="eng-duration-chart" style="max-height:200px;"></canvas>
            </div>
        </div>

    </div>

    <!-- Tableau pages avec score d'engagement -->
    <div class="statify-card">
        <div class="statify-card-header">
            <h2>Score d'engagement par page</h2>
            <span class="statify-badge-limit" title="Score calculé sur : durée (40%), scroll (35%), taux d'engagement (25%)">
                ℹ️ Score /100
            </span>
        </div>
        <div class="statify-card-body" style="padding:0;">
            <table class="statify-full-table" id="eng-pages-table">
                <thead>
                    <tr>
                        <th style="width:35%">Page</th>
                        <th style="text-align:center">Score</th>
                        <th style="text-align:right">Sessions</th>
                        <th style="text-align:right">Durée moy.</th>
                        <th style="text-align:right">Scroll moy.</th>
                        <th style="text-align:right">Engagement</th>
                        <th style="text-align:right">Vues</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="statify-no-data">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- .wrap -->

<script>
(function () {
    'use strict';

    function waitForConfig(cb) {
        if (typeof statifyAdmin !== 'undefined') {
            cb();
        } else {
            // statifyAdmin n'est pas encore disponible (script chargé de façon asynchrone)
            var attempts = 0;
            var interval = setInterval(function () {
                attempts++;
                if (typeof statifyAdmin !== 'undefined') {
                    clearInterval(interval);
                    cb();
                } else if (attempts > 20) {
                    clearInterval(interval);
                    console.warn('[Statify] statifyAdmin introuvable — vérifiez que le script admin est bien chargé.');
                }
            }, 100);
        }
    }

    waitForConfig(function () {

    var API   = statifyAdmin.restBase;
    var NONCE = statifyAdmin.nonce;

    var state = { from: dateOff(0), to: dateOff(0) };

    var engChart = null;
    var durChart = null;
    var currentDataset = 'engaged';

    // ── Init ──────────────────────────────────────────────────────────────────
    // Le DOM est déjà chargé quand WordPress inclut cette vue PHP,
    // DOMContentLoaded ne se déclenche donc jamais → on initialise directement.

    function init() {
        bindPeriod();
        loadAll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ── Period selector ───────────────────────────────────────────────────────

    function bindPeriod() {
        var sel  = document.getElementById('eng-period');
        var cDiv = document.getElementById('eng-custom-dates');

        sel.addEventListener('change', function () {
            cDiv.style.display = 'none';
            var today = dateOff(0);
            var map = {
                today:  { from: today,      to: today },
                '7days': { from: dateOff(-7), to: today },
                '30days':{ from: dateOff(-30),to: today },
                '90days':{ from: dateOff(-90),to: today },
                year:   { from: new Date().getFullYear() + '-01-01', to: today },
            };
            if (this.value === 'custom') { cDiv.style.display = 'flex'; return; }
            if (map[this.value]) { state.from = map[this.value].from; state.to = map[this.value].to; }
            loadAll();
        });

        document.getElementById('eng-apply').addEventListener('click', function () {
            var f = document.getElementById('eng-from').value;
            var t = document.getElementById('eng-to').value;
            if (f && t) { state.from = f; state.to = t; loadAll(); }
        });

        // Toggles graphique
        document.querySelectorAll('[data-eng-dataset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-eng-dataset]').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentDataset = this.getAttribute('data-eng-dataset');
                if (engChart) updateEngChartDataset();
            });
        });
    }

    // ── Fetch ─────────────────────────────────────────────────────────────────

    function apiFetch(endpoint, extra, cb) {
        var qs = 'from=' + enc(state.from) + '&to=' + enc(state.to) + '&_t=' + Date.now();
        if (extra) qs += '&' + extra;
        fetch(API + endpoint + '?' + qs, {
            cache: 'no-store',
            headers: { 'X-WP-Nonce': NONCE, 'Cache-Control': 'no-cache, no-store', 'Pragma': 'no-cache' },
        }).then(function (r) { return r.json(); }).then(cb).catch(function (e) { console.error(e); });
    }

    // ── Load ──────────────────────────────────────────────────────────────────

    function loadAll() {
        apiFetch('engagement', null, renderMain);
        apiFetch('engagement/pages', 'limit=50', renderPages);
    }

    // ── Render KPIs + charts principaux ──────────────────────────────────────

    var _chartData = null;

    function renderMain(d) {
        var k = d.kpis || {};
        setText('eng-kpi-rate',     (k.engagement_rate || 0) + '%');
        setText('eng-kpi-duration', fmtDur(k.avg_duration || 0));
        setText('eng-kpi-pages',    parseFloat(k.avg_pages || 0).toFixed(1));
        setText('eng-kpi-scroll',   parseFloat(k.avg_scroll_depth || 0).toFixed(0) + '%');
        setText('eng-kpi-deepread', (k.deep_read_rate || 0) + '%');

        _chartData = d.chart || [];
        renderEngChart(_chartData);
        renderScrollDist(d.scroll_distribution || {});
        renderDurationChart(d.chart || []);
    }

    // ── Graphique engagement temporel ─────────────────────────────────────────

    function renderEngChart(data) {
        var ctx = document.getElementById('eng-chart');
        if (!ctx) return;
        if (engChart) { engChart.destroy(); }

        var labels = data.map(function (d) { return d.label; });
        var colors = { engaged: '#6c63ff', avg_dur: '#10b981', avg_scroll: '#f59e0b' };
        var titles = { engaged: 'Sessions engagées', avg_dur: 'Durée moy. (s)', avg_scroll: 'Scroll moyen (%)' };

        var datasets = {
            engaged:    data.map(function (d) { return d.future ? null : d.engaged; }),
            avg_dur:    data.map(function (d) { return d.future ? null : d.avg_dur; }),
            avg_scroll: data.map(function (d) { return d.future ? null : d.avg_scroll; }),
        };

        engChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: Object.keys(datasets).map(function (key) {
                    return {
                        label:           titles[key],
                        data:            datasets[key],
                        borderColor:     colors[key],
                        backgroundColor: colors[key] + '18',
                        fill:            true,
                        tension:         0.4,
                        borderWidth:     2.5,
                        hidden:          key !== currentDataset,
                        spanGaps:        false,
                        pointRadius:     0,
                        pointHoverRadius:5,
                    };
                }),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } },
                    y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(0,0,0,0.04)' },
                         ticks: { font: { size: 11 } } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#1d2327', cornerRadius: 8, padding: 10,
                        callbacks: {
                            label: function (ctx) {
                                if (ctx.parsed.y === null) return null;
                                if (currentDataset === 'avg_dur') return ctx.dataset.label + ': ' + fmtDur(ctx.parsed.y);
                                if (currentDataset === 'avg_scroll') return ctx.dataset.label + ': ' + ctx.parsed.y + '%';
                                return ctx.dataset.label + ': ' + ctx.parsed.y;
                            }
                        }
                    },
                },
            },
        });
    }

    function updateEngChartDataset() {
        if (!engChart) return;
        engChart.data.datasets.forEach(function (ds) {
            ds.hidden = (ds.label !== { engaged: 'Sessions engagées', avg_dur: 'Durée moy. (s)', avg_scroll: 'Scroll moyen (%)' }[currentDataset]);
        });
        engChart.update();
    }

    // ── Funnel scroll ─────────────────────────────────────────────────────────

    function renderScrollDist(dist) {
        var container = document.getElementById('eng-scroll-dist');
        if (!container) return;

        var thresholds = [25, 50, 75, 100];
        var colors     = ['#6c63ff', '#10b981', '#f59e0b', '#ef4444'];
        var labels     = ['25%', '50%', '75%', '100%'];
        var max        = Math.max.apply(null, thresholds.map(function (t) { return dist[t] || 0; })) || 1;

        var html = '<div style="display:flex;flex-direction:column;gap:14px;">';
        thresholds.forEach(function (t, i) {
            var val = dist[t] || 0;
            var pct = Math.round((val / max) * 100);
            html += '<div>'
                  + '<div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:13px;">'
                  + '<span style="font-weight:600;color:#1d2327;">Scroll ' + labels[i] + '</span>'
                  + '<span style="color:#646970;font-weight:500;">' + val.toLocaleString('fr-FR') + ' sessions</span>'
                  + '</div>'
                  + '<div style="background:#f0f2f5;border-radius:6px;height:10px;overflow:hidden;">'
                  + '<div style="height:100%;width:' + pct + '%;background:' + colors[i] + ';border-radius:6px;transition:width .5s ease;"></div>'
                  + '</div>'
                  + '</div>';
        });
        html += '</div>';

        // Légende explication
        html += '<p style="margin-top:18px;font-size:12px;color:#9ca3af;line-height:1.5;">'
              + '⬇ Lecture : chaque barre = nombre de sessions ayant atteint ce seuil de scroll sur la période.'
              + '</p>';

        container.innerHTML = html;
    }

    // ── Graphique durée sessions (barres) ─────────────────────────────────────

    function renderDurationChart(data) {
        var ctx = document.getElementById('eng-duration-chart');
        if (!ctx) return;
        if (durChart) { durChart.destroy(); }

        // Buckets durée : <15s, 15-30s, 30-60s, 1-3m, 3-10m, >10m
        // On utilise avg_dur comme proxy sur le graphique temporel
        var labels = data.map(function (d) { return d.label; });
        var values = data.map(function (d) { return d.future ? null : (d.avg_dur || 0); });

        durChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Durée moy. (s)',
                    data: values,
                    backgroundColor: 'rgba(16,185,129,0.6)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return 'Durée moy. : ' + fmtDur(ctx.parsed.y); }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                    y: { beginAtZero: true, border: { display: false },
                         ticks: { font: { size: 11 }, callback: function (v) { return fmtDur(v); } } },
                },
            },
        });
    }

    // ── Tableau pages engagement ───────────────────────────────────────────────

    function renderPages(data) {
        var tbody = document.querySelector('#eng-pages-table tbody');
        if (!tbody) return;

        if (!data || !data.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="statify-no-data">Aucune donnée pour cette période</td></tr>';
            return;
        }

        tbody.innerHTML = data.map(function (p, idx) {
            var score   = parseFloat(p.engagement_score || 0);
            var scoreColor = score >= 70 ? '#10b981' : score >= 40 ? '#f59e0b' : '#ef4444';
            var scoreBg    = score >= 70 ? 'rgba(16,185,129,0.1)' : score >= 40 ? 'rgba(245,158,11,0.1)' : 'rgba(239,68,68,0.1)';
            var title      = p.page_title || p.page_url || '—';
            var engRate    = p.total_sessions > 0
                ? Math.round((p.engaged_sessions / p.total_sessions) * 100) + '%'
                : '—';
            var scrollDisp = p.avg_scroll ? parseFloat(p.avg_scroll).toFixed(0) + '%' : '—';
            var medal = idx === 0 ? '🥇 ' : idx === 1 ? '🥈 ' : idx === 2 ? '🥉 ' : '';

            return '<tr>'
                + '<td>'
                +   '<div style="font-size:13px;font-weight:600;color:#1d2327;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px;" title="' + esc(p.page_url) + '">'
                +   medal + esc(title)
                +   '</div>'
                +   '<div style="font-size:11px;color:#9ca3af;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px;">' + esc(p.page_url) + '</div>'
                + '</td>'
                + '<td style="text-align:center;">'
                +   '<span style="display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;background:' + scoreBg + ';color:' + scoreColor + ';font-weight:800;font-size:14px;">'
                +   score.toFixed(0)
                +   '</span>'
                +   '<div style="height:4px;background:#f0f2f5;border-radius:2px;margin-top:6px;width:60px;margin-left:auto;margin-right:auto;">'
                +   '<div style="height:100%;width:' + score + '%;background:' + scoreColor + ';border-radius:2px;"></div>'
                +   '</div>'
                + '</td>'
                + '<td style="text-align:right;">' + fmt(p.total_sessions) + '</td>'
                + '<td style="text-align:right;">' + fmtDur(p.avg_duration || 0) + '</td>'
                + '<td style="text-align:right;">'
                +   '<div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">'
                +   scrollDisp
                +   (p.avg_scroll ? '<div style="width:40px;height:5px;background:#f0f2f5;border-radius:2px;overflow:hidden;"><div style="width:' + parseFloat(p.avg_scroll) + '%;height:100%;background:#6c63ff;border-radius:2px;"></div></div>' : '')
                +   '</div>'
                + '</td>'
                + '<td style="text-align:right;">'
                +   '<span style="font-weight:600;color:' + (parseInt(engRate) >= 50 ? '#10b981' : '#f59e0b') + ';">' + engRate + '</span>'
                + '</td>'
                + '<td style="text-align:right;color:#646970;">' + fmt(p.page_views) + '</td>'
                + '</tr>';
        }).join('');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setText(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }
    function fmt(n) { return (parseInt(n, 10) || 0).toLocaleString('fr-FR'); }
    function fmtDur(s) {
        s = Math.round(+s || 0);
        if (s <= 0) return '0s';
        var m = Math.floor(s / 60); return m > 0 ? m + 'm ' + (s % 60) + 's' : s + 's';
    }
    function dateOff(d) {
        var dt = new Date(); dt.setDate(dt.getDate() + d);
        return dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
    }
    function enc(s) { return encodeURIComponent(s || ''); }
    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }

    }); // fin waitForConfig

})();
</script>
