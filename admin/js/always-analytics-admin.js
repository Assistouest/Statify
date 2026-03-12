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
        loadHitSources();
        // Reload campaigns so annotations re-apply after chart is redrawn
        if (window.AlwaysAnalyticsCampaigns) {
            window.AlwaysAnalyticsCampaigns.loadCampaigns();
        }
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
                tbody.innerHTML = '<tr><td colspan="2" class="aa-no-data">' + alwaysAnalyticsAdmin.i18n.noData + '</td></tr>';
                return;
            }
            var now = new Date();
            tbody.innerHTML = data.map(function (s) {
                var flag    = flag2(s.country_code);
                var deviceIcon = s.device_type === 'mobile'
                    ? '<svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>'
                    : s.device_type === 'tablet'
                    ? '<svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>'
                    : '<svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>';
                var vid     = s.visitor_hash.substring(0, 8);
                var ended   = new Date(s.ended_at + 'Z');
                var sec     = Math.floor((now - ended) / 1000);
                var time    = sec < 60 ? 'En ce moment' : 'Il y a ' + Math.floor(sec / 60) + ' min';
                var dur     = parseInt(s.total_duration, 10) || 0;
                var pages   = parseInt(s.total_pages, 10) || parseInt(s.last_page_count, 10) || 0;
                var multi   = parseInt(s.session_count, 10) > 1 ? ' <small style="color:#64748b">(' + s.session_count + ' visites)</small>' : '';
                var href    = '?page=always-analytics-visitor&visitor_hash=' + enc(s.visitor_hash);
                // Favicon du référent si disponible
                var refFavicon = '';
                if (s.last_referrer_domain) {
                    var refFaviconUrl = getFaviconUrl(s.last_referrer_domain);
                    refFavicon = ' <img src="' + refFaviconUrl + '" width="13" height="13" alt="' + esc(s.last_referrer_domain) + '" title="' + esc(s.last_referrer_domain) + '" loading="lazy" style="vertical-align:middle;border-radius:2px;opacity:.7;" onerror="this.style.display=\'none\'">';
                }
                return '<tr>'
                    + '<td>'
                    +   '<a href="' + href + '" class="aa-visitor-link"><strong>Visiteur ' + vid + '</strong></a>'
                    +   multi
                    +   '<br><small>' + flag + ' ' + deviceIcon + refFavicon + ' &middot; ' + pages + ' page' + (pages > 1 ? 's' : '') + '</small>'
                    + '</td>'
                    + '<td style="text-align:right">'
                    +   '<span class="aa-time-badge">' + time + '</span>'
                    +   '<br><small>' + fmtDuration(dur) + '</small>'
                    + '</td>'
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
            var info = r._info || REF_DB.categorize(r.referrer_domain);
            if (_refCat === 'all') return true;
            return info.cat === _refCat;
        });

        if (!rows.length) {
            container.innerHTML = '<div class="aa-ref-empty">Aucune source dans cette catégorie.</div>';
            return;
        }

        var maxHits = Math.max.apply(null, rows.map(function (r) { return +r.hits || 0; })) || 1;

        var rendered = rows.map(function (r) {
            var info   = r._info || REF_DB.categorize(r.referrer_domain);
            var pct    = Math.round(((+r.hits || 0) / maxHits) * 100);
            var label  = esc(info.label);
            var domain = esc(r.referrer_domain || '—');
            var hits   = fmt(+r.hits || 0);
            var uniq   = fmt(+r.unique_visitors || 0);

            var rawDomain = (r.referrer_domain || '').toLowerCase();
            var rawLabel  = info.label.toLowerCase();
            var showDomain = info.cat !== 'site' && info.cat !== 'direct'
                && rawDomain !== ''
                && rawDomain !== rawLabel
                && rawLabel.indexOf(rawDomain.replace(/^www\./, '')) === -1;

            // Favicon Google S2 — asynchrone, non bloquant.
            var faviconDomain = encodeURIComponent(r.referrer_domain || info.label);
            var faviconUrl    = r.referrer_domain
                ? 'https://www.google.com/s2/favicons?domain=' + faviconDomain + '&sz=32'
                : '';
            var initial       = (info.label || '?').charAt(0).toUpperCase();
            var fallbackStyle = 'width:18px;height:18px;border-radius:3px;'
                              + 'background:' + info.color + ';color:#fff;'
                              + 'font-size:11px;font-weight:700;line-height:18px;'
                              + 'text-align:center;flex-shrink:0;';

            var iconHtml;
            if (r.referrer_domain) {
                iconHtml = '<img class="aa-ref-favicon" src="' + faviconUrl + '" width="18" height="18" alt="" loading="lazy" decoding="async"'
                         + ' onerror="this.style.display=\'none\';this.nextSibling.style.display=\'flex\'">'
                         + '<span class="aa-ref-fallback" style="display:none;' + fallbackStyle + '">' + initial + '</span>';
            } else {
                // Direct : icône inline (pas de favicon)
                iconHtml = '<span style="' + fallbackStyle + 'display:flex;align-items:center;justify-content:center;">'
                         + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M12 3l9 9-9 9"/></svg>'
                         + '</span>';
            }

            var html = '<div class="aa-ref-row">'
                + '<div class="aa-ref-identity">'
                +   '<span class="aa-ref-icon" style="background:' + info.color + '18;">'
                +     iconHtml
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
            var raw = data || [];

            // ── Fusion des sous-domaines search par label ─────────────────────
            // ex: google.fr + google.com + fr.search.yahoo.com → un seul "Google"
            var merged = {};
            var order  = [];
            raw.forEach(function (r) {
                var info = REF_DB.categorize(r.referrer_domain);
                // Clé de fusion : pour search, on regroupe par label ; sinon par domaine
                var key = (info.cat === 'search') ? ('search:' + info.label) : (r.referrer_domain || '__direct__');
                if (!merged[key]) {
                    merged[key] = {
                        referrer_domain: r.referrer_domain,
                        hits:            0,
                        unique_visitors: 0,
                        _info:           info,
                    };
                    order.push(key);
                }
                merged[key].hits            += (+r.hits || 0);
                merged[key].unique_visitors += (+r.unique_visitors || 0);
            });
            _refData = order.map(function (k) { return merged[k]; });

            // Tri par hits desc
            _refData.sort(function (a, b) {
                return (+b.hits || 0) - (+a.hits || 0);
            });

            // ── Compteurs par catégorie ───────────────────────────────────────
            var counts = { all: 0, search: 0, social: 0, ai: 0, site: 0, direct: 0 };
            _refData.forEach(function (r) {
                counts.all++;
                var cat = r._info ? r._info.cat : REF_DB.categorize(r.referrer_domain).cat;
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

    /**
     * Génère une balise <img> pour le drapeau du pays.
     * SVG servis localement depuis /assets/flags/ (lipis/flag-icons — MIT).
     * Aucune requête externe, respectueux de la vie privée.
     *
     * @param {string} code  Code ISO 3166-1 alpha-2 (ex: "US", "FR").
     * @returns {string}     Balise <img> HTML ou badge fallback.
     */
    function flag2(code) {
        if (!code || code.length !== 2) {
            return '<span style="display:inline-block;padding:0 4px;height:14px;line-height:14px;'
                 + 'background:#e2e8f0;border-radius:2px;font-size:10px;color:#475569;font-weight:700;'
                 + 'vertical-align:middle;">?</span>';
        }
        var baseUrl = (alwaysAnalyticsAdmin && alwaysAnalyticsAdmin.flagsUrl) || '';
        var lc      = code.toLowerCase();
        var uc      = code.toUpperCase();
        return '<img src="' + baseUrl + lc + '.webp" alt="' + uc + '" title="' + uc + '" '
             + 'width="20" height="14" '
             + 'style="vertical-align:middle;border-radius:2px;object-fit:cover;" '
             + 'loading="lazy" '
             + 'onerror="this.replaceWith(document.createTextNode(\'' + uc + '\'))" />';
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

    // ── Campaigns ─────────────────────────────────────────────────────────────

    window.AlwaysAnalyticsCampaigns = (function () {

        var _campaigns = [];

        function loadCampaigns() {
            fetch(API_BASE + 'campaigns?_t=' + Date.now(), {
                method:  'GET',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _campaigns = data || [];
                if (window.AlwaysAnalyticsCharts) {
                    AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                }
                renderCampaignsList();
            })
            .catch(function (e) { console.error('[AA Campaigns] load', e); });
        }

        function renderCampaignsList() {
            var container = document.getElementById('aa-campaigns-list');
            if (!container) return;

            var empty = container.querySelector('.aa-no-data');

            if (!_campaigns.length) {
                container.innerHTML = '<p class="aa-no-data">' + (alwaysAnalyticsAdmin.i18n.noData || 'Aucun événement.') + '</p>';
                return;
            }

            // Tri par date décroissante
            var sorted = _campaigns.slice().sort(function (a, b) {
                return b.event_date.localeCompare(a.event_date);
            });

            container.innerHTML = sorted.map(function (c) {
                var parts = c.event_date.split('-');
                var d = new Date(parts[0], parts[1] - 1, parts[2]);
                var dLabel = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
                var color = c.color || '#6c63ff';
                return '<div class="aa-camp-row" data-id="' + c.id + '">'
                    + '<span class="aa-camp-dot" style="background:' + color + ';"></span>'
                    + '<div class="aa-camp-info">'
                    +   '<strong class="aa-camp-name">' + esc(c.label) + '</strong>'
                    +   '<span class="aa-camp-date">' + dLabel + '</span>'
                    +   (c.description ? '<span class="aa-camp-desc-preview">' + esc(c.description) + '</span>' : '')
                    + '</div>'
                    + '<div class="aa-camp-actions">'
                    +   '<button class="aa-camp-edit" data-id="' + c.id + '" title="Modifier"><svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>'
                    +   '<button class="aa-camp-del" data-id="' + c.id + '" title="Supprimer"><svg class="aa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>'
                    + '</div>'
                    + '</div>';
            }).join('');
        }

        function openModal() {
            var modal = document.getElementById('aa-campaign-modal');
            if (!modal) return;
            // Pré-remplir la date du jour
            var today = new Date();
            var yyyy  = today.getFullYear();
            var mm    = String(today.getMonth() + 1).padStart(2, '0');
            var dd    = String(today.getDate()).padStart(2, '0');
            document.getElementById('aa-camp-date').value  = yyyy + '-' + mm + '-' + dd;
            document.getElementById('aa-camp-label').value = '';
            document.getElementById('aa-camp-desc').value  = '';
            document.getElementById('aa-camp-color').value = '#6c63ff';
            modal.querySelectorAll('.aa-swatch').forEach(function (s) {
                s.classList.toggle('aa-swatch--active', s.getAttribute('data-color') === '#6c63ff');
            });
            var err = document.getElementById('aa-camp-error');
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            modal.classList.add('aa-modal--open');
            setTimeout(function () {
                var lbl = document.getElementById('aa-camp-label');
                if (lbl) lbl.focus();
            }, 50);
        }

        function closeModal() {
            var modal = document.getElementById('aa-campaign-modal');
            if (modal) modal.classList.remove('aa-modal--open');
        }

        function saveCampaign() {
            var date  = document.getElementById('aa-camp-date').value;
            var label = (document.getElementById('aa-camp-label').value || '').trim();
            var desc  = (document.getElementById('aa-camp-desc').value || '').trim();
            var color = document.getElementById('aa-camp-color').value || '#6c63ff';
            var err   = document.getElementById('aa-camp-error');

            if (!date || !label) {
                if (err) { err.textContent = 'La date et le label sont requis.'; err.style.display = 'block'; }
                return;
            }

            var btn = document.getElementById('aa-camp-save');
            if (btn) { btn.disabled = true; btn.textContent = 'Enregistrement…'; }

            fetch(API_BASE + 'campaigns', {
                method:  'POST',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_date: date, label: label, description: desc, color: color }),
            })
            .then(function (r) {
                if (r.status === 409) throw new Error('Un événement existe déjà pour cette date.');
                if (!r.ok) throw new Error('Erreur serveur (' + r.status + ').');
                return r.json();
            })
            .then(function (data) {
                _campaigns.push(data);
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                renderCampaignsList();
                closeModal();
            })
            .catch(function (e) {
                if (err) { err.textContent = e.message || 'Erreur.'; err.style.display = 'block'; }
            })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.textContent = 'Enregistrer'; }
            });
        }

        function deleteCampaign(id) {
            if (!confirm('Supprimer cet événement ?')) return;
            var sid = String(id);
            fetch(API_BASE + 'campaigns/' + sid, {
                method:  'DELETE',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE },
            })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function () {
                _campaigns = _campaigns.filter(function (c) { return String(c.id) !== sid; });
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                renderCampaignsList();
            })
            .catch(function () { alert('Impossible de supprimer l\'événement.'); });
        }

        function openEditModal(id) {
            var sid  = String(id);
            var camp = _campaigns.find(function (c) { return String(c.id) === sid; });
            if (!camp) return;
            var modal = document.getElementById('aa-campaign-edit-modal');
            if (!modal) return;

            document.getElementById('aa-edit-camp-id').value    = camp.id;
            document.getElementById('aa-edit-camp-date').value  = camp.event_date;
            document.getElementById('aa-edit-camp-label').value = camp.label;
            document.getElementById('aa-edit-camp-desc').value  = camp.description || '';
            document.getElementById('aa-edit-camp-color').value = camp.color || '#6c63ff';

            var color = camp.color || '#6c63ff';
            modal.querySelectorAll('#aa-edit-swatches .aa-swatch').forEach(function (s) {
                s.classList.toggle('aa-swatch--active', s.getAttribute('data-color') === color);
            });

            var err = document.getElementById('aa-edit-camp-error');
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            modal.classList.add('aa-modal--open');
            setTimeout(function () { document.getElementById('aa-edit-camp-label').focus(); }, 50);
        }

        function closeEditModal() {
            var modal = document.getElementById('aa-campaign-edit-modal');
            if (modal) modal.classList.remove('aa-modal--open');
        }

        function saveEditCampaign() {
            var id    = parseInt(document.getElementById('aa-edit-camp-id').value, 10);
            var date  = document.getElementById('aa-edit-camp-date').value;
            var label = (document.getElementById('aa-edit-camp-label').value || '').trim();
            var desc  = (document.getElementById('aa-edit-camp-desc').value || '').trim();
            var color = document.getElementById('aa-edit-camp-color').value || '#6c63ff';
            var err   = document.getElementById('aa-edit-camp-error');

            if (!date || !label) {
                if (err) { err.textContent = 'La date et le label sont requis.'; err.style.display = 'block'; }
                return;
            }

            var btn = document.getElementById('aa-edit-camp-save');
            if (btn) { btn.disabled = true; btn.textContent = 'Enregistrement…'; }

            // Vérifier doublon date (sauf si c'est le même événement)
            var existingOnDate = _campaigns.find(function (c) { return c.event_date === date && String(c.id) !== String(id); });
            if (existingOnDate) {
                if (err) { err.textContent = 'Un événement existe déjà pour cette date.'; err.style.display = 'block'; }
                if (btn) { btn.disabled = false; btn.textContent = 'Enregistrer'; }
                return;
            }

            // DELETE + re-POST (pas d'endpoint PUT natif)
            fetch(API_BASE + 'campaigns/' + id, {
                method:  'DELETE',
                cache:   'no-store',
                headers: { 'X-WP-Nonce': NONCE },
            })
            .then(function (r) { if (!r.ok) throw new Error('Erreur suppression.'); return r.json(); })
            .then(function () {
                return fetch(API_BASE + 'campaigns', {
                    method:  'POST',
                    cache:   'no-store',
                    headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_date: date, label: label, description: desc, color: color }),
                });
            })
            .then(function (r) {
                if (!r.ok) throw new Error('Erreur création.');
                return r.json();
            })
            .then(function (newCamp) {
                var sid = String(id);
                _campaigns = _campaigns.filter(function (c) { return String(c.id) !== sid; });
                _campaigns.push(newCamp);
                if (window.AlwaysAnalyticsCharts) AlwaysAnalyticsCharts.setCampaigns(_campaigns);
                renderCampaignsList();
                closeEditModal();
            })
            .catch(function (e) {
                if (err) { err.textContent = e.message || 'Erreur.'; err.style.display = 'block'; }
            })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.textContent = 'Enregistrer'; }
            });
        }

        function bindCampaignEvents() {
            var modal   = document.getElementById('aa-campaign-modal');
            var addBtn  = document.getElementById('aa-add-campaign-btn');
            var addBtn2 = document.getElementById('aa-add-campaign-btn2');
            var saveBtn = document.getElementById('aa-camp-save');

            // ── Ouvrir modal création ────────────────────────────────────────────
            function onAddClick(e) {
                e.preventDefault();
                e.stopPropagation();
                openModal();
            }
            if (addBtn)  addBtn.addEventListener('click', onAddClick);
            if (addBtn2) addBtn2.addEventListener('click', onAddClick);

            // ── Fermer modal création ────────────────────────────────────────────
            if (modal) {
                var closeBtn = modal.querySelector('.aa-modal-close');
                if (closeBtn) closeBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeModal(); });
                var cancelBtn = modal.querySelector('.aa-modal-cancel');
                if (cancelBtn) cancelBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeModal(); });
                var overlay = modal.querySelector('.aa-modal-overlay');
                if (overlay) overlay.addEventListener('click', function (e) { e.stopPropagation(); closeModal(); });
            }

            // ── Fermer modal édition ─────────────────────────────────────────────
            var editModal = document.getElementById('aa-campaign-edit-modal');
            if (editModal) {
                var editCloseBtn = editModal.querySelector('.aa-modal-close');
                if (editCloseBtn) editCloseBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeEditModal(); });
                var editCancelBtn = editModal.querySelector('.aa-modal-cancel');
                if (editCancelBtn) editCancelBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); closeEditModal(); });
                var editOverlay = editModal.querySelector('.aa-modal-overlay');
                if (editOverlay) editOverlay.addEventListener('click', function (e) { e.stopPropagation(); closeEditModal(); });
            }

            // ── Escape ferme toutes les modales ──────────────────────────────────
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeModal(); closeEditModal(); }
            });

            // ── Sauvegarder création ─────────────────────────────────────────────
            if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); saveCampaign(); });

            // ── Sauvegarder édition ──────────────────────────────────────────────
            var editSaveBtn = document.getElementById('aa-edit-camp-save');
            if (editSaveBtn) editSaveBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); saveEditCampaign(); });

            // ── Color swatches modal création ────────────────────────────────────
            if (modal) {
                modal.querySelectorAll('.aa-swatch').forEach(function (swatch) {
                    swatch.addEventListener('click', function (e) {
                        e.stopPropagation();
                        modal.querySelectorAll('.aa-swatch').forEach(function (s) { s.classList.remove('aa-swatch--active'); });
                        swatch.classList.add('aa-swatch--active');
                        var ci = document.getElementById('aa-camp-color');
                        if (ci) ci.value = swatch.getAttribute('data-color');
                    });
                });
                var colorInput = document.getElementById('aa-camp-color');
                if (colorInput) colorInput.addEventListener('input', function () {
                    modal.querySelectorAll('.aa-swatch').forEach(function (s) { s.classList.remove('aa-swatch--active'); });
                });
            }

            // ── Color swatches modal édition ─────────────────────────────────────
            if (editModal) {
                editModal.querySelectorAll('#aa-edit-swatches .aa-swatch').forEach(function (swatch) {
                    swatch.addEventListener('click', function (e) {
                        e.stopPropagation();
                        editModal.querySelectorAll('#aa-edit-swatches .aa-swatch').forEach(function (s) { s.classList.remove('aa-swatch--active'); });
                        swatch.classList.add('aa-swatch--active');
                        var ci = document.getElementById('aa-edit-camp-color');
                        if (ci) ci.value = swatch.getAttribute('data-color');
                    });
                });
                var editColorInput = document.getElementById('aa-edit-camp-color');
                if (editColorInput) editColorInput.addEventListener('input', function () {
                    editModal.querySelectorAll('#aa-edit-swatches .aa-swatch').forEach(function (s) { s.classList.remove('aa-swatch--active'); });
                });
            }

            // ── Clics édition / suppression dans la liste ────────────────────────
            var listContainer = document.getElementById('aa-campaigns-list');
            if (listContainer) {
                listContainer.addEventListener('click', function (e) {
                    var editBtn = e.target.closest('.aa-camp-edit');
                    var delBtn  = e.target.closest('.aa-camp-del');
                    if (editBtn) {
                        e.stopPropagation();
                        openEditModal(parseInt(editBtn.getAttribute('data-id'), 10));
                    }
                    if (delBtn) {
                        e.stopPropagation();
                        deleteCampaign(parseInt(delBtn.getAttribute('data-id'), 10));
                    }
                });
            }

            // ── Masquer tooltip au survol hors canvas ────────────────────────────
            var canvas = document.getElementById('aa-visits-chart');
            if (canvas) {
                canvas.addEventListener('mouseleave', function () {
                    var tip = document.getElementById('aa-camp-tooltip');
                    if (tip) tip.style.display = 'none';
                });
            }
        }

        // Init
        document.addEventListener('DOMContentLoaded', function () {
            bindCampaignEvents();
            loadCampaigns();
        });

        return { loadCampaigns: loadCampaigns, deleteCampaign: deleteCampaign };
    })();

    // ── Sources de tracking ───────────────────────────────────────────────────

    /**
     * Charge et affiche la card "Sources de tracking" (hit_source).
     * Sources : js / js_cookieless / pre_consent / noscript / cookie
     * Données : hits, visiteurs uniques, sessions, nouveaux visiteurs,
     *           % du total, hits fusionnés (pre_consent superseded), sparkline.
     */
    function loadHitSources() {
        var tbody  = document.getElementById('aa-sources-tbody');
        var bar    = document.getElementById('aa-sources-bar');
        var legend = document.getElementById('aa-sources-bar-legend');
        var badge  = document.getElementById('aa-sources-total-badge');
        var info   = document.getElementById('aa-sources-info-text');

        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" class="aa-loading-cell"><span class="aa-spinner"></span></td></tr>';
        if (bar)    bar.innerHTML    = '';
        if (legend) legend.innerHTML = '';
        if (badge)  badge.textContent = '';

        apiFetch('hit-sources', null, function (d) {
            if (!d || !d.sources || !d.sources.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="aa-no-data">Aucune donnée pour cette période.</td></tr>';
                return;
            }

            var sources   = d.sources;
            var totalHits = d.total_hits || 1;

            // ── Barre de répartition ─────────────────────────────────────────
            if (bar) {
                bar.innerHTML = '';
                var barSorted = sources.slice().sort(function (a, b) { return b.hits - a.hits; });
                barSorted.forEach(function (s) {
                    var effective = s.hits - (s.source === 'pre_consent' ? s.superseded_count : 0);
                    var pct = totalHits > 0 ? Math.max(0.5, effective / totalHits * 100) : 0;
                    var seg = document.createElement('div');
                    seg.className = 'aa-sources-bar-seg';
                    seg.style.width      = pct + '%';
                    seg.style.background = s.color;
                    seg.title = s.label + ' — ' + fmt(effective) + ' hits (' + s.pct_of_total + '%)';
                    bar.appendChild(seg);
                });
            }

            // ── Légende ──────────────────────────────────────────────────────
            if (legend) {
                legend.innerHTML = '';
                sources.forEach(function (s) {
                    var el = document.createElement('span');
                    el.className = 'aa-sources-legend-item';
                    el.innerHTML =
                        '<span class="aa-sources-dot" style="background:' + s.color + '"></span>' +
                        '<span>' + escHtml(s.label) + '</span>';
                    legend.appendChild(el);
                });
            }

            // ── Badge total ──────────────────────────────────────────────────
            if (badge) badge.textContent = fmt(totalHits) + ' hits total';

            // ── Tableau ──────────────────────────────────────────────────────
            tbody.innerHTML = '';
            sources.forEach(function (s) {
                var tr = document.createElement('tr');
                tr.className = 'aa-sources-row';

                var newVisPct = s.unique_visitors > 0
                    ? Math.round(s.new_visitors / s.unique_visitors * 100) + '%'
                    : '—';

                var supersededCell = s.source === 'pre_consent'
                    ? '<td class="aa-num">' +
                          '<span class="aa-sources-fused-badge" title="Hits pre_consent marqués is_superseded=1 après acceptation bannière — exclus du comptage principal">' +
                              fmt(s.superseded_count) +
                          '</span>' +
                      '</td>'
                    : '<td class="aa-num aa-muted">—</td>';

                tr.innerHTML =
                    '<td class="aa-sources-label-cell">' +
                        '<span class="aa-sources-icon" style="color:' + s.color + '">' + sourcesIcon(s.icon) + '</span>' +
                        '<span class="aa-sources-name">' + escHtml(s.label) + '</span>' +
                        '<span class="aa-sources-desc-icon" title="' + escAttr(s.description) + '">' +
                            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
                        '</span>' +
                    '</td>' +
                    '<td class="aa-num"><strong>' + fmt(s.hits) + '</strong></td>' +
                    '<td class="aa-num">' + fmt(s.unique_visitors) + '</td>' +
                    '<td class="aa-num">' + fmt(s.sessions) + '</td>' +
                    '<td class="aa-num">' +
                        fmt(s.new_visitors) +
                        '<span class="aa-sources-newvis-pct"> (' + newVisPct + ')</span>' +
                    '</td>' +
                    '<td class="aa-num">' +
                        '<span class="aa-sources-pct-pill" style="--src-color:' + s.color + '">' + s.pct_of_total + '%</span>' +
                    '</td>' +
                    supersededCell +
                    '<td class="aa-sources-spark">' + buildSparkline(s.trend, s.color) + '</td>';

                tbody.appendChild(tr);
            });

            // ── Bloc info contextuel ─────────────────────────────────────────
            if (info) {
                var msgs = [];
                var hasPreConsent = sources.some(function (s) { return s.source === 'pre_consent'; });
                var hasNoscript   = sources.some(function (s) { return s.source === 'noscript'; });
                var hasFallback   = sources.some(function (s) { return s.source === 'js_cookieless'; });
                if (hasPreConsent) msgs.push('La bannière de consentement est active — les hits pré-consentement fusionnés (acceptation) sont exclus du comptage principal.');
                if (hasNoscript)   msgs.push('Des visiteurs naviguent sans JavaScript (hits noscript).');
                if (hasFallback)   msgs.push('Des cookies sont bloqués chez certains visiteurs → fallback cookieless automatique (hits "JS sans cookie").');
                if (!msgs.length)  msgs.push('Mode cookieless standard actif — le tracker JS est la source exclusive de hits.');
                info.textContent = msgs.join(' ');
            }
        });
    }

    // ── Helpers Sources ───────────────────────────────────────────────────────

    function sourcesIcon(type) {
        var icons = {
            js:       '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
            fallback: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            consent:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            noscript: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>',
            cookie:   '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/></svg>',
            unknown:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        };
        return icons[type] || icons.unknown;
    }

    function buildSparkline(trend, color) {
        if (!trend || trend.length < 2) {
            return '<span class="aa-muted" style="font-size:11px;">—</span>';
        }
        var W = 80, H = 28, pad = 2;
        var values = trend.map(function (t) { return t.hits; });
        var minV = Math.min.apply(null, values);
        var maxV = Math.max.apply(null, values);
        var range = maxV - minV || 1;
        var n = values.length;
        var pts = values.map(function (v, i) {
            var x = pad + (i / (n - 1)) * (W - 2 * pad);
            var y = H - pad - ((v - minV) / range) * (H - 2 * pad);
            return x.toFixed(1) + ',' + y.toFixed(1);
        });
        var first = pts[0].split(',');
        var last  = pts[pts.length - 1].split(',');
        var area  = 'M' + first[0] + ',' + H + ' L' + pts.join(' L') + ' L' + last[0] + ',' + H + ' Z';
        return '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" class="aa-sparkline">' +
            '<path d="' + area + '" fill="' + color + '" opacity="0.12"/>' +
            '<polyline points="' + pts.join(' ') + '" fill="none" stroke="' + color + '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<circle cx="' + last[0] + '" cy="' + last[1] + '" r="2" fill="' + color + '"/>' +
        '</svg>';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(s) {
        return String(s).replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

})();
