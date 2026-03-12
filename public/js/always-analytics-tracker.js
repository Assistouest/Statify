/**
 * Advanced Stats — Front-end Tracker v5
 *
 * Modes de fonctionnement :
 *
 * ── Mode cookieless (trackingMode = 'cookieless') ──────────────────────────
 *   Démarre immédiatement. Hash côté serveur (IP+UA+date). Aucun cookie.
 *   Scroll, heartbeat, sendBeacon pleinement actifs.
 *
 * ── Mode cookie + bannière (trackingMode = 'cookie', preConsentEnabled = true) ──
 *   Phase 1 — PRE_CONSENT (avant réponse bannière) :
 *     • Envoi immédiat d'un hit 'pre_consent' en mode cookieless avancé
 *     • Scroll, heartbeat, durée collectés normalement (même sessionId)
 *
 *   Phase 2a — GRANTED (visiteur accepte) :
 *     • Cookie visitorId créé (13 mois)
 *     • Hit complet envoyé avec preConsentSessionId → fusion côté serveur
 *       (hit pre_consent marqué superseded, session rattachée au visitor cookie)
 *     • 0 doublon garanti
 *
 *   Phase 2b — DENIED (visiteur refuse) :
 *     • Cookie aa_vid supprimé s'il existe
 *     • Hit pre_consent reste en base comme visite anonyme (cookieless)
 *
 *   Phase 2c — Page fermée sans décision :
 *     • sendBeacon final envoyé avec scroll/durée collectés
 *     • Hit pre_consent reste en base comme visite anonyme
 *
 * ── Mode cookie sans bannière (preConsentEnabled absent/false) ────────────
 *   Démarre immédiatement avec cookie visitorId. Tracking complet.
 */
