/**
 * Advanced Stats — Admin JS
 * Zéro cache : cache:'no-store' + _t=timestamp sur CHAQUE requête fetch.
 */
(function () {
    'use strict';

    if (typeof statifyAdmin === 'undefined') return;

    var API_BASE = statifyAdmin.restBase;
    var NONCE    = statifyAdmin.nonce;

    var state = {
        from: dateOffset(0),
        to:   dateOffset(0),
        device:   '',
        postType: '',
        country:  '',
    };

    // ── Init ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        loadAllData();

        // Auto-refresh toutes les 60 s si on regarde aujourd'hui
        setInterval(function () {
            if (state.to === dateOffset(0)) loadAllData();
        }, 60000);
    });

    // Exposé globalement pour le bouton Actualiser
    window.loadAllData = loadAllData;

    // ── Events ────────────────────────────────────────────────────────────────

    function bindEvents() {
        var periodSel = document.getElementById('statify-period');
        if (periodSel) {
            periodSel.addEventListener('change', function () {
                var v = this.value;
                var customDiv = document.getElementById('statify-custom-dates');
                if (v === 'custom') { if (customDiv) customDiv.style.display = 'flex'; return; }
                if (customDiv) customDiv.style.display = 'none';
                var today = dateOffset(0);
                var map = {
                    today:  { from: today,                 to: today },
                    '7days':  { from: dateOffset(-7),        to: today },
                    '30days': { from: dateOffset(-30),       to: today },
                    '90days': { from: dateOffset(-90),       to: today },
                    year:   { from: new Date().getFullYear() + '-01-01', to: today },
                };
                if (map[v]) { state.from = map[v].from; state.to = map[v].to; }
                loadAllData();
            });
        }

        var applyBtn = document.getElementById('statify-apply-dates');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var f = document.getElementById('statify-from').value;
                var t = document.getElementById('statify-to').value;
                if (f && t) { state.from = f; state.to = t; loadAllData(); }
            });
        }

        var devSel = document.getElementById('statify-device-filter');
        if (devSel) {
            devSel.addEventListener('change', function () {
                state.device = this.value; loadAllData();
            });
        }

        document.querySelectorAll('.statify-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.statify-toggle').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                if (window.StatifyCharts) StatifyCharts.toggleDataset(this.getAttribute('data-dataset'));
            });
        });
    }

    // ── Fetch central — ZÉRO CACHE ────────────────────────────────────────────

    /**
     * Chaque appel :
     *  - cache:'no-store'  → le navigateur ne lit JAMAIS son cache
     *  - _t=timestamp      → URL unique → LiteSpeed/Varnish/CDN ne peuvent pas servir de réponse cachée
     *  - headers explicites → force tous les proxies intermédiaires
     */
    function apiFetch(endpoint, params, callback) {
        var qs = 'from='  + enc(state.from)
               + '&to='   + enc(state.to)
               + '&_t='   + Date.now();           // timestamp unique anti-cache

        if (state.device)   qs += '&device='    + enc(state.device);
        if (state.postType) qs += '&post_type='  + enc(state.postType);
        if (state.country)  qs += '&country='    + enc(state.country);
        if (params)         qs += '&' + params;

        fetch(API_BASE + endpoint + '?' + qs, {
            method:  'GET',
            cache:   'no-store',          // instruction navigateur : jamais de cache
            headers: {
                'X-WP-Nonce':     NONCE,
                'Cache-Control':  'no-cache, no-store, must-revalidate',
                'Pragma':         'no-cache',
            },
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(callback)
        .catch(function (e) { console.error('[Statify] ' + endpoint, e); });
    }

    // ── Loaders ───────────────────────────────────────────────────────────────

    function loadAllData() {
        loadOverview();
        loadChart();
        loadRecentVisitors();
        loadTopPages();
        loadReferrers();
        loadCountries();
        loadDevices();
    }

    function loadOverview() {
        apiFetch('overview', null, function (d) {
            setText('kpi-visitors',  fmt(d.unique_visitors));
            setText('kpi-pageviews', fmt(d.page_views));
            setText('kpi-sessions',  fmt(d.sessions));
            setText('kpi-duration',  fmtDuration(d.avg_duration));
            setText('kpi-bounce',    d.engagement_rate + '%');
            setChange('kpi-visitors-change',  d.change_visitors);
            setChange('kpi-pageviews-change', d.change_views);
        });
    }

    function loadChart() {
        apiFetch('chart/visits', null, function (d) {
            if (window.StatifyCharts) StatifyCharts.renderVisitsChart(d);
        });
    }

    function loadRecentVisitors() {
        apiFetch('recent-visitors', 'limit=5', function (data) {
            var tbody = document.querySelector('#statify-recent-visitors tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="statify-no-data">' + statifyAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var now = new Date();
            tbody.innerHTML = data.map(function (s) {
                var flag   = flag2(s.country_code);
                var device = s.device_type === 'mobile' ? '📱' : (s.device_type === 'tablet' ? '💊' : '💻');
                var vid    = s.visitor_hash.substring(0, 8);
                var ended  = new Date(s.ended_at + 'Z');
                var sec    = Math.floor((now - ended) / 1000);
                var time   = sec < 60 ? 'En ce moment' : 'Il y a ' + Math.floor(sec / 60) + ' min';
                return '<tr>'
                    + '<td><strong>Visiteur ' + vid + '</strong><br><small>' + flag + ' ' + device + '</small></td>'
                    + '<td><span class="statify-time-badge">' + time + '</span><br><small>Durée : ' + fmtDuration(s.engagement_time > 0 ? s.engagement_time : s.duration) + '</small></td>'
                    + '<td style="text-align:center"><span class="statify-badge">' + parseInt(s.page_count, 10) + '</span></td>'
                    + '<td style="text-align:right"><a href="?page=statify-visitor&session_id=' + enc(s.session_id) + '" class="button button-small">Voir parcours</a></td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadTopPages() {
        var link = document.getElementById('statify-all-pages-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=statify-top-pages&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('top-pages', 'limit=8', function (data) {
            var tbody = document.querySelector('#statify-top-pages tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="statify-no-data">' + statifyAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var max = Math.max.apply(null, data.map(function (d) { return +d.views; }));
            tbody.innerHTML = data.map(function (p) {
                var pct   = Math.round((+p.views / max) * 100);
                var title = p.page_title || p.page_url;
                return '<tr>'
                    + '<td title="' + esc(p.page_url) + '">'
                    +   '<div class="statify-bar"><span>' + esc(title) + '</span></div>'
                    +   '<div class="statify-bar-track"><div class="statify-bar-fill" style="width:' + pct + '%"></div></div>'
                    + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.views) + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.unique_visitors) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadReferrers() {
        apiFetch('top-referrers', 'limit=8', function (data) {
            var tbody = document.querySelector('#statify-referrers tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="statify-no-data">' + statifyAdmin.i18n.noData + '</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function (r) {
                return '<tr>'
                    + '<td>' + esc(r.referrer_domain) + '</td>'
                    + '<td style="text-align:right">' + fmt(+r.hits) + '</td>'
                    + '<td style="text-align:right">' + fmt(+r.unique_visitors) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadCountries() {
        var link = document.getElementById('statify-all-countries-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=statify-countries&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('countries', 'limit=8', function (data) {
            var tbody = document.querySelector('#statify-countries tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="statify-no-data">' + statifyAdmin.i18n.noData + '</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function (c) {
                return '<tr>'
                    + '<td>' + flag2(c.country_code) + ' ' + esc(c.country_code) + '</td>'
                    + '<td style="text-align:right">' + fmt(+c.hits) + '</td>'
                    + '<td style="text-align:right">' + parseFloat(c.percentage).toFixed(1) + '%</td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadDevices() {
        apiFetch('devices', null, function (data) {
            if (data.devices && window.StatifyCharts) StatifyCharts.renderDevicesChart(data.devices);

            var bl = document.getElementById('statify-browsers-list');
            if (bl && data.browsers) {
                bl.innerHTML = data.browsers.map(function (b) {
                    return '<li><span>' + esc(b.browser) + '</span><span>' + fmt(+b.count) + '</span></li>';
                }).join('') || '<li>—</li>';
            }

            var ol = document.getElementById('statify-os-list');
            if (ol && data.os) {
                ol.innerHTML = data.os.map(function (o) {
                    return '<li><span>' + esc(o.os) + '</span><span>' + fmt(+o.count) + '</span></li>';
                }).join('') || '<li>—</li>';
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setText(id, v) {
        var el = document.getElementById(id);
        if (el) el.textContent = v;
    }

    function setChange(id, v) {
        var el = document.getElementById(id);
        if (!el) return;
        if (!v) { el.textContent = ''; el.className = 'statify-kpi-change'; return; }
        el.textContent = (v > 0 ? '+' : '') + v + '%';
        el.className   = 'statify-kpi-change ' + (v >= 0 ? 'positive' : 'negative');
    }

    function fmt(n) {
        return (typeof n === 'number' ? n : parseInt(n, 10) || 0).toLocaleString('fr-FR');
    }

    function fmtDuration(s) {
        if (!s || s <= 0) return '0s';
        s = Math.round(s);
        var m = Math.floor(s / 60);
        return m > 0 ? m + 'm ' + (s % 60) + 's' : s + 's';
    }

    function dateOffset(days) {
        var d = new Date();
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    }

    function enc(s) { return encodeURIComponent(s || ''); }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function flag2(code) {
        if (!code || code.length !== 2) return '🌐';
        return String.fromCodePoint(0x1F1E6 + code.charCodeAt(0) - 65)
             + String.fromCodePoint(0x1F1E6 + code.charCodeAt(1) - 65);
    }

})();
