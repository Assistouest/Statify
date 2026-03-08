<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cache layer — DÉSACTIVÉ TOTALEMENT.
 *
 * Toutes les méthodes exécutent le callback directement sans jamais
 * lire ou écrire dans un cache (transients, object cache, Redis, Memcached).
 * Les données sont toujours lues en direct depuis la base de données.
 */
class Statify_Cache {

    public static function get( $key ) {
        return false;
    }

    public static function set( $key, $value, $expiration = 300 ) {
        // intentionnellement vide — zéro cache
    }

    public static function delete( $key ) {
        // intentionnellement vide
    }

    public static function invalidate_group( $group ) {
        // intentionnellement vide
    }

    public static function ttl_for_period( $to_date ) {
        return 0; // toujours live
    }

    /**
     * Exécute TOUJOURS le callback directement. Jamais de cache.
     */
    public static function remember( $key, $callback, $expiration = 300 ) {
        global $wpdb;
        $wpdb->flush(); // vide le cache interne $wpdb
        return call_user_func( $callback );
    }
}
