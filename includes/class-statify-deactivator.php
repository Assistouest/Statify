<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin deactivator — cleans up scheduled tasks.
 */
class Statify_Deactivator {

    public static function deactivate() {
        // Clear current cron hooks
        wp_clear_scheduled_hook( 'statify_daily_aggregate' );
        wp_clear_scheduled_hook( 'statify_daily_purge' );
        wp_clear_scheduled_hook( 'statify_expire_sessions' );

        // Clear legacy cron hooks (advstats_ prefix) in case they still exist
        wp_clear_scheduled_hook( 'advstats_daily_aggregate' );
        wp_clear_scheduled_hook( 'advstats_daily_purge' );
        wp_clear_scheduled_hook( 'advstats_expire_sessions' );
    }
}
