<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Geolocation handler — resolves IP addresses to geographic locations.
 * Supports native (bundled db-ip lite) and MaxMind GeoLite2 providers.
 */
class Statify_Geolocation {

    private $provider;
    private $options;

    /**
     * @param string $provider 'native' or 'maxmind'.
     * @param array  $options  Plugin options.
     */
    public function __construct( $provider = 'native', $options = array() ) {
        $this->provider = $provider;
        $this->options  = $options;
    }

    /**
     * Lookup geographic data for an IP address.
     *
     * @param string $ip The IP address.
     * @return array { country_code, region, city }
     */
    public function lookup( $ip ) {
        $default = array(
            'country_code' => '',
            'region'       => '',
            'city'         => '',
        );

        if ( empty( $ip ) || '0.0.0.0' === $ip || '127.0.0.1' === $ip || '::1' === $ip ) {
            return $default;
        }

        // Check cache first
        $cache_key = 'statify_geo_' . md5( $ip );
        $cached    = Statify_Cache::get( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $result = $default;

        switch ( $this->provider ) {
            case 'maxmind':
                $result = $this->lookup_maxmind( $ip );
                break;
            case 'native':
            default:
                $result = $this->lookup_native( $ip );
                break;
        }

        // Cache for 24 hours (IPs don't change geo often)
        Statify_Cache::set( $cache_key, $result, DAY_IN_SECONDS );

        return $result;
    }

    /**
     * Native geolocation using bundled IP database.
     * Uses the DB-IP Lite CSV format (free, no key required).
     * Fallback: country-only via the bundled PHP lookup table.
     *
     * @param string $ip The IP address.
     * @return array
     */
    private function lookup_native( $ip ) {
        $default = array( 'country_code' => '', 'region' => '', 'city' => '' );

        // Try the bundled PHP lookup table for country-level resolution
        $country_file = STATIFY_PLUGIN_DIR . 'data/ip-country.php';
        if ( file_exists( $country_file ) ) {
            $lookup_table = include $country_file;
            if ( is_array( $lookup_table ) ) {
                $country = $this->binary_search_country( $ip, $lookup_table );
                if ( $country ) {
                    return array(
                        'country_code' => $country,
                        'region'       => '',
                        'city'         => '',
                    );
                }
            }
        }

        // Fallback: try ip-api.com (free, no key, cached aggressively)
        $result = $this->lookup_ip_api( $ip );
        if ( ! empty( $result['country_code'] ) ) {
            return $result;
        }

        return $default;
    }

    /**
     * Binary search in the IP-to-country lookup table.
     *
     * @param string $ip    The IP address.
     * @param array  $table Array of [ 'start' => long, 'end' => long, 'cc' => 'XX' ].
     * @return string|false Country code or false.
     */
    private function binary_search_country( $ip, $table ) {
        $ip_long = ip2long( $ip );
        if ( false === $ip_long ) {
            return false;
        }

        $low  = 0;
        $high = count( $table ) - 1;

        while ( $low <= $high ) {
            $mid = intdiv( $low + $high, 2 );
            if ( $ip_long < $table[ $mid ]['s'] ) {
                $high = $mid - 1;
            } elseif ( $ip_long > $table[ $mid ]['e'] ) {
                $low = $mid + 1;
            } else {
                return $table[ $mid ]['cc'];
            }
        }

        return false;
    }

    /**
     * Fallback: lookup via ip-api.com (free, no registration, 45 req/min).
     *
     * @param string $ip The IP address.
     * @return array
     */
    private function lookup_ip_api( $ip ) {
        $default = array( 'country_code' => '', 'region' => '', 'city' => '' );

        // Rate limit: 1 request per second
        $rate_key = 'statify_geo_rate';
        if ( false !== get_transient( $rate_key ) ) {
            return $default;
        }
        set_transient( $rate_key, 1, 2 );

        $url      = 'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=status,countryCode,regionName,city';
        $response = wp_remote_get( $url, array(
            'timeout'   => 3,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return $default;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body ) || 'success' !== ( $body['status'] ?? '' ) ) {
            return $default;
        }

        return array(
            'country_code' => isset( $body['countryCode'] ) ? sanitize_text_field( $body['countryCode'] ) : '',
            'region'       => isset( $body['regionName'] ) ? sanitize_text_field( $body['regionName'] ) : '',
            'city'         => isset( $body['city'] ) ? sanitize_text_field( $body['city'] ) : '',
        );
    }

    /**
     * MaxMind GeoLite2 lookup.
     *
     * @param string $ip The IP address.
     * @return array
     */
    private function lookup_maxmind( $ip ) {
        $default = array( 'country_code' => '', 'region' => '', 'city' => '' );

        $db_path = ! empty( $this->options['maxmind_db_path'] )
            ? $this->options['maxmind_db_path']
            : STATIFY_PLUGIN_DIR . 'data/GeoLite2-City.mmdb';

        if ( ! file_exists( $db_path ) ) {
            // Fallback to native if MaxMind DB not found
            return $this->lookup_native( $ip );
        }

        try {
            // Use the maxminddb PHP extension if available
            if ( extension_loaded( 'maxminddb' ) ) {
                $reader = new \MaxMind\Db\Reader( $db_path );
                $record = $reader->get( $ip );
                $reader->close();
            } else {
                // Use the PHP pure reader (must be installed via composer)
                if ( ! class_exists( '\\MaxMind\\Db\\Reader' ) ) {
                    $autoload = STATIFY_PLUGIN_DIR . 'vendor/autoload.php';
                    if ( file_exists( $autoload ) ) {
                        require_once $autoload;
                    } else {
                        return $this->lookup_native( $ip );
                    }
                }
                $reader = new \MaxMind\Db\Reader( $db_path );
                $record = $reader->get( $ip );
                $reader->close();
            }

            if ( empty( $record ) ) {
                return $default;
            }

            return array(
                'country_code' => $record['country']['iso_code'] ?? '',
                'region'       => $record['subdivisions'][0]['names']['en'] ?? '',
                'city'         => $record['city']['names']['en'] ?? '',
            );
        } catch ( \Exception $e ) {
            return $this->lookup_native( $ip );
        }
    }
}
