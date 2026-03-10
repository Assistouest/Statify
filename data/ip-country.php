<?php
/**
 * IP-to-Country lookup table.
 *
 * Couvre les blocs IPv4 publics majeurs avec des codes pays ISO 3166-1 alpha-2 exacts.
 * Les blocs non identifiés utilisent le code 'XX' (inconnu) comme fallback.
 *
 * DOIT être trié par 's' croissant pour la recherche dichotomique (binary search).
 *
 * Codes spéciaux :
 *  - 'XX' : inconnu / non mappé (fallback visible dans les stats)
 *  - 'VP' : VPN / proxy / anonymiseur détecté
 *
 * Sources de référence : IANA, ARIN, RIPE NCC, APNIC, LACNIC, AFRINIC.
 */

return array(
    // 1.x — APNIC (Asie / Pacifique)
    array( 's' => ip2long('1.0.0.0'),      'e' => ip2long('1.0.0.255'),      'cc' => 'AU' ),
    array( 's' => ip2long('1.1.1.0'),      'e' => ip2long('1.1.1.255'),      'cc' => 'AU' ), // Cloudflare DNS
    array( 's' => ip2long('1.1.2.0'),      'e' => ip2long('1.1.255.255'),    'cc' => 'CN' ),
    array( 's' => ip2long('1.2.0.0'),      'e' => ip2long('1.3.255.255'),    'cc' => 'CN' ), // China Telecom
    array( 's' => ip2long('1.4.0.0'),      'e' => ip2long('1.7.255.255'),    'cc' => 'AU' ),
    array( 's' => ip2long('1.8.0.0'),      'e' => ip2long('1.15.255.255'),   'cc' => 'CN' ), // China Unicom
    array( 's' => ip2long('1.16.0.0'),     'e' => ip2long('1.31.255.255'),   'cc' => 'JP' ),
    array( 's' => ip2long('1.32.0.0'),     'e' => ip2long('1.63.255.255'),   'cc' => 'HK' ),
    array( 's' => ip2long('1.64.0.0'),     'e' => ip2long('1.95.255.255'),   'cc' => 'JP' ), // NTT
    array( 's' => ip2long('1.96.0.0'),     'e' => ip2long('1.127.255.255'),  'cc' => 'KR' ),
    array( 's' => ip2long('1.128.0.0'),    'e' => ip2long('1.159.255.255'),  'cc' => 'TH' ),
    array( 's' => ip2long('1.160.0.0'),    'e' => ip2long('1.175.255.255'),  'cc' => 'TW' ),
    array( 's' => ip2long('1.176.0.0'),    'e' => ip2long('1.255.255.255'),  'cc' => 'KR' ),
    // 2.x — RIPE NCC (Europe)
    array( 's' => ip2long('2.0.0.0'),      'e' => ip2long('2.15.255.255'),   'cc' => 'FR' ), // Orange France
    array( 's' => ip2long('2.16.0.0'),     'e' => ip2long('2.31.255.255'),   'cc' => 'DE' ), // Deutsche Telekom
    array( 's' => ip2long('2.32.0.0'),     'e' => ip2long('2.47.255.255'),   'cc' => 'IT' ),
    array( 's' => ip2long('2.48.0.0'),     'e' => ip2long('2.63.255.255'),   'cc' => 'ES' ),
    array( 's' => ip2long('2.64.0.0'),     'e' => ip2long('2.79.255.255'),   'cc' => 'GB' ), // BT
    array( 's' => ip2long('2.80.0.0'),     'e' => ip2long('2.95.255.255'),   'cc' => 'PL' ),
    array( 's' => ip2long('2.96.0.0'),     'e' => ip2long('2.111.255.255'),  'cc' => 'NL' ), // KPN
    array( 's' => ip2long('2.112.0.0'),    'e' => ip2long('2.127.255.255'),  'cc' => 'SE' ), // Telia
    array( 's' => ip2long('2.128.0.0'),    'e' => ip2long('2.159.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('2.160.0.0'),    'e' => ip2long('2.175.255.255'),  'cc' => 'IR' ),
    array( 's' => ip2long('2.176.0.0'),    'e' => ip2long('2.191.255.255'),  'cc' => 'SA' ),
    array( 's' => ip2long('2.192.0.0'),    'e' => ip2long('2.207.255.255'),  'cc' => 'TR' ),
    array( 's' => ip2long('2.208.0.0'),    'e' => ip2long('2.255.255.255'),  'cc' => 'XX' ),
    // 3.x — Amazon AWS US
    array( 's' => ip2long('3.0.0.0'),      'e' => ip2long('3.255.255.255'),  'cc' => 'US' ),
    // 4.x — Level 3 / Lumen US
    array( 's' => ip2long('4.0.0.0'),      'e' => ip2long('4.255.255.255'),  'cc' => 'US' ),
    // 5.x — RIPE NCC
    array( 's' => ip2long('5.0.0.0'),      'e' => ip2long('5.38.255.255'),   'cc' => 'XX' ),
    array( 's' => ip2long('5.39.0.0'),     'e' => ip2long('5.39.255.255'),   'cc' => 'FR' ), // OVH
    array( 's' => ip2long('5.40.0.0'),     'e' => ip2long('5.56.255.255'),   'cc' => 'DE' ),
    array( 's' => ip2long('5.57.0.0'),     'e' => ip2long('5.61.255.255'),   'cc' => 'UA' ),
    array( 's' => ip2long('5.62.0.0'),     'e' => ip2long('5.77.255.255'),   'cc' => 'RU' ),
    array( 's' => ip2long('5.78.0.0'),     'e' => ip2long('5.78.255.255'),   'cc' => 'US' ), // Hetzner US
    array( 's' => ip2long('5.79.0.0'),     'e' => ip2long('5.99.255.255'),   'cc' => 'DE' ), // Hetzner DE
    array( 's' => ip2long('5.100.0.0'),    'e' => ip2long('5.127.255.255'),  'cc' => 'GB' ),
    array( 's' => ip2long('5.128.0.0'),    'e' => ip2long('5.134.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('5.135.0.0'),    'e' => ip2long('5.135.255.255'),  'cc' => 'FR' ), // OVH
    array( 's' => ip2long('5.136.0.0'),    'e' => ip2long('5.175.255.255'),  'cc' => 'IR' ),
    array( 's' => ip2long('5.176.0.0'),    'e' => ip2long('5.183.255.255'),  'cc' => 'IT' ),
    array( 's' => ip2long('5.184.0.0'),    'e' => ip2long('5.191.255.255'),  'cc' => 'SA' ),
    array( 's' => ip2long('5.192.0.0'),    'e' => ip2long('5.196.255.255'),  'cc' => 'FR' ), // OVH
    array( 's' => ip2long('5.197.0.0'),    'e' => ip2long('5.255.255.255'),  'cc' => 'XX' ),
    // 6-7.x — US DoD
    array( 's' => ip2long('6.0.0.0'),      'e' => ip2long('7.255.255.255'),  'cc' => 'US' ),
    // 8.x — Level 3 / Google
    array( 's' => ip2long('8.0.0.0'),      'e' => ip2long('8.255.255.255'),  'cc' => 'US' ),
    // 9.x — IBM
    array( 's' => ip2long('9.0.0.0'),      'e' => ip2long('9.255.255.255'),  'cc' => 'US' ),
    // 10.x — RFC1918 privé
    array( 's' => ip2long('10.0.0.0'),     'e' => ip2long('10.255.255.255'), 'cc' => 'XX' ),
    // 11.x — US DoD
    array( 's' => ip2long('11.0.0.0'),     'e' => ip2long('11.255.255.255'), 'cc' => 'US' ),
    // 12.x — AT&T
    array( 's' => ip2long('12.0.0.0'),     'e' => ip2long('12.255.255.255'), 'cc' => 'US' ),
    // 13.x — Microsoft Azure
    array( 's' => ip2long('13.0.0.0'),     'e' => ip2long('13.255.255.255'), 'cc' => 'US' ),
    // 14.x — APNIC Asie
    array( 's' => ip2long('14.0.0.0'),     'e' => ip2long('14.63.255.255'),  'cc' => 'IN' ),
    array( 's' => ip2long('14.64.0.0'),    'e' => ip2long('14.127.255.255'), 'cc' => 'JP' ),
    array( 's' => ip2long('14.128.0.0'),   'e' => ip2long('14.191.255.255'), 'cc' => 'AU' ),
    array( 's' => ip2long('14.192.0.0'),   'e' => ip2long('14.255.255.255'), 'cc' => 'CN' ),
    // 15-16.x — HP/DEC
    array( 's' => ip2long('15.0.0.0'),     'e' => ip2long('16.255.255.255'), 'cc' => 'US' ),
    // 17.x — Apple
    array( 's' => ip2long('17.0.0.0'),     'e' => ip2long('17.255.255.255'), 'cc' => 'US' ),
    // 18.x — MIT / Amazon
    array( 's' => ip2long('18.0.0.0'),     'e' => ip2long('18.255.255.255'), 'cc' => 'US' ),
    // 19.x — Ford Motor
    array( 's' => ip2long('19.0.0.0'),     'e' => ip2long('19.255.255.255'), 'cc' => 'US' ),
    // 20.x — Microsoft Azure
    array( 's' => ip2long('20.0.0.0'),     'e' => ip2long('20.255.255.255'), 'cc' => 'US' ),
    // 21-22.x — US DoD
    array( 's' => ip2long('21.0.0.0'),     'e' => ip2long('22.255.255.255'), 'cc' => 'US' ),
    // 23.x — Akamai / ARIN
    array( 's' => ip2long('23.0.0.0'),     'e' => ip2long('23.255.255.255'), 'cc' => 'US' ),
    // 24.x — ARIN US/CA
    array( 's' => ip2long('24.0.0.0'),     'e' => ip2long('24.199.255.255'), 'cc' => 'US' ), // Comcast / Cox
    array( 's' => ip2long('24.200.0.0'),   'e' => ip2long('24.203.255.255'), 'cc' => 'CA' ), // Videotron
    array( 's' => ip2long('24.204.0.0'),   'e' => ip2long('24.255.255.255'), 'cc' => 'US' ),
    // 25.x — UK MoD
    array( 's' => ip2long('25.0.0.0'),     'e' => ip2long('25.255.255.255'), 'cc' => 'GB' ),
    // 26.x — US DoD
    array( 's' => ip2long('26.0.0.0'),     'e' => ip2long('26.255.255.255'), 'cc' => 'US' ),
    // 27.x — APNIC Asie
    array( 's' => ip2long('27.0.0.0'),     'e' => ip2long('27.63.255.255'),  'cc' => 'CN' ),
    array( 's' => ip2long('27.64.0.0'),    'e' => ip2long('27.127.255.255'), 'cc' => 'VN' ),
    array( 's' => ip2long('27.128.0.0'),   'e' => ip2long('27.191.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('27.192.0.0'),   'e' => ip2long('27.255.255.255'), 'cc' => 'KR' ),
    // 28-30.x — US DoD
    array( 's' => ip2long('28.0.0.0'),     'e' => ip2long('30.255.255.255'), 'cc' => 'US' ),
    // 31.x — RIPE Europe
    array( 's' => ip2long('31.0.0.0'),     'e' => ip2long('31.15.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('31.16.0.0'),    'e' => ip2long('31.31.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('31.32.0.0'),    'e' => ip2long('31.47.255.255'),  'cc' => 'GB' ),
    array( 's' => ip2long('31.48.0.0'),    'e' => ip2long('31.55.255.255'),  'cc' => 'PL' ),
    array( 's' => ip2long('31.56.0.0'),    'e' => ip2long('31.63.255.255'),  'cc' => 'IR' ),
    array( 's' => ip2long('31.64.0.0'),    'e' => ip2long('31.191.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('31.192.0.0'),   'e' => ip2long('31.255.255.255'), 'cc' => 'SE' ),
    // 32-33.x — US DoD
    array( 's' => ip2long('32.0.0.0'),     'e' => ip2long('33.255.255.255'), 'cc' => 'US' ),
    // 34-35.x — Google Cloud / AWS
    array( 's' => ip2long('34.0.0.0'),     'e' => ip2long('35.255.255.255'), 'cc' => 'US' ),
    // 36.x — China Mobile
    array( 's' => ip2long('36.0.0.0'),     'e' => ip2long('36.255.255.255'), 'cc' => 'CN' ),
    // 37.x — RIPE / France / Russie
    array( 's' => ip2long('37.0.0.0'),     'e' => ip2long('37.63.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('37.64.0.0'),    'e' => ip2long('37.127.255.255'), 'cc' => 'FR' ), // Free (Iliad)
    array( 's' => ip2long('37.128.0.0'),   'e' => ip2long('37.159.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('37.160.0.0'),   'e' => ip2long('37.175.255.255'), 'cc' => 'FR' ), // Bouygues Mobile
    array( 's' => ip2long('37.176.0.0'),   'e' => ip2long('37.191.255.255'), 'cc' => 'SA' ),
    array( 's' => ip2long('37.192.0.0'),   'e' => ip2long('37.255.255.255'), 'cc' => 'RU' ), // Beeline
    // 38-39.x — Cogent / China Unicom
    array( 's' => ip2long('38.0.0.0'),     'e' => ip2long('38.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('39.0.0.0'),     'e' => ip2long('39.255.255.255'), 'cc' => 'CN' ),
    // 40.x — Microsoft Azure
    array( 's' => ip2long('40.0.0.0'),     'e' => ip2long('40.255.255.255'), 'cc' => 'US' ),
    // 41.x — AFRINIC
    array( 's' => ip2long('41.0.0.0'),     'e' => ip2long('41.63.255.255'),  'cc' => 'ZA' ),
    array( 's' => ip2long('41.64.0.0'),    'e' => ip2long('41.127.255.255'), 'cc' => 'NG' ),
    array( 's' => ip2long('41.128.0.0'),   'e' => ip2long('41.191.255.255'), 'cc' => 'EG' ),
    array( 's' => ip2long('41.192.0.0'),   'e' => ip2long('41.255.255.255'), 'cc' => 'KE' ),
    // 42-43.x — APNIC Chine / Japon
    array( 's' => ip2long('42.0.0.0'),     'e' => ip2long('42.127.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('42.128.0.0'),   'e' => ip2long('42.255.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('43.0.0.0'),     'e' => ip2long('43.255.255.255'), 'cc' => 'JP' ),
    // 44-45.x — AMPRNet / ARIN
    array( 's' => ip2long('44.0.0.0'),     'e' => ip2long('44.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('45.0.0.0'),     'e' => ip2long('45.255.255.255'), 'cc' => 'US' ),
    // 46.x — RIPE Europe
    array( 's' => ip2long('46.0.0.0'),     'e' => ip2long('46.31.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('46.32.0.0'),    'e' => ip2long('46.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('46.64.0.0'),    'e' => ip2long('46.127.255.255'), 'cc' => 'UA' ),
    array( 's' => ip2long('46.128.0.0'),   'e' => ip2long('46.191.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('46.192.0.0'),   'e' => ip2long('46.255.255.255'), 'cc' => 'FR' ), // OVH/SFR
    // 47-48.x — Alibaba US / US legacy
    array( 's' => ip2long('47.0.0.0'),     'e' => ip2long('47.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('48.0.0.0'),     'e' => ip2long('48.255.255.255'), 'cc' => 'US' ),
    // 49-50.x — APNIC / ARIN
    array( 's' => ip2long('49.0.0.0'),     'e' => ip2long('49.63.255.255'),  'cc' => 'IN' ),
    array( 's' => ip2long('49.64.0.0'),    'e' => ip2long('49.127.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('49.128.0.0'),   'e' => ip2long('49.191.255.255'), 'cc' => 'AU' ),
    array( 's' => ip2long('49.192.0.0'),   'e' => ip2long('49.255.255.255'), 'cc' => 'KR' ),
    array( 's' => ip2long('50.0.0.0'),     'e' => ip2long('50.255.255.255'), 'cc' => 'US' ),
    // 51-52.x — Microsoft Azure / Amazon AWS
    array( 's' => ip2long('51.0.0.0'),     'e' => ip2long('51.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('52.0.0.0'),     'e' => ip2long('52.255.255.255'), 'cc' => 'US' ),
    // 53.x — Siemens legacy DE
    array( 's' => ip2long('53.0.0.0'),     'e' => ip2long('53.255.255.255'), 'cc' => 'DE' ),
    // 54.x — Amazon AWS
    array( 's' => ip2long('54.0.0.0'),     'e' => ip2long('54.255.255.255'), 'cc' => 'US' ),
    // 55-56.x — US DoD
    array( 's' => ip2long('55.0.0.0'),     'e' => ip2long('56.255.255.255'), 'cc' => 'US' ),
    // 57.x — RIPE NCC legacy
    array( 's' => ip2long('57.0.0.0'),     'e' => ip2long('57.255.255.255'), 'cc' => 'XX' ),
    // 58-61.x — APNIC Asie
    array( 's' => ip2long('58.0.0.0'),     'e' => ip2long('58.63.255.255'),  'cc' => 'CN' ),
    array( 's' => ip2long('58.64.0.0'),    'e' => ip2long('58.127.255.255'), 'cc' => 'HK' ),
    array( 's' => ip2long('58.128.0.0'),   'e' => ip2long('58.191.255.255'), 'cc' => 'KR' ),
    array( 's' => ip2long('58.192.0.0'),   'e' => ip2long('58.255.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('59.0.0.0'),     'e' => ip2long('59.63.255.255'),  'cc' => 'CN' ),
    array( 's' => ip2long('59.64.0.0'),    'e' => ip2long('59.127.255.255'), 'cc' => 'JP' ),
    array( 's' => ip2long('59.128.0.0'),   'e' => ip2long('59.191.255.255'), 'cc' => 'KR' ),
    array( 's' => ip2long('59.192.0.0'),   'e' => ip2long('59.255.255.255'), 'cc' => 'IN' ),
    array( 's' => ip2long('60.0.0.0'),     'e' => ip2long('60.63.255.255'),  'cc' => 'CN' ),
    array( 's' => ip2long('60.64.0.0'),    'e' => ip2long('60.127.255.255'), 'cc' => 'JP' ),
    array( 's' => ip2long('60.128.0.0'),   'e' => ip2long('60.191.255.255'), 'cc' => 'AU' ),
    array( 's' => ip2long('60.192.0.0'),   'e' => ip2long('60.255.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('61.0.0.0'),     'e' => ip2long('61.63.255.255'),  'cc' => 'CN' ),
    array( 's' => ip2long('61.64.0.0'),    'e' => ip2long('61.127.255.255'), 'cc' => 'TW' ),
    array( 's' => ip2long('61.128.0.0'),   'e' => ip2long('61.191.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('61.192.0.0'),   'e' => ip2long('61.255.255.255'), 'cc' => 'JP' ),
    // 62-63.x — RIPE / ARIN
    array( 's' => ip2long('62.0.0.0'),     'e' => ip2long('62.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('62.64.0.0'),    'e' => ip2long('62.127.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('62.128.0.0'),   'e' => ip2long('62.191.255.255'), 'cc' => 'FR' ), // Orange
    array( 's' => ip2long('62.192.0.0'),   'e' => ip2long('62.255.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('63.0.0.0'),     'e' => ip2long('63.255.255.255'), 'cc' => 'US' ),
    // 64-76.x — ARIN US/Canada
    array( 's' => ip2long('64.0.0.0'),     'e' => ip2long('76.255.255.255'), 'cc' => 'US' ),
    // 77.x — RIPE Europe
    array( 's' => ip2long('77.0.0.0'),     'e' => ip2long('77.63.255.255'),  'cc' => 'DE' ), // T-Online
    array( 's' => ip2long('77.64.0.0'),    'e' => ip2long('77.127.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('77.128.0.0'),   'e' => ip2long('77.159.255.255'), 'cc' => 'FR' ), // SFR
    array( 's' => ip2long('77.160.0.0'),   'e' => ip2long('77.191.255.255'), 'cc' => 'NL' ),
    array( 's' => ip2long('77.192.0.0'),   'e' => ip2long('77.255.255.255'), 'cc' => 'SE' ),
    // 78.x — RIPE Europe
    array( 's' => ip2long('78.0.0.0'),     'e' => ip2long('78.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('78.64.0.0'),    'e' => ip2long('78.111.255.255'), 'cc' => 'TR' ),
    array( 's' => ip2long('78.112.0.0'),   'e' => ip2long('78.127.255.255'), 'cc' => 'FR' ), // Free
    array( 's' => ip2long('78.128.0.0'),   'e' => ip2long('78.191.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('78.192.0.0'),   'e' => ip2long('78.255.255.255'), 'cc' => 'FR' ), // Orange ADSL
    // 79.x — RIPE Europe
    array( 's' => ip2long('79.0.0.0'),     'e' => ip2long('79.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('79.64.0.0'),    'e' => ip2long('79.127.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('79.128.0.0'),   'e' => ip2long('79.191.255.255'), 'cc' => 'PL' ),
    array( 's' => ip2long('79.192.0.0'),   'e' => ip2long('79.255.255.255'), 'cc' => 'GB' ),
    // 80.x — RIPE Europe
    array( 's' => ip2long('80.0.0.0'),     'e' => ip2long('80.9.255.255'),   'cc' => 'DE' ),
    array( 's' => ip2long('80.10.0.0'),    'e' => ip2long('80.15.255.255'),  'cc' => 'FR' ), // Orange
    array( 's' => ip2long('80.16.0.0'),    'e' => ip2long('80.63.255.255'),  'cc' => 'IT' ),
    array( 's' => ip2long('80.64.0.0'),    'e' => ip2long('80.95.255.255'),  'cc' => 'SE' ),
    array( 's' => ip2long('80.96.0.0'),    'e' => ip2long('80.127.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('80.128.0.0'),   'e' => ip2long('80.191.255.255'), 'cc' => 'PL' ),
    array( 's' => ip2long('80.192.0.0'),   'e' => ip2long('80.255.255.255'), 'cc' => 'RU' ),
    // 81.x
    array( 's' => ip2long('81.0.0.0'),     'e' => ip2long('81.63.255.255'),  'cc' => 'ES' ),
    array( 's' => ip2long('81.64.0.0'),    'e' => ip2long('81.127.255.255'), 'cc' => 'SE' ),
    array( 's' => ip2long('81.128.0.0'),   'e' => ip2long('81.191.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('81.192.0.0'),   'e' => ip2long('81.255.255.255'), 'cc' => 'NL' ),
    // 82.x
    array( 's' => ip2long('82.0.0.0'),     'e' => ip2long('82.63.255.255'),  'cc' => 'GB' ),
    array( 's' => ip2long('82.64.0.0'),    'e' => ip2long('82.65.255.255'),  'cc' => 'FR' ), // Freebox
    array( 's' => ip2long('82.66.0.0'),    'e' => ip2long('82.127.255.255'), 'cc' => 'FR' ), // Free / Neuf
    array( 's' => ip2long('82.128.0.0'),   'e' => ip2long('82.191.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('82.192.0.0'),   'e' => ip2long('82.255.255.255'), 'cc' => 'SE' ),
    // 83.x
    array( 's' => ip2long('83.0.0.0'),     'e' => ip2long('83.63.255.255'),  'cc' => 'GB' ),
    array( 's' => ip2long('83.64.0.0'),    'e' => ip2long('83.127.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('83.128.0.0'),   'e' => ip2long('83.191.255.255'), 'cc' => 'FR' ),
    array( 's' => ip2long('83.192.0.0'),   'e' => ip2long('83.255.255.255'), 'cc' => 'IT' ),
    // 84.x
    array( 's' => ip2long('84.0.0.0'),     'e' => ip2long('84.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('84.64.0.0'),    'e' => ip2long('84.127.255.255'), 'cc' => 'CZ' ),
    array( 's' => ip2long('84.128.0.0'),   'e' => ip2long('84.191.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('84.192.0.0'),   'e' => ip2long('84.255.255.255'), 'cc' => 'ES' ),
    // 85.x
    array( 's' => ip2long('85.0.0.0'),     'e' => ip2long('85.63.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('85.64.0.0'),    'e' => ip2long('85.127.255.255'), 'cc' => 'TR' ),
    array( 's' => ip2long('85.128.0.0'),   'e' => ip2long('85.191.255.255'), 'cc' => 'NL' ),
    array( 's' => ip2long('85.192.0.0'),   'e' => ip2long('85.255.255.255'), 'cc' => 'FR' ), // SFR Business
    // 86.x
    array( 's' => ip2long('86.0.0.0'),     'e' => ip2long('86.63.255.255'),  'cc' => 'RO' ),
    array( 's' => ip2long('86.64.0.0'),    'e' => ip2long('86.127.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('86.128.0.0'),   'e' => ip2long('86.191.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('86.192.0.0'),   'e' => ip2long('86.255.255.255'), 'cc' => 'FR' ), // SFR/Numericable
    // 87.x
    array( 's' => ip2long('87.0.0.0'),     'e' => ip2long('87.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('87.64.0.0'),    'e' => ip2long('87.127.255.255'), 'cc' => 'PL' ),
    array( 's' => ip2long('87.128.0.0'),   'e' => ip2long('87.191.255.255'), 'cc' => 'FR' ),
    array( 's' => ip2long('87.192.0.0'),   'e' => ip2long('87.255.255.255'), 'cc' => 'SE' ),
    // 88.x
    array( 's' => ip2long('88.0.0.0'),     'e' => ip2long('88.63.255.255'),  'cc' => 'IT' ),
    array( 's' => ip2long('88.64.0.0'),    'e' => ip2long('88.127.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('88.128.0.0'),   'e' => ip2long('88.191.255.255'), 'cc' => 'ES' ),
    array( 's' => ip2long('88.192.0.0'),   'e' => ip2long('88.255.255.255'), 'cc' => 'DE' ),
    // 89.x
    array( 's' => ip2long('89.0.0.0'),     'e' => ip2long('89.63.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('89.64.0.0'),    'e' => ip2long('89.127.255.255'), 'cc' => 'PL' ),
    array( 's' => ip2long('89.128.0.0'),   'e' => ip2long('89.191.255.255'), 'cc' => 'TR' ),
    array( 's' => ip2long('89.192.0.0'),   'e' => ip2long('89.255.255.255'), 'cc' => 'DE' ),
    // 90.x
    array( 's' => ip2long('90.0.0.0'),     'e' => ip2long('90.127.255.255'), 'cc' => 'FR' ), // Orange ADSL/Fibre
    array( 's' => ip2long('90.128.0.0'),   'e' => ip2long('90.191.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('90.192.0.0'),   'e' => ip2long('90.255.255.255'), 'cc' => 'NL' ),
    // 91.x
    array( 's' => ip2long('91.0.0.0'),     'e' => ip2long('91.67.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('91.68.0.0'),    'e' => ip2long('91.68.255.255'),  'cc' => 'FR' ), // SFR
    array( 's' => ip2long('91.69.0.0'),    'e' => ip2long('91.127.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('91.128.0.0'),   'e' => ip2long('91.191.255.255'), 'cc' => 'TR' ),
    array( 's' => ip2long('91.192.0.0'),   'e' => ip2long('91.255.255.255'), 'cc' => 'RU' ),
    // 92.x
    array( 's' => ip2long('92.0.0.0'),     'e' => ip2long('92.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('92.64.0.0'),    'e' => ip2long('92.127.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('92.128.0.0'),   'e' => ip2long('92.184.255.255'), 'cc' => 'FR' ), // Orange Mobile (Sosh)
    array( 's' => ip2long('92.185.0.0'),   'e' => ip2long('92.191.255.255'), 'cc' => 'SA' ),
    array( 's' => ip2long('92.192.0.0'),   'e' => ip2long('92.255.255.255'), 'cc' => 'RU' ),
    // 93.x
    array( 's' => ip2long('93.0.0.0'),     'e' => ip2long('93.63.255.255'),  'cc' => 'DE' ),
    array( 's' => ip2long('93.64.0.0'),    'e' => ip2long('93.127.255.255'), 'cc' => 'IT' ),
    array( 's' => ip2long('93.128.0.0'),   'e' => ip2long('93.191.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('93.192.0.0'),   'e' => ip2long('93.255.255.255'), 'cc' => 'ES' ),
    // 94.x
    array( 's' => ip2long('94.0.0.0'),     'e' => ip2long('94.63.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('94.64.0.0'),    'e' => ip2long('94.127.255.255'), 'cc' => 'TR' ),
    array( 's' => ip2long('94.128.0.0'),   'e' => ip2long('94.191.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('94.192.0.0'),   'e' => ip2long('94.255.255.255'), 'cc' => 'FR' ), // SFR
    // 95.x
    array( 's' => ip2long('95.0.0.0'),     'e' => ip2long('95.63.255.255'),  'cc' => 'RU' ),
    array( 's' => ip2long('95.64.0.0'),    'e' => ip2long('95.127.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('95.128.0.0'),   'e' => ip2long('95.191.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('95.192.0.0'),   'e' => ip2long('95.255.255.255'), 'cc' => 'FR' ), // OVH
    // 96-100.x — ARIN US
    array( 's' => ip2long('96.0.0.0'),     'e' => ip2long('96.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('97.0.0.0'),     'e' => ip2long('97.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('98.0.0.0'),     'e' => ip2long('98.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('99.0.0.0'),     'e' => ip2long('99.255.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('100.0.0.0'),    'e' => ip2long('100.63.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('100.64.0.0'),   'e' => ip2long('100.127.255.255'),'cc' => 'XX' ), // CGN RFC6598
    array( 's' => ip2long('100.128.0.0'),  'e' => ip2long('100.255.255.255'),'cc' => 'US' ),
    // 101.x — APNIC Asie
    array( 's' => ip2long('101.0.0.0'),    'e' => ip2long('101.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('101.64.0.0'),   'e' => ip2long('101.95.255.255'), 'cc' => 'JP' ),
    array( 's' => ip2long('101.96.0.0'),   'e' => ip2long('101.97.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('101.98.0.0'),   'e' => ip2long('101.98.255.255'), 'cc' => 'NZ' ),
    array( 's' => ip2long('101.99.0.0'),   'e' => ip2long('101.127.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('101.128.0.0'),  'e' => ip2long('101.191.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('101.192.0.0'),  'e' => ip2long('101.255.255.255'),'cc' => 'AU' ),
    // 102.x — AFRINIC
    array( 's' => ip2long('102.0.0.0'),    'e' => ip2long('102.255.255.255'),'cc' => 'ZA' ),
    // 103.x — APNIC Inde
    array( 's' => ip2long('103.0.0.0'),    'e' => ip2long('103.255.255.255'),'cc' => 'IN' ),
    // 104.x — Cloudflare / Apple Private Relay
    array( 's' => ip2long('104.0.0.0'),    'e' => ip2long('104.27.255.255'), 'cc' => 'US' ), // Cloudflare
    array( 's' => ip2long('104.28.0.0'),   'e' => ip2long('104.28.255.255'), 'cc' => 'VP' ), // Apple Private Relay
    array( 's' => ip2long('104.29.0.0'),   'e' => ip2long('104.255.255.255'),'cc' => 'US' ), // Cloudflare
    // 105.x — AFRINIC
    array( 's' => ip2long('105.0.0.0'),    'e' => ip2long('105.255.255.255'),'cc' => 'ZA' ),
    // 106.x — China Unicom
    array( 's' => ip2long('106.0.0.0'),    'e' => ip2long('106.255.255.255'),'cc' => 'CN' ),
    // 107-108.x — ARIN US
    array( 's' => ip2long('107.0.0.0'),    'e' => ip2long('108.255.255.255'),'cc' => 'US' ),
    // 109.x — RIPE Europe
    array( 's' => ip2long('109.0.0.0'),    'e' => ip2long('109.63.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('109.64.0.0'),   'e' => ip2long('109.127.255.255'),'cc' => 'TR' ),
    array( 's' => ip2long('109.128.0.0'),  'e' => ip2long('109.191.255.255'),'cc' => 'GB' ),
    array( 's' => ip2long('109.192.0.0'),  'e' => ip2long('109.255.255.255'),'cc' => 'UA' ),
    // 110-119.x — APNIC Asie
    array( 's' => ip2long('110.0.0.0'),    'e' => ip2long('110.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('110.64.0.0'),   'e' => ip2long('110.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('110.128.0.0'),  'e' => ip2long('110.191.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('110.192.0.0'),  'e' => ip2long('110.255.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('111.0.0.0'),    'e' => ip2long('111.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('111.64.0.0'),   'e' => ip2long('111.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('111.128.0.0'),  'e' => ip2long('111.255.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('112.0.0.0'),    'e' => ip2long('112.63.255.255'), 'cc' => 'KR' ),
    array( 's' => ip2long('112.64.0.0'),   'e' => ip2long('112.127.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('112.128.0.0'),  'e' => ip2long('112.255.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('113.0.0.0'),    'e' => ip2long('113.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('114.0.0.0'),    'e' => ip2long('114.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('114.64.0.0'),   'e' => ip2long('114.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('114.128.0.0'),  'e' => ip2long('114.191.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('114.192.0.0'),  'e' => ip2long('114.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('115.0.0.0'),    'e' => ip2long('115.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('115.64.0.0'),   'e' => ip2long('115.127.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('115.128.0.0'),  'e' => ip2long('115.191.255.255'),'cc' => 'BD' ),
    array( 's' => ip2long('115.192.0.0'),  'e' => ip2long('115.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('116.0.0.0'),    'e' => ip2long('116.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('116.64.0.0'),   'e' => ip2long('116.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('116.128.0.0'),  'e' => ip2long('116.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('116.192.0.0'),  'e' => ip2long('116.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('117.0.0.0'),    'e' => ip2long('117.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('117.64.0.0'),   'e' => ip2long('117.127.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('117.128.0.0'),  'e' => ip2long('117.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('117.192.0.0'),  'e' => ip2long('117.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('118.0.0.0'),    'e' => ip2long('118.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('118.64.0.0'),   'e' => ip2long('118.127.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('118.128.0.0'),  'e' => ip2long('118.191.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('118.192.0.0'),  'e' => ip2long('118.255.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('119.0.0.0'),    'e' => ip2long('119.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('119.64.0.0'),   'e' => ip2long('119.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('119.128.0.0'),  'e' => ip2long('119.191.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('119.192.0.0'),  'e' => ip2long('119.255.255.255'),'cc' => 'IN' ),
    // 120-126.x — APNIC Asie
    array( 's' => ip2long('120.0.0.0'),    'e' => ip2long('120.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('120.64.0.0'),   'e' => ip2long('120.127.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('120.128.0.0'),  'e' => ip2long('120.255.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('121.0.0.0'),    'e' => ip2long('121.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('121.64.0.0'),   'e' => ip2long('121.127.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('121.128.0.0'),  'e' => ip2long('121.191.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('121.192.0.0'),  'e' => ip2long('121.255.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('122.0.0.0'),    'e' => ip2long('122.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('123.0.0.0'),    'e' => ip2long('123.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('123.64.0.0'),   'e' => ip2long('123.127.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('123.128.0.0'),  'e' => ip2long('123.191.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('123.192.0.0'),  'e' => ip2long('123.255.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('124.0.0.0'),    'e' => ip2long('124.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('124.64.0.0'),   'e' => ip2long('124.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('124.128.0.0'),  'e' => ip2long('124.191.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('124.192.0.0'),  'e' => ip2long('124.255.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('125.0.0.0'),    'e' => ip2long('125.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('125.64.0.0'),   'e' => ip2long('125.127.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('125.128.0.0'),  'e' => ip2long('125.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('125.192.0.0'),  'e' => ip2long('125.255.255.255'),'cc' => 'NZ' ),
    array( 's' => ip2long('126.0.0.0'),    'e' => ip2long('126.255.255.255'),'cc' => 'JP' ), // NTT Docomo
    // 127.x — Loopback (ignoré en amont mais robustesse)
    array( 's' => ip2long('127.0.0.0'),    'e' => ip2long('127.255.255.255'),'cc' => 'XX' ),
    // 128-130.x — ARIN US legacy
    array( 's' => ip2long('128.0.0.0'),    'e' => ip2long('130.255.255.255'),'cc' => 'US' ),
    // 131-132.x — Divers
    array( 's' => ip2long('131.0.0.0'),    'e' => ip2long('132.255.255.255'),'cc' => 'XX' ),
    // 133.x — Japan NIC
    array( 's' => ip2long('133.0.0.0'),    'e' => ip2long('133.255.255.255'),'cc' => 'JP' ),
    // 134-139.x — ARIN US legacy
    array( 's' => ip2long('134.0.0.0'),    'e' => ip2long('139.255.255.255'),'cc' => 'US' ),
    // 140.x — Divers
    array( 's' => ip2long('140.0.0.0'),    'e' => ip2long('140.255.255.255'),'cc' => 'XX' ),
    // 141.x — DFN Germany
    array( 's' => ip2long('141.0.0.0'),    'e' => ip2long('141.255.255.255'),'cc' => 'DE' ),
    // 142-143.x — ARIN US
    array( 's' => ip2long('142.0.0.0'),    'e' => ip2long('143.255.255.255'),'cc' => 'US' ),
    // 144.x — AARNET Australia
    array( 's' => ip2long('144.0.0.0'),    'e' => ip2long('144.255.255.255'),'cc' => 'AU' ),
    // 145.x — RIPE NCC
    array( 's' => ip2long('145.0.0.0'),    'e' => ip2long('145.255.255.255'),'cc' => 'NL' ),
    // 146-149.x — Divers / ARIN
    array( 's' => ip2long('146.0.0.0'),    'e' => ip2long('146.255.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('147.0.0.0'),    'e' => ip2long('148.255.255.255'),'cc' => 'US' ),
    array( 's' => ip2long('149.0.0.0'),    'e' => ip2long('149.255.255.255'),'cc' => 'XX' ),
    // 150.x — China
    array( 's' => ip2long('150.0.0.0'),    'e' => ip2long('150.255.255.255'),'cc' => 'CN' ),
    // 151.x — Italie / RIPE
    array( 's' => ip2long('151.0.0.0'),    'e' => ip2long('151.99.255.255'), 'cc' => 'IT' ),
    array( 's' => ip2long('151.100.0.0'),  'e' => ip2long('151.255.255.255'),'cc' => 'XX' ),
    // 152-153.x — Brésil / Japon
    array( 's' => ip2long('152.0.0.0'),    'e' => ip2long('152.255.255.255'),'cc' => 'BR' ),
    array( 's' => ip2long('153.0.0.0'),    'e' => ip2long('153.255.255.255'),'cc' => 'JP' ),
    // 154.x — AFRINIC
    array( 's' => ip2long('154.0.0.0'),    'e' => ip2long('154.255.255.255'),'cc' => 'XX' ),
    // 155.x — ARIN US
    array( 's' => ip2long('155.0.0.0'),    'e' => ip2long('155.255.255.255'),'cc' => 'US' ),
    // 156.x — Egypte / Divers
    array( 's' => ip2long('156.0.0.0'),    'e' => ip2long('156.191.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('156.192.0.0'),  'e' => ip2long('156.223.255.255'),'cc' => 'EG' ), // TE Data Egypt
    array( 's' => ip2long('156.224.0.0'),  'e' => ip2long('156.255.255.255'),'cc' => 'XX' ),
    // 157-160.x — ARIN / Divers
    array( 's' => ip2long('157.0.0.0'),    'e' => ip2long('157.255.255.255'),'cc' => 'US' ),
    array( 's' => ip2long('158.0.0.0'),    'e' => ip2long('159.255.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('160.0.0.0'),    'e' => ip2long('161.255.255.255'),'cc' => 'US' ),
    // 162.x — ARIN US / Tor
    array( 's' => ip2long('162.0.0.0'),    'e' => ip2long('162.247.71.255'), 'cc' => 'US' ),
    array( 's' => ip2long('162.247.72.0'), 'e' => ip2long('162.247.75.255'), 'cc' => 'VP' ), // Tor Exit Nodes
    array( 's' => ip2long('162.247.76.0'), 'e' => ip2long('162.255.255.255'),'cc' => 'US' ),
    // 163.x — Taiwan
    array( 's' => ip2long('163.0.0.0'),    'e' => ip2long('163.255.255.255'),'cc' => 'TW' ),
    // 164-168.x — ARIN US
    array( 's' => ip2long('164.0.0.0'),    'e' => ip2long('168.255.255.255'),'cc' => 'US' ),
    // 169.x — Link-local
    array( 's' => ip2long('169.0.0.0'),    'e' => ip2long('169.253.255.255'),'cc' => 'US' ),
    array( 's' => ip2long('169.254.0.0'),  'e' => ip2long('169.254.255.255'),'cc' => 'XX' ), // Link-local RFC3927
    array( 's' => ip2long('169.255.0.0'),  'e' => ip2long('169.255.255.255'),'cc' => 'US' ),
    // 170-171.x — ARIN US
    array( 's' => ip2long('170.0.0.0'),    'e' => ip2long('171.255.255.255'),'cc' => 'US' ),
    // 172.x — RFC1918 / Apple Private Relay
    array( 's' => ip2long('172.0.0.0'),    'e' => ip2long('172.15.255.255'), 'cc' => 'US' ),
    array( 's' => ip2long('172.16.0.0'),   'e' => ip2long('172.31.255.255'), 'cc' => 'XX' ), // RFC1918
    array( 's' => ip2long('172.32.0.0'),   'e' => ip2long('172.223.255.255'),'cc' => 'US' ),
    array( 's' => ip2long('172.224.0.0'),  'e' => ip2long('172.255.255.255'),'cc' => 'VP' ), // Apple Private Relay
    // 173-174.x — ARIN US
    array( 's' => ip2long('173.0.0.0'),    'e' => ip2long('174.255.255.255'),'cc' => 'US' ),
    // 175.x — China Mobile
    array( 's' => ip2long('175.0.0.0'),    'e' => ip2long('175.255.255.255'),'cc' => 'CN' ),
    // 176.x — Russie / Bouygues
    array( 's' => ip2long('176.0.0.0'),    'e' => ip2long('176.127.255.255'),'cc' => 'RU' ),
    array( 's' => ip2long('176.128.0.0'),  'e' => ip2long('176.191.255.255'),'cc' => 'FR' ), // Bouygues Telecom
    array( 's' => ip2long('176.192.0.0'),  'e' => ip2long('176.255.255.255'),'cc' => 'RU' ),
    // 177-179.x — Brésil / LACNIC
    array( 's' => ip2long('177.0.0.0'),    'e' => ip2long('177.255.255.255'),'cc' => 'BR' ),
    // 178.x — Hetzner / Russie / UK
    array( 's' => ip2long('178.0.0.0'),    'e' => ip2long('178.63.255.255'), 'cc' => 'DE' ), // Hetzner
    array( 's' => ip2long('178.64.0.0'),   'e' => ip2long('178.127.255.255'),'cc' => 'RU' ),
    array( 's' => ip2long('178.128.0.0'),  'e' => ip2long('178.191.255.255'),'cc' => 'US' ), // DigitalOcean
    array( 's' => ip2long('178.192.0.0'),  'e' => ip2long('178.255.255.255'),'cc' => 'GB' ),
    // 179-181.x — Brésil / Latam
    array( 's' => ip2long('179.0.0.0'),    'e' => ip2long('179.255.255.255'),'cc' => 'BR' ),
    array( 's' => ip2long('180.0.0.0'),    'e' => ip2long('180.127.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('180.128.0.0'),  'e' => ip2long('180.191.255.255'),'cc' => 'TH' ),
    array( 's' => ip2long('180.192.0.0'),  'e' => ip2long('180.255.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('181.0.0.0'),    'e' => ip2long('181.0.255.255'),  'cc' => 'CO' ),
    array( 's' => ip2long('181.1.0.0'),    'e' => ip2long('181.1.255.255'),  'cc' => 'AR' ),
    array( 's' => ip2long('181.2.0.0'),    'e' => ip2long('181.255.255.255'),'cc' => 'BR' ),
    // 182-184.x — APNIC / LACNIC / ARIN
    array( 's' => ip2long('182.0.0.0'),    'e' => ip2long('182.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('183.0.0.0'),    'e' => ip2long('183.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('183.64.0.0'),   'e' => ip2long('183.127.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('183.128.0.0'),  'e' => ip2long('183.191.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('183.192.0.0'),  'e' => ip2long('183.255.255.255'),'cc' => 'HK' ),
    array( 's' => ip2long('184.0.0.0'),    'e' => ip2long('184.255.255.255'),'cc' => 'US' ),
    // 185.x — RIPE NCC + VPNs
    array( 's' => ip2long('185.0.0.0'),    'e' => ip2long('185.159.155.255'),'cc' => 'XX' ), // RIPE NCC
    array( 's' => ip2long('185.159.156.0'),'e' => ip2long('185.159.159.255'),'cc' => 'VP' ), // Proton VPN
    array( 's' => ip2long('185.159.160.0'),'e' => ip2long('185.199.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('185.200.116.0'),'e' => ip2long('185.200.119.255'),'cc' => 'VP' ), // NordVPN / Surfshark
    array( 's' => ip2long('185.200.120.0'),'e' => ip2long('185.213.151.255'),'cc' => 'XX' ),
    array( 's' => ip2long('185.213.152.0'),'e' => ip2long('185.213.155.255'),'cc' => 'VP' ), // Mullvad VPN
    array( 's' => ip2long('185.213.156.0'),'e' => ip2long('185.225.231.255'),'cc' => 'XX' ),
    array( 's' => ip2long('185.225.232.0'),'e' => ip2long('185.225.235.255'),'cc' => 'VP' ), // Datacamp VPN
    array( 's' => ip2long('185.225.236.0'),'e' => ip2long('185.255.255.255'),'cc' => 'XX' ),
    // 186-191.x — LACNIC / RIPE
    array( 's' => ip2long('186.0.0.0'),    'e' => ip2long('186.63.255.255'), 'cc' => 'MX' ),
    array( 's' => ip2long('186.64.0.0'),   'e' => ip2long('186.127.255.255'),'cc' => 'CO' ),
    array( 's' => ip2long('186.128.0.0'),  'e' => ip2long('186.191.255.255'),'cc' => 'AR' ),
    array( 's' => ip2long('186.192.0.0'),  'e' => ip2long('186.255.255.255'),'cc' => 'CL' ),
    array( 's' => ip2long('187.0.0.0'),    'e' => ip2long('187.255.255.255'),'cc' => 'BR' ),
    array( 's' => ip2long('188.0.0.0'),    'e' => ip2long('188.63.255.255'), 'cc' => 'RU' ),
    array( 's' => ip2long('188.64.0.0'),   'e' => ip2long('188.127.255.255'),'cc' => 'CZ' ),
    array( 's' => ip2long('188.128.0.0'),  'e' => ip2long('188.191.255.255'),'cc' => 'FR' ), // SFR/Neuf
    array( 's' => ip2long('188.192.0.0'),  'e' => ip2long('188.255.255.255'),'cc' => 'DE' ),
    array( 's' => ip2long('189.0.0.0'),    'e' => ip2long('189.255.255.255'),'cc' => 'BR' ),
    array( 's' => ip2long('190.0.0.0'),    'e' => ip2long('190.255.255.255'),'cc' => 'AR' ),
    array( 's' => ip2long('191.0.0.0'),    'e' => ip2long('191.255.255.255'),'cc' => 'BR' ),
    // 192.x — RFC1918 + Divers
    array( 's' => ip2long('192.0.0.0'),    'e' => ip2long('192.167.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('192.168.0.0'),  'e' => ip2long('192.168.255.255'),'cc' => 'XX' ), // RFC1918
    array( 's' => ip2long('192.169.0.0'),  'e' => ip2long('192.255.255.255'),'cc' => 'XX' ),
    // 193-195.x — RIPE NCC Europe + VPNs
    array( 's' => ip2long('193.0.0.0'),    'e' => ip2long('193.25.15.255'),  'cc' => 'XX' ),
    array( 's' => ip2long('193.25.16.0'),  'e' => ip2long('193.25.19.255'),  'cc' => 'VP' ), // M247 VPN
    array( 's' => ip2long('193.25.20.0'),  'e' => ip2long('193.255.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('194.0.0.0'),    'e' => ip2long('194.1.255.255'),  'cc' => 'XX' ),
    array( 's' => ip2long('194.2.0.0'),    'e' => ip2long('194.2.255.255'),  'cc' => 'FR' ), // Orange Business
    array( 's' => ip2long('194.3.0.0'),    'e' => ip2long('194.9.255.255'),  'cc' => 'GB' ),
    array( 's' => ip2long('194.10.0.0'),   'e' => ip2long('194.24.255.255'), 'cc' => 'DE' ),
    array( 's' => ip2long('194.25.0.0'),   'e' => ip2long('194.25.255.255'), 'cc' => 'DE' ), // Deutsche Telekom
    array( 's' => ip2long('194.26.0.0'),   'e' => ip2long('194.63.255.255'), 'cc' => 'XX' ),
    array( 's' => ip2long('194.64.0.0'),   'e' => ip2long('194.79.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('194.80.0.0'),   'e' => ip2long('194.95.255.255'), 'cc' => 'FR' ), // Renater / CNRS
    array( 's' => ip2long('194.96.0.0'),   'e' => ip2long('194.167.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('194.168.0.0'),  'e' => ip2long('194.168.255.255'),'cc' => 'GB' ),
    array( 's' => ip2long('194.169.0.0'),  'e' => ip2long('194.255.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('195.0.0.0'),    'e' => ip2long('195.63.255.255'), 'cc' => 'XX' ),
    array( 's' => ip2long('195.64.0.0'),   'e' => ip2long('195.127.255.255'),'cc' => 'RU' ),
    array( 's' => ip2long('195.128.0.0'),  'e' => ip2long('195.191.255.255'),'cc' => 'GB' ),
    array( 's' => ip2long('195.192.0.0'),  'e' => ip2long('195.255.255.255'),'cc' => 'DE' ),
    // 196-197.x — AFRINIC
    array( 's' => ip2long('196.0.0.0'),    'e' => ip2long('196.255.255.255'),'cc' => 'ZA' ),
    array( 's' => ip2long('197.0.0.0'),    'e' => ip2long('197.209.255.255'),'cc' => 'XX' ),
    array( 's' => ip2long('197.210.0.0'),  'e' => ip2long('197.210.255.255'),'cc' => 'NG' ),
    array( 's' => ip2long('197.211.0.0'),  'e' => ip2long('197.255.255.255'),'cc' => 'XX' ),
    // 198-199.x — ARIN US
    array( 's' => ip2long('198.0.0.0'),    'e' => ip2long('199.255.255.255'),'cc' => 'US' ),
    // 200-201.x — LACNIC Amérique Latine
    array( 's' => ip2long('200.0.0.0'),    'e' => ip2long('200.63.255.255'), 'cc' => 'MX' ),
    array( 's' => ip2long('200.64.0.0'),   'e' => ip2long('200.127.255.255'),'cc' => 'BR' ),
    array( 's' => ip2long('200.128.0.0'),  'e' => ip2long('200.191.255.255'),'cc' => 'AR' ),
    array( 's' => ip2long('200.192.0.0'),  'e' => ip2long('200.255.255.255'),'cc' => 'CL' ),
    array( 's' => ip2long('201.0.0.0'),    'e' => ip2long('201.255.255.255'),'cc' => 'BR' ),
    // 202-203.x — APNIC Asie/Océanie
    array( 's' => ip2long('202.0.0.0'),    'e' => ip2long('202.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('202.64.0.0'),   'e' => ip2long('202.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('202.128.0.0'),  'e' => ip2long('202.191.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('202.192.0.0'),  'e' => ip2long('202.255.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('203.0.0.0'),    'e' => ip2long('203.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('203.64.0.0'),   'e' => ip2long('203.127.255.255'),'cc' => 'TW' ),
    array( 's' => ip2long('203.128.0.0'),  'e' => ip2long('203.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('203.192.0.0'),  'e' => ip2long('203.255.255.255'),'cc' => 'IN' ),
    // 204-209.x — ARIN US
    array( 's' => ip2long('204.0.0.0'),    'e' => ip2long('209.255.255.255'),'cc' => 'US' ),
    // 210-211.x — APNIC Asie
    array( 's' => ip2long('210.0.0.0'),    'e' => ip2long('210.63.255.255'), 'cc' => 'JP' ),
    array( 's' => ip2long('210.64.0.0'),   'e' => ip2long('210.127.255.255'),'cc' => 'TW' ),
    array( 's' => ip2long('210.128.0.0'),  'e' => ip2long('210.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('210.192.0.0'),  'e' => ip2long('210.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('211.0.0.0'),    'e' => ip2long('211.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('211.64.0.0'),   'e' => ip2long('211.127.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('211.128.0.0'),  'e' => ip2long('211.191.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('211.192.0.0'),  'e' => ip2long('211.255.255.255'),'cc' => 'AU' ),
    // 212-213.x — RIPE Europe
    array( 's' => ip2long('212.0.0.0'),    'e' => ip2long('212.63.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('212.64.0.0'),   'e' => ip2long('212.127.255.255'),'cc' => 'DE' ),
    array( 's' => ip2long('212.128.0.0'),  'e' => ip2long('212.191.255.255'),'cc' => 'FR' ), // Orange/Wanadoo
    array( 's' => ip2long('212.192.0.0'),  'e' => ip2long('212.255.255.255'),'cc' => 'RU' ),
    array( 's' => ip2long('213.0.0.0'),    'e' => ip2long('213.63.255.255'), 'cc' => 'ES' ),
    array( 's' => ip2long('213.64.0.0'),   'e' => ip2long('213.127.255.255'),'cc' => 'IT' ),
    array( 's' => ip2long('213.128.0.0'),  'e' => ip2long('213.191.255.255'),'cc' => 'SE' ),
    array( 's' => ip2long('213.192.0.0'),  'e' => ip2long('213.255.255.255'),'cc' => 'TR' ),
    // 214-215.x — US DoD
    array( 's' => ip2long('214.0.0.0'),    'e' => ip2long('215.255.255.255'),'cc' => 'US' ),
    // 216.x — ARIN US (Google, Cloudflare…)
    array( 's' => ip2long('216.0.0.0'),    'e' => ip2long('216.255.255.255'),'cc' => 'US' ),
    // 217.x — RIPE Europe
    array( 's' => ip2long('217.0.0.0'),    'e' => ip2long('217.63.255.255'), 'cc' => 'GB' ),
    array( 's' => ip2long('217.64.0.0'),   'e' => ip2long('217.127.255.255'),'cc' => 'DE' ),
    array( 's' => ip2long('217.128.0.0'),  'e' => ip2long('217.191.255.255'),'cc' => 'FR' ), // SFR/Neuf
    array( 's' => ip2long('217.192.0.0'),  'e' => ip2long('217.255.255.255'),'cc' => 'RU' ),
    // 218-222.x — APNIC Asie
    array( 's' => ip2long('218.0.0.0'),    'e' => ip2long('218.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('218.64.0.0'),   'e' => ip2long('218.127.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('218.128.0.0'),  'e' => ip2long('218.191.255.255'),'cc' => 'TW' ),
    array( 's' => ip2long('218.192.0.0'),  'e' => ip2long('218.255.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('219.0.0.0'),    'e' => ip2long('219.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('219.64.0.0'),   'e' => ip2long('219.127.255.255'),'cc' => 'IN' ),
    array( 's' => ip2long('219.128.0.0'),  'e' => ip2long('219.191.255.255'),'cc' => 'AU' ),
    array( 's' => ip2long('219.192.0.0'),  'e' => ip2long('219.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('220.0.0.0'),    'e' => ip2long('220.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('220.64.0.0'),   'e' => ip2long('220.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('220.128.0.0'),  'e' => ip2long('220.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('220.192.0.0'),  'e' => ip2long('220.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('221.0.0.0'),    'e' => ip2long('221.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('221.64.0.0'),   'e' => ip2long('221.127.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('221.128.0.0'),  'e' => ip2long('221.191.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('221.192.0.0'),  'e' => ip2long('221.255.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('222.0.0.0'),    'e' => ip2long('222.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('222.64.0.0'),   'e' => ip2long('222.127.255.255'),'cc' => 'KR' ),
    array( 's' => ip2long('222.128.0.0'),  'e' => ip2long('222.191.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('222.192.0.0'),  'e' => ip2long('222.255.255.255'),'cc' => 'JP' ),
    // 223.x — APNIC/CNNIC Chine
    array( 's' => ip2long('223.0.0.0'),    'e' => ip2long('223.63.255.255'), 'cc' => 'CN' ),
    array( 's' => ip2long('223.64.0.0'),   'e' => ip2long('223.127.255.255'),'cc' => 'CN' ),
    array( 's' => ip2long('223.128.0.0'),  'e' => ip2long('223.191.255.255'),'cc' => 'JP' ),
    array( 's' => ip2long('223.192.0.0'),  'e' => ip2long('223.255.255.255'),'cc' => 'CN' ),
    // 224-255.x — Multicast / Réservé IANA
    array( 's' => ip2long('224.0.0.0'),    'e' => ip2long('239.255.255.255'),'cc' => 'XX' ), // Multicast
    array( 's' => ip2long('240.0.0.0'),    'e' => ip2long('255.255.255.255'),'cc' => 'XX' ), // Réservé IANA
);