(function () {
    'use strict';

    if (typeof alwaysAnalyticsConfig === 'undefined') return;

    var config      = alwaysAnalyticsConfig;
    var COOKIE_VID  = 'aa_vid';
    var COOKIE_CONS = 'aa_consent';
    var COOKIE_DAYS = 395;

    // ── Chronomètre d'engagement ──────────────────────────────────────────────
    var _pageStartTime   = Date.now();
    var _engagementMs    = 0;
    var _lastVisibleTime = Date.now();
    var _isPageVisible   = !document.hidden;
    var _hitSent         = false;
    var _finalPingSent   = false;
    var _preConsentPromise = null; // Promesse du hit pre_consent — attendue avant d'envoyer le hit complet

    function updateEngagement() {
        if (_isPageVisible) {
            _engagementMs += Math.max(0, Date.now() - _lastVisibleTime);
            _lastVisibleTime = Date.now();
        }
    }
    function totalDurationSeconds() {
        return Math.round(Math.max(0, Date.now() - _pageStartTime) / 1000);
    }
    function engagementSeconds() {
        updateEngagement();
        return Math.round(_engagementMs / 1000);
    }

    // ── État du consentement ──────────────────────────────────────────────────

    function consentStatus() {
        if (config.trackingMode !== 'cookie') return 'not_required';
        if (!config.preConsentEnabled)        return 'not_required';
        if (window.alwaysAnalyticsConsentStatus)      return window.alwaysAnalyticsConsentStatus;
        var stored = getCookie(COOKIE_CONS);
        if (stored === 'granted') { window.alwaysAnalyticsConsentStatus = 'granted'; return 'granted'; }
        if (stored === 'denied')  { window.alwaysAnalyticsConsentStatus = 'denied';  return 'denied'; }
        return 'pending';
    }

    // ── Point d'entrée ────────────────────────────────────────────────────────

    function track() {
        var cs = consentStatus();

        if (cs === 'denied') {
            deleteVisitorCookie();
            return;
        }

        if (cs === 'not_required' || cs === 'granted') {
            doSendHit('js');
            return;
        }

        // cs === 'pending' : cookieless avancé immédiatement
        doSendPreConsent();

        window.alwaysAnalyticsOnConsent = function (status) {
            if (status === 'granted') {
                onConsentGranted();
            } else {
                onConsentDenied();
            }
        };
    }

    // ── Phase pre_consent ─────────────────────────────────────────────────────

    function doSendPreConsent() {
        var data = collectBaseData();
        if (!data) return;
        data.action    = 'pre_consent';
        data.hitSource = 'pre_consent';
        // Stocker la Promise pour pouvoir l'attendre dans onConsentGranted()
        // Protège contre la race condition si l'utilisateur accepte en < 200ms
        _preConsentPromise = sendPost(data);
        _hitSent = true;
        initScrollTracker();
        initVisibilityTracker();
        startHeartbeat();
    }

    // ── Acceptation ───────────────────────────────────────────────────────────

    function onConsentGranted() {
        var vid  = getOrCreateVisitorId();
        var data = collectBaseData();
        if (!data) return;
        // vid peut être null si cookie bloqué → fallback cookieless côté serveur.
        if (vid) data.visitorId = vid;
        var preSessionId = getSessionId();
        if (preSessionId) {
            data.preConsentSessionId = preSessionId;
        }

        // Attendre que le hit pre_consent soit confirmé reçu par le serveur
        // avant d'envoyer le hit complet avec preConsentSessionId.
        // Sans ça, si l'utilisateur accepte en < 200ms, le serveur cherche à fusionner
        // un hit pre_consent qui n'est pas encore en base → fusion ratée → doublon.
        Promise.resolve(_preConsentPromise).then(function () {
            sendPost(data);
        });
        // _hitSent déjà true — scroll/heartbeat continuent avec la même session
    }

    // ── Refus ─────────────────────────────────────────────────────────────────

    function onConsentDenied() {
        deleteVisitorCookie();
        // Le hit pre_consent reste en base comme visite anonyme
    }

    // ── Hit complet (modes sans consentement pending) ─────────────────────────

    function doSendHit(source) {
        var data = collectBaseData();
        if (!data) return;
        if (config.trackingMode === 'cookie') {
            var vid = getOrCreateVisitorId();
            // Si vid est null (cookie bloqué), on n'inclut pas visitorId dans le body.
            // Le serveur détecte l'absence et applique le fallback cookieless automatiquement.
            if (vid) data.visitorId = vid;
        }
        data.hitSource = source || 'js';
        sendPost(data);
        _hitSent = true;
        initScrollTracker();
        initVisibilityTracker();
        startHeartbeat();
    }

    // ── Collecte des données de base ──────────────────────────────────────────

    function collectBaseData() {
        var data = {
            url:          window.location.href,
            title:        document.title,
            referrer:     getEntryReferrer(),
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
        if (typeof window.alwaysAnalyticsBeforeTrack === 'function') {
            data = window.alwaysAnalyticsBeforeTrack(data);
            if (!data) return null;
        }
        return data;
    }

    // ── Suivi de visibilité ───────────────────────────────────────────────────

    var _visibilityInited = false;

    function initVisibilityTracker() {
        if (_visibilityInited) return;
        _visibilityInited = true;
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', onPageHide);
        window.addEventListener('beforeunload', onBeforeUnload);
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'hidden') { onPageBecameHidden(); }
        else { onPageBecameVisible(); }
    }
    function onPageBecameHidden() {
        if (_isPageVisible) { updateEngagement(); _isPageVisible = false; }
        sendFinalPing();
    }
    function onPageBecameVisible() {
        if (!_isPageVisible) {
            _isPageVisible   = true;
            _lastVisibleTime = Date.now();
            _finalPingSent   = false;
            sendPing();
        }
    }
    function onPageHide()     { onPageBecameHidden(); }
    function onBeforeUnload() { onPageBecameHidden(); }

    // ── Scroll tracker ────────────────────────────────────────────────────────

    var _scrollSent   = { 25: false, 50: false, 75: false, 100: false };
    var _scrollInited = false;
    var _scrollRaf    = null;
    var _currentScrollPct = 0;

    function initScrollTracker() {
        if (_scrollInited) return;
        _scrollInited = true;
        window.addEventListener('scroll', onScroll, { passive: true });
        requestAnimationFrame(checkScrollDepth);
    }

    function onScroll() {
        if (_scrollRaf) return;
        _scrollRaf = requestAnimationFrame(function () { _scrollRaf = null; checkScrollDepth(); });
    }

    function checkScrollDepth() {
        var docH = Math.max(
            document.body.scrollHeight, document.documentElement.scrollHeight,
            document.body.offsetHeight,  document.documentElement.offsetHeight
        );
        var viewH    = window.innerHeight || document.documentElement.clientHeight;
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
        if (cs === 'denied') return;
        if (!_hitSent) return;

        var data = {
            action:      'scroll',
            sessionId:   getSessionId(),
            scrollDepth: depth,
            url:         window.location.href,
        };

        // En mode pending (pre_consent) : on envoie le scroll sans visitorId
        if (config.trackingMode === 'cookie' && cs !== 'pending') {
            data.visitorId = getCookie(COOKIE_VID);
            if (!data.visitorId) return;
        }

        sendPost(data);
    }

    // ── Heartbeat ─────────────────────────────────────────────────────────────

    function startHeartbeat() {
        setTimeout(function () {
            sendPing();
            setInterval(sendPing, 15000);
        }, 5000);
    }

    function sendPing() {
        var cs = consentStatus();
        if (cs === 'denied') return;
        if (!_hitSent) return;
        if (document.hidden) return;

        var data = {
            action:         'ping',
            sessionId:      getSessionId(),
            scrollDepth:    _currentScrollPct,
            clientDuration: totalDurationSeconds(),
            engagementTime: engagementSeconds(),
        };

        if (config.trackingMode === 'cookie' && cs !== 'pending') {
            data.visitorId = getCookie(COOKIE_VID);
            if (!data.visitorId) return;
        }

        sendPost(data);
    }

    function sendFinalPing() {
        var cs = consentStatus();
        if (cs === 'denied') return;
        if (!_hitSent) return;
        if (_finalPingSent) return;
        _finalPingSent = true;

        var data = {
            action:         'ping',
            sessionId:      getSessionId(),
            scrollDepth:    _currentScrollPct,
            clientDuration: totalDurationSeconds(),
            engagementTime: engagementSeconds(),
            isFinal:        true,
        };

        if (config.trackingMode === 'cookie' && cs !== 'pending') {
            data.visitorId = getCookie(COOKIE_VID);
            if (!data.visitorId) return;
        }

        var payload = JSON.stringify(data);
        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([payload], { type: 'application/json' });
                if (navigator.sendBeacon(config.endpoint, blob)) return;
            }
        } catch (e) {}
        try {
            fetch(config.endpoint, {
                method: 'POST', keepalive: true,
                headers: { 'Content-Type': 'application/json' },
                body: payload,
            }).catch(function () {});
        } catch (e) {}
    }

    // ── Envoi ─────────────────────────────────────────────────────────────────

    function sendPost(data) {
        try {
            return fetch(config.endpoint, {
                method: 'POST', keepalive: true,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            }).catch(function () {});
        } catch (e) {
            return Promise.resolve();
        }
    }

    // ── Référent de session ───────────────────────────────────────────────────
    //
    // Problème : document.referrer n'est disponible que sur la première page vue.
    // Pour les pages suivantes (navigation interne), il vaut l'URL de la page WP
    // précédente — ce qui polluerait referrer_domain avec notre propre domaine.
    //
    // Solution :
    //  1. Sur la page d'entrée (referrer externe ou vide), on stocke la valeur
    //     dans sessionStorage sous la clé 'aa_entry_referrer'.
    //  2. Sur toutes les pages suivantes, on renvoie ce referrer d'entrée persisté
    //     au lieu de document.referrer.
    //  3. Un referrer est considéré interne si son hostname correspond à celui
    //     du site (config.siteUrl). Dans ce cas on stocke '' (direct).

    var _REFERRER_KEY = 'aa_entry_referrer';

    function _hostnameOf(url) {
        if (!url) return '';
        try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return ''; }
    }

    function getEntryReferrer() {
        try {
            var stored = sessionStorage.getItem(_REFERRER_KEY);
            if (stored !== null) return stored; // peut être '' (direct)

            var raw      = document.referrer || '';
            var siteHost = _hostnameOf((config.siteUrl || window.location.origin));
            var refHost  = _hostnameOf(raw);

            // Referrer interne => on considere l'entree comme directe
            var entry = (refHost && refHost === siteHost) ? '' : raw;
            sessionStorage.setItem(_REFERRER_KEY, entry);
            return entry;
        } catch (e) {
            // sessionStorage indisponible (navigation privee stricte) :
            // fallback avec filtrage interne a la volee.
            var raw      = document.referrer || '';
            var siteHost = _hostnameOf((config.siteUrl || window.location.origin));
            var refHost  = _hostnameOf(raw);
            return (refHost && refHost === siteHost) ? '' : raw;
        }
    }

    // ── Session ID ────────────────────────────────────────────────────────────

    function getSessionId() {
        var KEY = 'aa_sid';
        var sid;
        try {
            sid = sessionStorage.getItem(KEY);
            if (!sid) { sid = genId(); sessionStorage.setItem(KEY, sid); }
        } catch (e) {
            if (!window._aaSidFallback) window._aaSidFallback = genId();
            sid = window._aaSidFallback;
        }
        return sid;
    }

    // ── Visitor ID ────────────────────────────────────────────────────────────

    // Retourne le visitorId persisté en cookie, ou null si les cookies sont bloqués.
    // Si null, le hit est envoyé sans visitorId → le serveur applique le fallback
    // cookieless (hash journalier IP+UA) pour ne perdre aucune visite.
    function getOrCreateVisitorId() {
        var vid = getCookie(COOKIE_VID);
        if (vid) return vid;

        // Tenter de créer le cookie et vérifier qu'il a bien été persisté.
        var candidate = genId();
        setVisitorCookie(candidate);
        var persisted = getCookie(COOKIE_VID);

        if (persisted) {
            // Cookie accepté par le navigateur — visitorId stable.
            return persisted;
        }

        // Cookie bloqué (ITP, mode privé, bloqueur) — on retourne null.
        // Le hit sera envoyé sans visitorId, le serveur basculera en cookieless.
        return null;
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

    function deleteVisitorCookie() {
        var c = COOKIE_VID + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
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
