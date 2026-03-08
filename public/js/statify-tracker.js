/**
 * Advanced Stats — Front-end Tracker v4
 * Tracking : page vue, durée de session (heartbeat + sendBeacon),
 *            temps d'engagement (visibilité), profondeur de scroll (25/50/75/100%).
 *
 * Changements majeurs vs v3 :
 * - sendBeacon + visibilitychange pour ping final au départ → élimine ~80% des sessions 0s
 * - Premier ping réduit à 5s (au lieu de 10s)
 * - Calcul de durée côté client (engagement time) transmis au serveur
 * - Heartbeat adapté à la visibilité : pause quand l'onglet est masqué, reprise au retour
 * - Fallback pagehide/beforeunload pour navigateurs sans support visibilitychange
 */
(function () {
    'use strict';

    if (typeof statifyConfig === 'undefined') return;

    var config      = statifyConfig;
    var COOKIE_VID  = 'statify_vid';
    var COOKIE_CONS = 'statify_consent';
    var COOKIE_DAYS = 395;  // 13 mois — recommandation CNIL pour cookies analytiques

    // ── Chronomètre d'engagement ──────────────────────────────────────────────
    // Mesure le temps réel passé sur la page (uniquement quand l'onglet est visible).
    // Similaire au "engagement time" de GA4.

    var _pageStartTime    = Date.now();           // timestamp absolu du chargement
    var _engagementMs     = 0;                    // temps d'engagement cumulé (ms)
    var _lastVisibleTime  = Date.now();           // dernier moment où la page est devenue visible
    var _isPageVisible    = !document.hidden;     // état courant de visibilité
    var _hitSent          = false;                // le hit initial a-t-il été envoyé ?
    var _finalPingSent    = false;                // le ping final a-t-il été envoyé ?

    /**
     * Met à jour le compteur d'engagement.
     * Appelé à chaque transition visible→caché ou avant chaque ping.
     */
    function updateEngagement() {
        if (_isPageVisible) {
            _engagementMs += Math.max(0, Date.now() - _lastVisibleTime);
            _lastVisibleTime = Date.now();
        }
    }

    /**
     * Durée totale depuis le chargement en secondes.
     */
    function totalDurationSeconds() {
        return Math.round(Math.max(0, Date.now() - _pageStartTime) / 1000);
    }

    /**
     * Durée d'engagement en secondes (page visible uniquement).
     */
    function engagementSeconds() {
        updateEngagement();
        return Math.round(_engagementMs / 1000);
    }

    // ── Consentement ──────────────────────────────────────────────────────────

    function consentStatus() {
        if (config.trackingMode !== 'cookie') return 'not_required';
        if (config.consentGiven === 'not_required') return 'not_required';
        if (window.statifyConsentStatus) return window.statifyConsentStatus;
        var stored = getCookie(COOKIE_CONS);
        if (stored === 'granted') { window.statifyConsentStatus = 'granted'; return 'granted'; }
        if (stored === 'denied')  { window.statifyConsentStatus = 'denied';  return 'denied'; }
        return 'pending';
    }

    // ── Tracking principal ────────────────────────────────────────────────────

    function track() {
        var cs = consentStatus();
        if (cs === 'denied') return;
        if (cs === 'pending') {
            window.statifyOnConsent = function (status) {
                if (status === 'granted') doSendHit();
            };
            return;
        }
        doSendHit();
    }

    function doSendHit() {
        var data = collectData();
        if (!data) return;
        sendPost(data);
        _hitSent = true;

        // Démarre le scroll tracker après l'envoi du hit initial
        initScrollTracker();

        // Démarre le suivi de visibilité et les heartbeats
        initVisibilityTracker();
        startHeartbeat();
    }

    // ── Collecte ──────────────────────────────────────────────────────────────

    function collectData() {
        var data = {
            url:          window.location.href,
            title:        document.title,
            referrer:     document.referrer || '',
            screenWidth:  screen.width,
            screenHeight: screen.height,
            sessionId:    getSessionId(),
        };

        if (config.postId) data.postId = config.postId;

        try {
            var p = new URLSearchParams(window.location.search);
            if (p.get('utm_source'))   data.utmSource   = p.get('utm_source');
            if (p.get('utm_medium'))   data.utmMedium   = p.get('utm_medium');
            if (p.get('utm_campaign')) data.utmCampaign = p.get('utm_campaign');
        } catch(e) {}

        if (config.trackingMode === 'cookie') {
            data.visitorId = getOrCreateVisitorId();
        }

        if (typeof window.statifyBeforeTrack === 'function') {
            data = window.statifyBeforeTrack(data);
            if (!data) return null;
        }

        return data;
    }

    // ── Suivi de visibilité ───────────────────────────────────────────────────

    var _visibilityInited = false;

    function initVisibilityTracker() {
        if (_visibilityInited) return;
        _visibilityInited = true;

        // visibilitychange — standard moderne, déclenché à chaque changement d'onglet/fermeture
        document.addEventListener('visibilitychange', onVisibilityChange);

        // pagehide — filet de sécurité pour iOS Safari (bfcache) et navigation SPA
        window.addEventListener('pagehide', onPageHide);

        // beforeunload — dernier recours pour les anciens navigateurs
        window.addEventListener('beforeunload', onBeforeUnload);
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'hidden') {
            onPageBecameHidden();
        } else {
            onPageBecameVisible();
        }
    }

    function onPageBecameHidden() {
        if (_isPageVisible) {
            updateEngagement();
            _isPageVisible = false;
        }
        sendFinalPing();
    }

    function onPageBecameVisible() {
        if (!_isPageVisible) {
            _isPageVisible = true;
            _lastVisibleTime = Date.now();
            // Permet un nouveau ping final au prochain départ
            _finalPingSent = false;
            // Ping de réconciliation pour signaler le retour
            sendPing();
        }
    }

    function onPageHide() {
        onPageBecameHidden();
    }

    function onBeforeUnload() {
        onPageBecameHidden();
    }

    // ── Scroll tracker ────────────────────────────────────────────────────────

    var _scrollSent     = { 25: false, 50: false, 75: false, 100: false };
    var _scrollInited   = false;
    var _scrollRaf      = null;
    var _currentScrollPct = 0;

    function initScrollTracker() {
        if (_scrollInited) return;
        _scrollInited = true;

        window.addEventListener('scroll', onScroll, { passive: true });
        requestAnimationFrame(checkScrollDepth);
    }

    function onScroll() {
        if (_scrollRaf) return;
        _scrollRaf = requestAnimationFrame(function () {
            _scrollRaf = null;
            checkScrollDepth();
        });
    }

    function checkScrollDepth() {
        var docH    = Math.max(
            document.body.scrollHeight,
            document.documentElement.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.offsetHeight
        );
        var viewH   = window.innerHeight || document.documentElement.clientHeight;
        var scrolled = window.pageYOffset || document.documentElement.scrollTop || 0;

        var scrollable = docH - viewH;
        var pct = scrollable > 10
            ? Math.min(100, Math.round(((scrolled + viewH) / docH) * 100))
            : 100;

        _currentScrollPct = pct;

        var thresholds = [25, 50, 75, 100];
        for (var i = 0; i < thresholds.length; i++) {
            var t = thresholds[i];
            if (!_scrollSent[t] && pct >= t) {
                _scrollSent[t] = true;
                sendScrollEvent(t);
            }
        }
    }

    function sendScrollEvent(depth) {
        var cs = consentStatus();
        if (cs === 'denied' || cs === 'pending') return;

        var data = {
            action:     'scroll',
            sessionId:  getSessionId(),
            scrollDepth: depth,
            url:        window.location.href,
        };

        if (config.trackingMode === 'cookie') {
            data.visitorId = getCookie(COOKIE_VID);
            if (!data.visitorId) return;
        }

        sendPost(data);
    }

    // ── Heartbeat (durée de session) ──────────────────────────────────────────

    function startHeartbeat() {
        // Premier ping après 5 secondes (réduit depuis 10s)
        setTimeout(function () {
            sendPing();
            // Puis heartbeat régulier toutes les 15 secondes
            setInterval(sendPing, 15000);
        }, 5000);
    }

    function sendPing() {
        var cs = consentStatus();
        if (cs === 'denied' || cs === 'pending') return;
        if (!_hitSent) return;

        // On n'envoie pas de ping heartbeat si l'onglet est caché
        // (le ping final via sendBeacon s'en occupe)
        if (document.hidden) return;

        var data = {
            action:          'ping',
            sessionId:       getSessionId(),
            scrollDepth:     _currentScrollPct,
            clientDuration:  totalDurationSeconds(),
            engagementTime:  engagementSeconds(),
        };

        if (config.trackingMode === 'cookie') {
            data.visitorId = getCookie(COOKIE_VID);
            if (!data.visitorId) return;
        }

        sendPost(data);
    }

    /**
     * Ping final envoyé via sendBeacon quand l'utilisateur quitte la page.
     * sendBeacon() est garanti d'être envoyé même pendant le déchargement.
     */
    function sendFinalPing() {
        var cs = consentStatus();
        if (cs === 'denied' || cs === 'pending') return;
        if (!_hitSent) return;
        if (_finalPingSent) return;
        _finalPingSent = true;

        var data = {
            action:          'ping',
            sessionId:       getSessionId(),
            scrollDepth:     _currentScrollPct,
            clientDuration:  totalDurationSeconds(),
            engagementTime:  engagementSeconds(),
            isFinal:         true,
        };

        if (config.trackingMode === 'cookie') {
            data.visitorId = getCookie(COOKIE_VID);
            if (!data.visitorId) return;
        }

        var payload = JSON.stringify(data);

        // sendBeacon — fiable pendant le déchargement de page
        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([payload], { type: 'application/json' });
                var sent = navigator.sendBeacon(config.endpoint, blob);
                if (sent) return;
            }
        } catch (e) {}

        // Fallback : fetch avec keepalive
        try {
            fetch(config.endpoint, {
                method:    'POST',
                keepalive: true,
                headers:   { 'Content-Type': 'application/json' },
                body:      payload,
            }).catch(function () {});
        } catch (e) {}
    }

    // ── Envoi ─────────────────────────────────────────────────────────────────

    function sendPost(data) {
        try {
            fetch(config.endpoint, {
                method:    'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            }).catch(function () {});
        } catch (e) {}
    }

    // ── Session ID ────────────────────────────────────────────────────────────

    function getSessionId() {
        var KEY = 'statify_sid';
        var sid;
        try {
            sid = sessionStorage.getItem(KEY);
            if (!sid) { sid = genId(); sessionStorage.setItem(KEY, sid); }
        } catch (e) {
            if (!window._statifySidFallback) window._statifySidFallback = genId();
            sid = window._statifySidFallback;
        }
        return sid;
    }

    // ── Visitor ID (cookie mode) ──────────────────────────────────────────────

    function getOrCreateVisitorId() {
        var vid = getCookie(COOKIE_VID);
        if (!vid) { vid = genId(); setVisitorCookie(vid); }
        return vid;
    }

    function setVisitorCookie(value) {
        var expires = new Date();
        expires.setTime(expires.getTime() + COOKIE_DAYS * 86400000);
        var c = COOKIE_VID + '=' + encodeURIComponent(value)
              + ';expires=' + expires.toUTCString()
              + ';path=/;SameSite=Lax';
        if (window.location.protocol === 'https:') c += ';Secure';
        document.cookie = c;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function genId() {
        try { if (crypto && crypto.randomUUID) return crypto.randomUUID(); } catch(e) {}
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function getCookie(name) {
        var m = document.cookie.match('(?:^|;)\\s*' + name + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : null;
    }

    // ── Démarrage ─────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', track);
    } else {
        track();
    }

})();
