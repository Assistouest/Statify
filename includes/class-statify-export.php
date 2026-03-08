<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Export handler — generates CSV and JSON reports.
 */
class Statify_Export {

    /**
     * Generate an export based on the given parameters.
     *
     * @param array  $params Filter parameters (from, to, post_type, device, country).
     * @param string $format 'csv' or 'json'.
     * @return string File content.
     */
    public static function generate( $params, $format = 'csv' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'statify_hits';
        $where = array( '1=1' );
        $args  = array();

        if ( ! empty( $params['from'] ) ) {
            $where[] = 'hit_at >= %s';
            $args[]  = sanitize_text_field( $params['from'] ) . ' 00:00:00';
        }

        if ( ! empty( $params['to'] ) ) {
            $where[] = 'hit_at <= %s';
            $args[]  = sanitize_text_field( $params['to'] ) . ' 23:59:59';
        }

        if ( ! empty( $params['post_type'] ) ) {
            $where[] = 'post_type = %s';
            $args[]  = sanitize_key( $params['post_type'] );
        }

        if ( ! empty( $params['device'] ) ) {
            $where[] = 'device_type = %s';
            $args[]  = sanitize_text_field( $params['device'] );
        }

        if ( ! empty( $params['country'] ) ) {
            $where[] = 'country_code = %s';
            $args[]  = sanitize_text_field( $params['country'] );
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT
            hit_at, page_url, page_title, post_type,
            referrer_domain, utm_source, utm_medium, utm_campaign,
            device_type, browser, os, country_code, city,
            is_new_visitor, screen_width, screen_height
        FROM {$table}
        WHERE {$where_clause}
        ORDER BY hit_at DESC
        LIMIT 50000";

        if ( ! empty( $args ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $query, ARRAY_A );
        }

        if ( empty( $results ) ) {
            $results = array();
        }

        /**
         * Filter export data before generation.
         *
         * @param array  $results The result data.
         * @param string $format  The format (csv/json).
         */
        $results = apply_filters( 'statify_export_data', $results, $format );

        if ( 'json' === $format ) {
            return self::to_json( $results );
        }

        return self::to_csv( $results );
    }

    /**
     * Convert results to CSV string.
     */
    private static function to_csv( $results ) {
        if ( empty( $results ) ) {
            return '';
        }

        ob_start();
        $output = fopen( 'php://output', 'w' );

        // Headers
        fputcsv( $output, array_keys( $results[0] ) );

        // Data rows
        foreach ( $results as $row ) {
            fputcsv( $output, $row );
        }

        fclose( $output );
        return ob_get_clean();
    }

    /**
     * Convert results to JSON string.
     */
    private static function to_json( $results ) {
        return wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Send export as a file download.
     *
     * @param array  $params Filter parameters.
     * @param string $format 'csv' or 'json'.
     */
    public static function download( $params, $format = 'csv' ) {
        $content   = self::generate( $params, $format );
        $extension = ( 'json' === $format ) ? 'json' : 'csv';
        $mime_type = ( 'json' === $format ) ? 'application/json' : 'text/csv';
        $filename  = 'statify-export-' . gmdate( 'Y-m-d-His' ) . '.' . $extension;

        header( 'Content-Type: ' . $mime_type . '; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        if ( 'csv' === $format ) {
            echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8
        }

        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
