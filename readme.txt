=== Always Analytics ===
Contributors: adrien
Tags: analytics, statistics, tracking, privacy, GDPR
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Statistiques avancées auto-hébergées, légères et respectueuses de la vie privée pour WordPress.

== Description ==

Always Analytics est un plugin de statistiques complet pour WordPress qui remplace Google Analytics ou Matomo. Toutes les données restent sur votre serveur.

**Fonctionnalités principales :**

* Visiteurs uniques, pages vues, sessions et durée moyenne
* Top pages, référents, pays et appareils
* Détection des navigateurs et systèmes d'exploitation
* Mode sans cookie (respectueux de la vie privée) ou mode cookie (meilleur suivi)
* Bannière de consentement RGPD intégrée
* Géolocalisation native ou via MaxMind GeoLite2
* Dashboard interactif avec graphiques Chart.js
* Export CSV / JSON
* Filtrage automatique des bots
* Widget sur le tableau de bord WordPress
* Hooks et filtres pour les développeurs

== Installation ==

1. Téléchargez et installez le plugin via le menu Extensions
2. Activez le plugin
3. Les tables de base de données se créent automatiquement
4. Configurez les options dans *Statistiques → Réglages*
5. Les statistiques se collectent automatiquement dès l'activation

== Frequently Asked Questions ==

= Le plugin utilise-t-il des cookies ? =
Par défaut, non. Le mode "sans cookie" identifie les visiteurs via un hash éphémère journalier. Si vous activez le mode "cookie" pour un meilleur suivi, une bannière de consentement est disponible.

= Où sont stockées les données ? =
Toutes les données sont stockées dans votre base de données WordPress, dans 3 tables dédiées. Aucune donnée n'est envoyée à un service tiers.

= Le plugin ralentit-il mon site ? =
Le script de tracking fait moins de 5 Ko et utilise `sendBeacon` pour un envoi non-bloquant. L'impact sur les performances est minimal.

== Bibliothèques tierces ==

Ce plugin inclut les bibliothèques open source suivantes, servies localement (aucune requête externe) :

= flag-icons =
* Auteur : Panayiotis Lipiridis
* Source : https://github.com/lipis/flag-icons
* Licence : MIT — https://github.com/lipis/flag-icons/blob/main/LICENSE
* Usage : Icônes de drapeaux SVG affichées dans le tableau de bord (statistiques par pays). Les fichiers SVG sont embarqués dans le plugin sous `/assets/flags/` et servis depuis votre propre serveur.

= Chart.js =
* Auteur : Chart.js Contributors
* Source : https://github.com/chartjs/Chart.js
* Licence : MIT — https://github.com/chartjs/Chart.js/blob/master/LICENSE.md
* Usage : Rendu des graphiques dans le tableau de bord.

== Changelog ==

= 1.0.0 =
* Version initiale
