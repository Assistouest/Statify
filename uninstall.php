<?php
/**
 * Uninstall handler — removes all plugin data.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$options = get_option( 'statify_options', array() );

if ( ! empty( $options['delete_on_uninstall'] ) ) {
    global $wpdb;

    // Drop custom tables (new names)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}statify_hits" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}statify_sessions" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}statify_daily" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}statify_scroll" );

    // Also drop legacy advstats_* tables in case migration never ran
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_hits" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_sessions" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_daily" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}advstats_scroll" );

    // Delete options (new names)
    delete_option( 'statify_options' );
    delete_option( 'statify_db_version' );
    delete_option( 'statify_db_schema_version' );

    // Delete legacy options
    delete_option( 'advstats_options' );
    delete_option( 'advstats_db_version' );
    delete_option( 'advstats_db_schema_version' );

    // Delete transients (both prefixes)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_statify_%' OR option_name LIKE '_transient_timeout_statify_%' OR option_name LIKE '_transient_advstats_%' OR option_name LIKE '_transient_timeout_advstats_%'"
    );
}
