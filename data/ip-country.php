<?php
/**
 * IP-to-Country lookup table (sample).
 *
 * This is a placeholder structure. To populate it with real data:
 * 1. Download the free DB-IP Lite CSV: https://db-ip.com/db/download/ip-to-country-lite
 * 2. Run the included converter script: php data/convert-csv.php
 *
 * Format: array of [ 's' => start_ip_long, 'e' => end_ip_long, 'cc' => 'XX' ]
 * Sorted by 's' for binary search.
 *
 * @return array
 */
return array(
    // Sample entries (a tiny subset for demonstration)
    array( 's' => ip2long( '1.0.0.0' ),    'e' => ip2long( '1.0.0.255' ),    'cc' => 'AU' ),
    array( 's' => ip2long( '1.0.1.0' ),    'e' => ip2long( '1.0.3.255' ),    'cc' => 'CN' ),
    array( 's' => ip2long( '1.0.4.0' ),    'e' => ip2long( '1.0.7.255' ),    'cc' => 'AU' ),
    array( 's' => ip2long( '1.1.1.0' ),    'e' => ip2long( '1.1.1.255' ),    'cc' => 'AU' ),
    array( 's' => ip2long( '2.0.0.0' ),    'e' => ip2long( '2.15.255.255' ),  'cc' => 'FR' ),
    array( 's' => ip2long( '2.16.0.0' ),   'e' => ip2long( '2.16.255.255' ),  'cc' => 'GB' ),
    array( 's' => ip2long( '5.39.0.0' ),   'e' => ip2long( '5.39.127.255' ),  'cc' => 'FR' ),
    array( 's' => ip2long( '8.8.8.0' ),    'e' => ip2long( '8.8.8.255' ),     'cc' => 'US' ),
    array( 's' => ip2long( '8.8.4.0' ),    'e' => ip2long( '8.8.4.255' ),     'cc' => 'US' ),
    array( 's' => ip2long( '77.136.0.0' ), 'e' => ip2long( '77.136.255.255' ),'cc' => 'FR' ),
    array( 's' => ip2long( '80.10.0.0' ),  'e' => ip2long( '80.10.255.255' ), 'cc' => 'FR' ),
    array( 's' => ip2long( '91.68.0.0' ),  'e' => ip2long( '91.68.255.255' ), 'cc' => 'FR' ),
    array( 's' => ip2long( '176.0.0.0' ),  'e' => ip2long( '176.0.255.255' ), 'cc' => 'DE' ),
    array( 's' => ip2long( '185.0.0.0' ),  'e' => ip2long( '185.0.255.255' ), 'cc' => 'NL' ),
    array( 's' => ip2long( '192.168.0.0' ), 'e' => ip2long( '192.168.255.255' ), 'cc' => 'XX' ), // Private range
    array( 's' => ip2long( '212.27.32.0' ), 'e' => ip2long( '212.27.63.255' ), 'cc' => 'FR' ),
);
