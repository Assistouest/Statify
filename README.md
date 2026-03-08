<div align="center">

# ⚡ Statify

**Plugin WordPress d'analytics auto-hébergé, léger et respectueux de la vie privée.**

[![Version](https://img.shields.io/badge/version-1.2.3-1db954?style=flat-square)](https://github.com/votre-pseudo/statify/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net)
[![Licence](https://img.shields.io/badge/licence-GPLv2%2B-blue?style=flat-square)](LICENSE)
[![RGPD](https://img.shields.io/badge/RGPD-conforme-1db954?style=flat-square)](https://github.com/votre-pseudo/statify#privacy)

Remplacez Google Analytics par une solution auto-hébergée, vos données restent sur votre serveur.

</div>

---

## L'algorithme de collecte résiliente

La plupart des analytics conditionnent le tracking au consentement : pas de cookie accepté = visite perdue. Statify fonctionne à l'envers.

> **Le cookieless est le socle. Le cookie est un enrichissement optionnel.**

Peu importe ce que fait le visiteur — accepter, refuser, fermer la bannière, bloquer les cookies, désactiver JS — une visite est toujours enregistrée. Le consentement ne décide pas si la visite est comptée. Il décide uniquement si le visiteur peut être reconnu lors de sa prochaine visite.

---

## Les trois modes

### Mode 1 — Cookieless

Aucun cookie. Aucune bannière. Démarre immédiatement.

Chaque visiteur est identifié par un hash journalier calculé côté serveur :

```
SHA-256( IP_anonymisée + User-Agent + Accept-Language + date_UTC )
```

Le hash change chaque nuit à minuit UTC. Le même visiteur produit un hash identique toute la journée, et un hash différent le lendemain. Il n'est jamais persisté sur l'appareil du visiteur.

**Données collectées :** URL, titre, Post ID, référent, UTM, device/navigateur/OS, résolution, géolocalisation pays/région, profondeur de scroll (paliers 25/50/75/100%), durée de session, temps d'engagement (page visible uniquement), sessions multi-pages, nouveau visiteur du jour, utilisateur WordPress connecté.

**Limite :** la rétention inter-jours est impossible par nature — le hash change chaque jour.

---

### Mode 2 — Cookie + Bannière RGPD

C'est le Mode 1 augmenté. Le cookieless tourne en permanence en dessous. La bannière et le cookie viennent s'ajouter par-dessus.

#### Le flux complet

```
Visiteur arrive
       │
       ▼
[Hit pre_consent envoyé immédiatement]
Hash journalier cookieless. Scroll, durée, engagement collectés.
Aucun cookie posé.
       │
       ▼
[Bannière affichée]
       │
       ├──── Accepte ──────────────────────────────────────────────────────┐
       │                                                                   │
       ├──── Refuse ────────────────────────────────────────────┐          │
       │                                                        │          │
       └──── Ferme sans répondre ───────────────────────────┐   │          │
                                                            │   │          │
                                                            ▼   ▼          │
                                              Hit pre_consent conservé     │
                                              = visite cookieless complète │
                                                                           ▼
                                                            Cookie visitorId créé (182j)
                                                            Hit complet envoyé
                                                            Pre_consent fusionné (is_superseded=1)
                                                            Identité persistante activée
```

#### Les trois issues

| Décision | Cookie posé | Hash utilisé | Rétention inter-jours | Données perdues |
|---|---|---|---|---|
| Acceptation | Oui — 182j | UUID permanent | ✓ Oui | Aucune |
| Refus | Non | Journalier cookieless | ✗ Non | Aucune |
| Fermeture sans réponse | Non | Journalier cookieless + sendBeacon | ✗ Non | Aucune |

#### Cas particulier — cookie bloqué (v1.2.2)

Même si le visiteur accepte, son navigateur peut bloquer le cookie (mode navigation privée, ITP Safari, extension bloqueur). Avant v1.2.2, ce cas produisait un UUID fantôme inutilisable.

Depuis v1.2.2, le JS détecte activement le blocage :

```javascript
// Écriture du cookie
setVisitorCookie(candidate);

// Vérification immédiate de persistance
const persisted = getCookie(COOKIE_VID);

if (persisted) {
    return persisted;       // Cookie ok → visitorId stable
}
return null;                // Cookie bloqué → fallback cookieless
```

Si `null` est retourné, le hit est envoyé sans `visitorId`. Le serveur détecte l'absence et bascule automatiquement sur le hash journalier — exactement comme le Mode 1.

---

### Mode 3 — Cookie sans bannière (debug)

Cookie visitorId posé immédiatement, durée 395 jours. Tracking complet dès le premier chargement. Identité persistante garantie. À utiliser en environnement de développement uniquement.
