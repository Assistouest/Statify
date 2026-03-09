<div align="center">

<img src="Statify.svg" alt="Statify Logo" width="60" />

# Statify
### Reprenez le contrôle de vos analytics. Sans compromis.

**La solution d'analytics WordPress auto-hébergée qui capture 100% de vos visites, tout en respectant scrupuleusement le RGPD.**

[![Version](https://img.shields.io/badge/version-1.2.3-1db954?style=flat-square)](https://github.com/votre-pseudo/statify/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net)
[![Privacy](https://img.shields.io/badge/RGPD-conforme-1db954?style=flat-square)](#-la-révolution-privacy-first)

[Fonctionnalités](#-pourquoi-statify-) • [Comment ça marche](#lalgorithme-de-collecte-résiliente) • [Détails Techniques](#les-trois-modes)

</div>

---

## 🚀 Pourquoi Statify ?

- **0% de perte de données** : Capture chaque visite, même sans consentement.
- **100% Souverain** : Vos données restent chez vous, sur votre serveur.
- **Vitesse Éclair** : Script ultra-léger sans impact sur le SEO.
- **Conformité RGPD Native** : Anonymisation et respect de la vie privée par design.

---

## 🎯 Score d'Engagement : La Data Science au service du contenu

Statify ne se contente pas de compter les vues. Il **qualifie la lecture** grâce à un algorithme de scoring composite sophistiqué qui identifie vos contenus les plus performants.

### Visualisez la performance réelle de vos pages

| Page | Score | 🕒 Durée | ⬇ Scroll | ✅ Engag. | 🔁 Retour | 📄 Profond. | 📊 Sessions |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 🥇 **Mettre à jour Ubuntu...** | **53** | 14m 54s | 100% | 100% | 0% | 3 p. | 1 sess. |
| 🥈 **Enable automatic updates...** | **47** | 14m 54s | 100% | 100% | 0% | — | 1 sess. |
| 🥉 **How to fully update...** | **47** | -- | -- | -- | -- | -- | -- |

### Une approche mathématique de haut niveau
Le score (0 à 100) est une fusion intelligente de 6 signaux pondérés, traitée pour éliminer le "bruit" et les faux positifs :

- **Loi de Wilson (Lower Bound)** : Le taux d'engagement est corrigé statistiquement pour éviter que les pages à faible volume ne faussent vos analyses.
- **Normalisation par Médiane Relative** : La durée de lecture est comparée à la performance réelle de *votre* site, s'adaptant automatiquement à votre style éditorial.
- **Algorithme de Pondération** : Durée (22%) • Scroll (20%) • Engagement (20%) • Fidélité/Retour (18%) • Profondeur de navigation (12%) • Confiance statistique (8%).

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

#### Cas particulier, le cookie bloqué

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

---

<div align="center">
  <p>Fait avec ❤️ par Adrien pour la communauté WordPress.</p>
  <a href="https://buymeacoffee.com/assistouest" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;" ></a>
</div>
