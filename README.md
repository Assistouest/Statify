<div align="center">

<img src="always-analytics.svg" alt="Statify Logo" width="60" />

# Always Analytics
### Reprenez le contrôle de vos analytics. Sans compromis.

**La solution d'analytics WordPress auto-hébergée qui capture 100% de vos visites, tout en respectant scrupuleusement le RGPD.**

[![Version](https://img.shields.io/badge/version-2.1.0-1db954?style=flat-square)](https://github.com/votre-pseudo/statify/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net)
[![Privacy](https://img.shields.io/badge/RGPD-conforme-1db954?style=flat-square)](#-la-révolution-privacy-first)

</div>

---

- **0% de perte de données** : Capture chaque visite, même sans consentement.
- **100% Souverain** : Vos données restent chez vous, sur votre serveur.
- **Vitesse Éclair** : Script ultra-léger sans impact sur le SEO.
- **Conformité RGPD Native** : Anonymisation et respect de la vie privée par design.

Contrairement à Yoast SEO ou Google Analytics, Always Analytics traite chaque vue avec une intelligence contextuelle.

Pour éviter l'anomalie des petits nombres, nous appliquons la limite inférieure de l'intervalle de confiance de Wilson :

Une page avec 1 vue et 100% d'engagement ne passera jamais devant un pilier de votre site affichant 1000 vues et 80% d'engagement. La stabilité statistique prime sur le pourcentage.

| Page | Score | 🕒 Durée | ⬇ Scroll | ✅ Engag. | 🔁 Retour | 📄 Profond. | 📊 Sessions |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 🥇 **Mettre à jour Ubuntu...** | **53** | 14m 54s | 100% | 100% | 0% | 3 p. | 1 sess. |
| 🥈 **Enable automatic updates...** | **47** | 14m 54s | 100% | 100% | 0% | — | 1 sess. |
| 🥉 **How to fully update...** | **47** | -- | -- | -- | -- | -- | -- |


Le score est calculé relativement à votre site. Always Analytics détermine la médiane  de vos contenus pour définir ce qu’est une lecture longue. Le score final est une synthèse de Durée (22%) • Scroll (20%) • Engagement (20%) • Fidélité (18%) • Profondeur (12%) • Confiance statistique (8%).

---

## L'algorithme de collecte résiliente

La plupart des analytics conditionnent le tracking au consentement : pas de cookie accepté = visite perdue. Always Analytics fonctionne à l'envers.

> **Le suivi sans cookie constitue la base, le cookie n’intervient qu’en option.**

Peu importe ce que fait le visiteur (accepter, refuser, fermer la bannière, bloquer les cookies, désactiver JS) une visite est toujours enregistrée. Le consentement ne décide pas si la visite est comptée. Il décide uniquement si le visiteur peut être reconnu lors de sa prochaine visite.

---

## Les trois modes

### Mode 1. Cookieless

Aucun cookie. Aucune bannière. Démarre immédiatement.

Chaque visiteur est identifié par un hash journalier calculé côté serveur :

```
SHA256(IP_anon + UA + Accept-Language + YYYY-MM + site_salt)
```

Le hash change chaque mois. Le même visiteur produit un hash identique toute le mois, et un hash différent le mois suivant. Il n'est jamais persisté sur l'appareil du visiteur plus de 31 jours.

## Données collectées

- URL de la page
- Titre de la page
- Post ID (identifiant du contenu)
- Référent (source de la visite)
- Paramètres UTM
- Type d’appareil
- Navigateur
- Système d’exploitation
- Résolution d’écran
- Géolocalisation (pays / région)
- Profondeur de scroll (paliers : 25 %, 50 %, 75 %, 100 %)
- Durée de session
- Temps d’engagement (uniquement lorsque la page est visible)
- Sessions multi-pages
- Indicateur de nouveau visiteur du jour
- Utilisateur WordPress connecté (si applicable)

**Limite :** la rétention inter-jours est impossible par nature, le hash change chaque jour.

---

### Mode 2. Cookie + Bannière RGPD

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

### Mode 3. Cookie sans bannière (pour les développeurs)

Cookie visitorId posé immédiatement, durée 395 jours. Tracking complet dès le premier chargement. Identité persistante garantie. À utiliser en environnement de développement uniquement.

---

<div align="center">
  <p>Fait avec ❤️ par Adrien pour la communauté WordPress.</p>
  <a href="https://buymeacoffee.com/assistouest" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;" ></a>
</div>
