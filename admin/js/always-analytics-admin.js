/**
 * Advanced Stats — Admin JS
 * Zéro cache : cache:'no-store' + _t=timestamp sur CHAQUE requête fetch.
 */
(function () {
    'use strict';

    if (typeof alwaysAnalyticsAdmin === 'undefined') return;

    var API_BASE = alwaysAnalyticsAdmin.restBase;
    var NONCE    = alwaysAnalyticsAdmin.nonce;

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
        var periodSel = document.getElementById('aa-period');
        if (periodSel) {
            periodSel.addEventListener('change', function () {
                var v = this.value;
                var customDiv = document.getElementById('aa-custom-dates');
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

        var applyBtn = document.getElementById('aa-apply-dates');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var f = document.getElementById('aa-from').value;
                var t = document.getElementById('aa-to').value;
                if (f && t) { state.from = f; state.to = t; loadAllData(); }
            });
        }

        var devSel = document.getElementById('aa-device-filter');
        if (devSel) {
            devSel.addEventListener('change', function () {
                state.device = this.value; loadAllData();
            });
        }

        document.querySelectorAll('.aa-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.aa-toggle').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.toggleDataset(this.getAttribute('data-dataset'));
            });
        });

        // Onglets référents
        document.addEventListener('click', function (e) {
            var tab = e.target.closest('.aa-ref-tab');
            if (!tab) return;
            document.querySelectorAll('.aa-ref-tab').forEach(function (t) {
                t.classList.remove('aa-ref-tab--active');
            });
            tab.classList.add('aa-ref-tab--active');
            _refCat = tab.getAttribute('data-cat');
            renderReferrers();
        });

        // Onglets appareils
        document.addEventListener('click', function (e) {
            var tab = e.target.closest('.aa-dev-tab');
            if (!tab) return;
            document.querySelectorAll('.aa-dev-tab').forEach(function (t) {
                t.classList.remove('aa-dev-tab--active');
            });
            tab.classList.add('aa-dev-tab--active');
            _devFilter = tab.getAttribute('data-device');
            renderDevices();
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
        .catch(function (e) { console.error('[Always Analytics] ' + endpoint, e); });
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
            if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.renderVisitsChart(d);
        });
    }

    function loadRecentVisitors() {
        apiFetch('recent-visitors', 'limit=5', function (data) {
            var tbody = document.querySelector('#aa-recent-visitors tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
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
                var pages  = parseInt(s.total_pages, 10) || parseInt(s.last_page_count, 10) || 0;
                var dur    = parseInt(s.total_duration, 10) || 0;
                var sessions_label = parseInt(s.session_count, 10) > 1 ? ' <small style="color:#64748b">(' + s.session_count + ' visites)</small>' : '';
                return '<tr>'
                    + '<td><strong>Visiteur ' + vid + '</strong>' + sessions_label + '<br><small>' + flag + ' ' + device + '</small></td>'
                    + '<td><span class="aa-time-badge">' + time + '</span><br><small>Durée : ' + fmtDuration(dur) + '</small></td>'
                    + '<td style="text-align:center"><span class="aa-badge">' + pages + '</span></td>'
                    + '<td style="text-align:right"><a href="?page=always-analytics-visitor&visitor_hash=' + enc(s.visitor_hash) + '" class="button button-small">Voir parcours</a></td>'
                    + '</tr>';
            }).join('');
        });
    }

    function loadTopPages() {
        var link = document.getElementById('aa-all-pages-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=always-analytics-top-pages&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('top-pages', 'limit=8', function (data) {
            var tbody = document.querySelector('#aa-top-pages tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var max = Math.max.apply(null, data.map(function (d) { return +d.views; }));
            tbody.innerHTML = data.map(function (p) {
                var pct   = Math.round((+p.views / max) * 100);
                var title = p.page_title || p.page_url;
                return '<tr>'
                    + '<td title="' + esc(p.page_url) + '">'
                    +   '<div class="aa-bar"><span>' + esc(title) + '</span></div>'
                    +   '<div class="aa-bar-track"><div class="aa-bar-fill" style="width:' + pct + '%"></div></div>'
                    + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.views) + '</td>'
                    + '<td style="text-align:right">' + fmt(+p.unique_visitors) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    // ── Référents — catégorisation & icônes ──────────────────────────────────

    var REF_DB = (function () {
        // Chargement depuis data/referrer-sources.php via wp_localize_script
        // Le champ 'emoji' a été supprimé — on utilise le favicon uniquement,
        // avec un fallback universel unique en cas d'erreur de chargement.
        var rawSources = (alwaysAnalyticsAdmin.referrerSources || []);

        var rules = rawSources.map(function (s) {
            return {
                re:    new RegExp(s.pattern, 'i'),
                cat:   s.cat,
                label: s.label,
                color: s.color,
            };
        });

        return {
            categorize: function (domain) {
                if (!domain) return { cat: 'direct', label: 'Direct', color: '#64748B' };
                var d = domain.toLowerCase();
                for (var i = 0; i < rules.length; i++) {
                    if (rules[i].re.test(d)) {
                        return { cat: rules[i].cat, label: rules[i].label, color: rules[i].color };
                    }
                }
                return { cat: 'site', label: domain, color: '#64748B' };
            }
        };
    })();

    var _refData = [];
    var _refCat  = 'all';

    function renderReferrers() {
        var container = document.getElementById('aa-referrers-list');
        if (!container) return;

        var rows = _refData.filter(function (r) {
            if (_refCat === 'all') return true;
            var info = REF_DB.categorize(r.referrer_domain);
            return info.cat === _refCat;
        });

        if (!rows.length) {
            container.innerHTML = '<div class="aa-ref-empty">Aucune source dans cette catégorie.</div>';
            return;
        }

        var maxHits = Math.max.apply(null, rows.map(function (r) { return +r.hits || 0; })) || 1;

        var rendered = rows.map(function (r) {
            var info   = REF_DB.categorize(r.referrer_domain);
            var pct    = Math.round(((+r.hits || 0) / maxHits) * 100);
            var label  = esc(info.label);
            var domain = esc(r.referrer_domain || '—');
            var hits   = fmt(+r.hits || 0);
            var uniq   = fmt(+r.unique_visitors || 0);

            var rawDomain = (r.referrer_domain || '').toLowerCase();
            var rawLabel  = info.label.toLowerCase();
            var showDomain = info.cat !== 'site'
                && rawDomain !== rawLabel
                && rawLabel.indexOf(rawDomain.replace(/^www\./, '')) === -1;

            // Favicon Google S2 — asynchrone, non bloquant.
            // En cas d'erreur, affiche un carré coloré avec l'initiale (fallback universel).
            var faviconDomain = encodeURIComponent(r.referrer_domain || info.label);
            var faviconUrl    = 'https://www.google.com/s2/favicons?domain=' + faviconDomain + '&sz=32';
            var initial       = (info.label || '?').charAt(0).toUpperCase();
            var fallbackStyle = 'display:none;width:18px;height:18px;border-radius:3px;'
                              + 'background:' + info.color + ';color:#fff;'
                              + 'font-size:11px;font-weight:700;line-height:18px;'
                              + 'text-align:center;flex-shrink:0;';

            var html = '<div class="aa-ref-row">'
                + '<div class="aa-ref-identity">'
                +   '<span class="aa-ref-icon" style="background:' + info.color + '18;">'
                +     '<img class="aa-ref-favicon" src="' + faviconUrl + '" width="18" height="18" alt="" loading="lazy" decoding="async"'
                +         ' onerror="this.style.display=\'none\';this.nextSibling.style.display=\'flex\'">'
                +     '<span class="aa-ref-fallback" style="' + fallbackStyle + '">' + initial + '</span>'
                +   '</span>'
                +   '<span class="aa-ref-labels">'
                +     '<span class="aa-ref-name">' + label + '</span>'
                +     (showDomain ? '<span class="aa-ref-domain">' + domain + '</span>' : '')
                +   '</span>'
                + '</div>'
                + '<div class="aa-ref-bar-wrap">'
                +   '<div class="aa-ref-bar-track"><div class="aa-ref-bar-fill" style="width:' + pct + '%;background:' + info.color + '"></div></div>'
                + '</div>'
                + '<div class="aa-ref-stats">'
                +   '<span class="aa-ref-hits">' + hits + ' sess.</span>'
                +   '<span class="aa-ref-uniq">' + uniq + ' uniq.</span>'
                + '</div>'
                + '</div>';

            return html;
        });

        container.innerHTML = rendered.join('');
    }

    function loadReferrers() {
        apiFetch('top-referrers', 'limit=40', function (data) {
            _refData = data || [];

            // Compteurs par catégorie
            var counts = { all: 0, search: 0, social: 0, ai: 0, site: 0 };
            _refData.forEach(function (r) {
                counts.all++;
                var cat = REF_DB.categorize(r.referrer_domain).cat;
                if (cat in counts) counts[cat]++;
                else counts.site++;
            });

            // Mise à jour des badges
            document.querySelectorAll('.aa-ref-tab').forEach(function (tab) {
                var cat = tab.getAttribute('data-cat');
                var badge = tab.querySelector('.aa-ref-count');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'aa-ref-count';
                    tab.appendChild(badge);
                }
                var n = counts[cat] || 0;
                badge.textContent = n;
                // Masquer le badge "0" sauf pour "Tous"
                badge.style.display = (n === 0 && cat !== 'all') ? 'none' : '';
            });

            renderReferrers();
        });
    }

    function loadCountries() {
        var link = document.getElementById('aa-all-countries-link');
        if (link) {
            var base = link.href.split('?')[0];
            link.href = base + '?page=always-analytics-countries&from=' + enc(state.from) + '&to=' + enc(state.to);
        }
        apiFetch('countries', 'limit=8', function (data) {
            var tbody = document.querySelector('#aa-countries tbody');
            if (!tbody) return;
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
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

    // ── Appareils & Navigateurs ───────────────────────────────────────────────

    // DB des navigateurs — clé = nom exact renvoyé par le tracker PHP
    var BROWSER_DB = {
        'Chrome':          { domain: 'google.com',       color: '#4285F4' },
        'Firefox':         { domain: 'mozilla.org',      color: '#FF7139' },
        'Safari':          { domain: 'apple.com',        color: '#006CFF' },
        'Edge':            { domain: 'microsoft.com',    color: '#0078D4' },
        'Opera':           { domain: 'opera.com',        color: '#FF1B2D' },
        'Opera GX':        { domain: 'opera.com',        color: '#CC1B4A' },
        'Opera Mini':      { domain: 'opera.com',        color: '#FF1B2D' },
        'IE':              { domain: 'microsoft.com',    color: '#1EBBEE' },
        'Samsung Browser': { domain: 'samsung.com',      color: '#1428A0' },
        'Brave':           { domain: 'brave.com',        color: '#FB542B' },
        'Vivaldi':         { domain: 'vivaldi.com',      color: '#EF3939' },
        'DuckDuckGo':      { domain: 'duckduckgo.com',   color: '#DE5833' },
        'Yandex Browser':  { domain: 'browser.yandex.com', color: '#FF0000' },
        'UCBrowser':       { domain: 'ucweb.com',        color: '#FF6600' },
        'Puffin':          { domain: 'puffinbrowser.com',color: '#2196F3' },
        'QQ Browser':      { domain: 'qq.com',           color: '#12B7F5' },
        'Baidu Browser':   { domain: 'baidu.com',        color: '#2932E1' },
        'Silk':            { domain: 'amazon.com',       color: '#FF9900' },
        'Firefox Focus':   { domain: 'mozilla.org',      color: '#9747FF' },
    };

    // DB des OS — clé = nom exact renvoyé par le tracker PHP
    var OS_DB = {
        'Windows 11':    { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 10':    { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 8.1':   { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 8':     { domain: 'microsoft.com', color: '#0078D4' },
        'Windows 7':     { domain: 'microsoft.com', color: '#0078D4' },
        'Windows Vista': { domain: 'microsoft.com', color: '#0078D4' },
        'Windows XP':    { domain: 'microsoft.com', color: '#0078D4' },
        'Windows Phone': { domain: 'microsoft.com', color: '#0078D4' },
        'macOS':         { domain: 'apple.com',     color: '#555555' },
        'iOS':           { domain: 'apple.com',     color: '#555555' },
        'iPadOS':        { domain: 'apple.com',     color: '#555555' },
        'Android':       { domain: 'android.com',   color: '#3DDC84' },
        'Chrome OS':     { domain: 'google.com',    color: '#4285F4' },
        'Linux':         { domain: 'kernel.org',    color: '#F0AB00' },
        'Ubuntu':        { domain: 'ubuntu.com',    color: '#E95420' },
        'Fedora':        { domain: 'fedoraproject.org', color: '#294172' },
        'Debian':        { domain: 'debian.org',    color: '#A81D33' },
        'Linux Mint':    { domain: 'linuxmint.com', color: '#87CF3E' },
        'BlackBerry':    { domain: 'blackberry.com',color: '#000000' },
        'Symbian':       { domain: 'nokia.com',     color: '#005AFF' },
        'KaiOS':         { domain: 'kaiostech.com', color: '#6600CC' },
        'Tizen':         { domain: 'tizen.org',     color: '#2196F3' },
        'HarmonyOS':     { domain: 'harmonyos.com', color: '#CF0A2C' },
    };

    var _devData   = null; // réponse API complète
    var _devFilter = 'all';

    function getFaviconUrl(domain) {
        return 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(domain) + '&sz=32';
    }

    function renderDeviceRow(name, count, maxCount, dbEntry) {
        var color   = dbEntry ? dbEntry.color : '#64748B';
        var domain  = dbEntry ? dbEntry.domain : name.toLowerCase().replace(/\s+/g, '') + '.com';
        var pct     = Math.round((count / maxCount) * 100);
        var initial = (name || '?').charAt(0).toUpperCase();
        var fallbackStyle = 'display:none;width:18px;height:18px;border-radius:3px;'
                          + 'background:' + color + ';color:#fff;'
                          + 'font-size:11px;font-weight:700;line-height:18px;'
                          + 'text-align:center;flex-shrink:0;';

        return '<div class="aa-dev-row">'
            + '<div class="aa-dev-identity">'
            +   '<span class="aa-dev-icon" style="background:' + color + '18;">'
            +     '<img class="aa-dev-favicon" src="' + getFaviconUrl(domain) + '" width="18" height="18" alt="" loading="lazy" decoding="async"'
            +         ' onerror="this.style.display=\'none\';this.nextSibling.style.display=\'flex\'">'
            +     '<span class="aa-dev-fallback" style="' + fallbackStyle + '">' + initial + '</span>'
            +   '</span>'
            +   '<span class="aa-dev-name">' + esc(name) + '</span>'
            + '</div>'
            + '<div class="aa-dev-bar-wrap">'
            +   '<div class="aa-dev-bar-track"><div class="aa-dev-bar-fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
            + '</div>'
            + '<span class="aa-dev-count">' + fmt(count) + ' sess.</span>'
            + '</div>';
    }

    function renderDeviceSection(title, rows, dbMap) {
        if (!rows || !rows.length) return '';
        var maxCount = Math.max.apply(null, rows.map(function(r) { return +r.count || 0; })) || 1;
        return '<div class="aa-dev-section">'
            + '<div class="aa-dev-section-title">' + title + '</div>'
            + rows.map(function(r) {
                var name = r.browser || r.os || '?';
                // Recherche exacte d'abord, puis partielle (ex: "Windows 10" → Windows*)
                var entry = dbMap[name] || null;
                if (!entry) {
                    for (var key in dbMap) {
                        if (name.toLowerCase().indexOf(key.toLowerCase()) !== -1) {
                            entry = dbMap[key]; break;
                        }
                    }
                }
                return renderDeviceRow(name, +r.count || 0, maxCount, entry);
            }).join('')
            + '</div>';
    }

    function renderDevices() {
        var container = document.getElementById('aa-devices-list');
        if (!container || !_devData) return;

        var browsers, os;
        if (_devFilter === 'all') {
            browsers = _devData.browsers || [];
            os       = _devData.os       || [];
        } else {
            var bd   = (_devData.by_device && _devData.by_device[_devFilter]) || {};
            browsers = bd.browsers || [];
            os       = bd.os       || [];
        }

        if (!browsers.length && !os.length) {
            container.innerHTML = '<div class="aa-dev-empty">Aucune donnée pour ce filtre.</div>';
            return;
        }

        container.innerHTML =
            renderDeviceSection('Navigateurs', browsers, BROWSER_DB) +
            renderDeviceSection('Systèmes d\'exploitation', os, OS_DB);
    }

    function loadDevices() {
        apiFetch('devices', null, function (data) {
            _devData = data;

            // Mettre à jour les badges sur les onglets
            var counts = { all: 0, desktop: 0, mobile: 0, tablet: 0 };
            if (data.devices) {
                data.devices.forEach(function(d) {
                    counts.all += +d.count || 0;
                    if (d.device_type in counts) counts[d.device_type] = +d.count || 0;
                });
            }
            document.querySelectorAll('.aa-dev-tab').forEach(function(tab) {
                var key = tab.getAttribute('data-device');
                var badge = tab.querySelector('.aa-dev-count-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'aa-dev-count-badge';
                    tab.appendChild(badge);
                }
                var n = counts[key] || 0;
                badge.textContent = n;
                badge.style.display = (n === 0 && key !== 'all') ? 'none' : '';
            });

            // Mettre à jour le graphique donut si disponible
            if (data.devices && window.AlwaysAnalyticsCharts) {
                AlwaysAnalyticsCharts.renderDevicesChart(data.devices);
            }

            renderDevices();
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
        if (!v) { el.textContent = ''; el.className = 'aa-kpi-change'; return; }
        el.textContent = (v > 0 ? '+' : '') + v + '%';
        el.className   = 'aa-kpi-change ' + (v >= 0 ? 'positive' : 'negative');
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

    // ── Manual anonymization button ──────────────────────────────────────────
    var purgeBtn = document.getElementById('aa-purge-btn');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            var i18n = (alwaysAnalyticsAdmin && alwaysAnalyticsAdmin.i18n) || {};
            var confirmMsg = i18n.purgeConfirm || 'Lancer l\'anonymisation maintenant ?';
            if (!window.confirm(confirmMsg)) {
                return;
            }

            purgeBtn.disabled = true;
            purgeBtn.textContent = '⏳ …';

            var result = document.getElementById('aa-purge-result');

            var data = new URLSearchParams();
            data.append('action', 'always_analytics_manual_purge');
            data.append('nonce',  alwaysAnalyticsAdmin.purgeNonce);

            fetch(alwaysAnalyticsAdmin.ajaxUrl, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:        data.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (result) {
                    result.style.display = 'inline';
                    if (json.success) {
                        result.style.color = 'var(--aa-success, green)';
                        result.textContent = (i18n.purgeSuccess || json.data.message);
                    } else {
                        result.style.color = 'var(--aa-danger, red)';
                        result.textContent = (i18n.purgeError || 'Erreur.');
                    }
                }
            })
            .catch(function () {
                if (result) {
                    result.style.display = 'inline';
                    result.style.color   = 'var(--aa-danger, red)';
                    result.textContent   = i18n.purgeError || 'Erreur réseau.';
                }
            })
            .finally(function () {
                purgeBtn.disabled    = false;
                purgeBtn.textContent = '🔄 Lancer l\'anonymisation';
            });
        });
    }

})();
