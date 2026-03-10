<?php
/**
 * Visitor detail view template — Advanced Stats.
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$t_sessions = $wpdb->prefix . 'aa_sessions';
$t_hits     = $wpdb->prefix . 'aa_hits';

// Accepte visitor_hash (nouveau) ou session_id (rétrocompat.)
$visitor_hash = isset( $_GET['visitor_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['visitor_hash'] ) ) : '';
$session_id   = isset( $_GET['session_id'] )   ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) )   : '';

if ( empty( $visitor_hash ) && ! empty( $session_id ) ) {
    // Rétrocompatibilité : on résout le visitor_hash depuis la session
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $visitor_hash = $wpdb->get_var( $wpdb->prepare(
        "SELECT visitor_hash FROM {$t_sessions} WHERE session_id = %s",
        $session_id
    ) );
}

if ( empty( $visitor_hash ) ) {
    echo '<div class="wrap"><h2>Erreur</h2><p>Identifiant visiteur manquant.</p></div>';
    return;
}

// Toutes les sessions du visiteur, de la plus récente à la plus ancienne
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$sessions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$t_sessions} WHERE visitor_hash = %s ORDER BY ended_at DESC",
    $visitor_hash
) );

if ( empty( $sessions ) ) {
    echo '<div class="wrap"><h2>Erreur</h2><p>Visiteur introuvable.</p></div>';
    return;
}

// Offset du fuseau horaire du site pour convertir hit_at (UTC) en heure locale
$tz_offset_seconds = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

// Reusable function to calculate time ago
if ( ! function_exists( 'aa_time_ago' ) ) {
    function aa_time_ago( $datetime ) {
        $time = strtotime( $datetime . ' UTC' );
        $diff = time() - $time;
        if ( $diff < 60 ) return 'Il y a ' . $diff . ' s';
        if ( $diff < 3600 ) return 'Il y a ' . floor( $diff / 60 ) . ' min';
        if ( $diff < 86400 ) return 'Il y a ' . floor( $diff / 3600 ) . ' h';
        return 'Il y a ' . floor( $diff / 86400 ) . ' j';
    }
}

// Totaux agrégés sur toutes les sessions
$total_pages    = array_sum( array_column( $sessions, 'page_count' ) );
$total_dur_raw  = array_sum( array_map( function( $s ) {
    return ( ! empty( $s->engagement_time ) && $s->engagement_time > 0 ) ? (int) $s->engagement_time : (int) $s->duration;
}, $sessions ) );
$first_session  = end( $sessions );
$last_session   = reset( $sessions );
?>
<div class="wrap aa-wrap">

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=always-analytics' ) ); ?>" class="button" style="margin-bottom:20px;">
        &larr; Retour au tableau de bord
    </a>

    <div class="aa-card">
        <div class="aa-card-header">
            <h2>
                Visiteur <?php echo esc_html( substr( $visitor_hash, 0, 8 ) ); ?>
            </h2>
            <div>
                <span class="aa-badge"><?php echo esc_html( $last_session->country_code ?: '🌐' ); ?></span>
                <span class="aa-badge"><?php echo esc_html( $last_session->device_type ); ?></span>
                <span class="aa-badge"><?php echo count( $sessions ); ?> visite(s)</span>
            </div>
        </div>

        <div class="aa-card-body">
            <!-- Totaux agrégés -->
            <div style="display:flex;gap:40px;margin-bottom:30px;padding:20px;background:#f8f9fc;border-radius:8px;">
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Pages vues (total)</strong>
                    <div style="font-size:24px;font-weight:600;color:#0f172a;"><?php echo (int) $total_pages; ?></div>
                </div>
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Durée totale</strong>
                    <div style="font-size:24px;font-weight:600;color:#0f172a;">
                        <?php
                        $m = floor( $total_dur_raw / 60 );
                        $s = $total_dur_raw % 60;
                        echo esc_html( ( $m > 0 ? $m . 'm ' : '' ) . $s . 's' );
                        ?>
                    </div>
                </div>
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Première visite</strong>
                    <div style="font-size:20px;font-weight:600;color:#0f172a;margin-top:4px;">
                        <?php echo esc_html( aa_time_ago( $first_session->started_at ) ); ?>
                    </div>
                </div>
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Dernière activité</strong>
                    <div style="font-size:20px;font-weight:600;color:#0f172a;margin-top:4px;">
                        <?php echo esc_html( aa_time_ago( $last_session->ended_at ) ); ?>
                    </div>
                </div>
            </div>

            <!-- Liste de toutes les sessions -->
            <?php foreach ( $sessions as $sess_index => $session ) :
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $hits = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$t_hits} WHERE session_id = %s AND is_superseded = 0 ORDER BY hit_at ASC",
                    $session->session_id
                ) );
                $sess_dur_raw = ( ! empty( $session->engagement_time ) && $session->engagement_time > 0 )
                    ? (int) $session->engagement_time
                    : (int) $session->duration;
                $sess_m = floor( $sess_dur_raw / 60 );
                $sess_s = $sess_dur_raw % 60;
            ?>
            <div style="margin-bottom:32px;">
                <h3 style="margin-bottom:8px;display:flex;align-items:center;gap:12px;">
                    <?php echo $sess_index === 0 ? '🟢 ' : ''; ?>
                    Visite <?php echo esc_html( count( $sessions ) - $sess_index ); ?>
                    <small style="font-weight:400;color:#64748b;font-size:13px;">
                        — <?php echo esc_html( aa_time_ago( $session->started_at ) ); ?>
                        &nbsp;·&nbsp; <?php echo esc_html( ( $sess_m > 0 ? $sess_m . 'm ' : '' ) . $sess_s . 's' ); ?>
                        &nbsp;·&nbsp; <?php echo (int) $session->page_count; ?> page(s)
                    </small>
                </h3>

                <div style="border-left:2px solid #e2e8f0;margin-left:8px;padding-left:20px;">
                    <?php if ( empty( $hits ) ) : ?>
                        <p style="color:#94a3b8;font-style:italic;">Aucune page enregistre pour cette session.</p>
                    <?php endif; ?>
                    <?php foreach ( $hits as $index => $hit ) : ?>
                        <div style="position:relative;margin-bottom:24px;">
                            <!-- Timeline Dot -->
                            <div style="position:absolute;left:-29px;top:4px;width:16px;height:16px;border-radius:50%;background:#ffffff;border:3px solid #6c63ff;"></div>

                            <div style="color:#64748b;font-size:13px;margin-bottom:4px;">
                                <?php echo esc_html( gmdate( 'H:i:s', strtotime( $hit->hit_at ) + $tz_offset_seconds ) ); ?>
                            </div>

                            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:6px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                <div style="font-weight:600;color:#0f172a;margin-bottom:4px;">
                                    <?php echo esc_html( $hit->page_title ?: $hit->page_url ); ?>
                                </div>
                                <div style="color:#64748b;font-size:13px;word-break:break-all;">
                                    <a href="<?php echo esc_url( $hit->page_url ); ?>" target="_blank" style="text-decoration:none;color:#3b82f6;">
                                        <?php echo esc_html( $hit->page_url ); ?>
                                    </a>
                                </div>

                                <?php if ( $index === 0 && ! empty( $hit->referrer ) ) : ?>
                                    <div style="margin-top:12px;padding-top:12px;border-top:1px dashed #e2e8f0;color:#64748b;font-size:13px;">
                                        <span class="dashicons dashicons-external" style="font-size:14px;line-height:1;width:14px;height:14px;margin-right:4px;"></span>
                                        Source : <?php echo esc_html( $hit->referrer ); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ( ! empty( $hit->utm_source ) ) : ?>
                                    <div style="margin-top:8px;display:flex;gap:8px;">
                                        <span style="background:#f1f5f9;color:#475569;font-size:11px;padding:2px 6px;border-radius:4px;font-weight:600;text-transform:uppercase;">
                                            UTM Source: <?php echo esc_html( $hit->utm_source ); ?>
                                        </span>
                                        <?php if ( $hit->utm_medium ) : ?>
                                        <span style="background:#f1f5f9;color:#475569;font-size:11px;padding:2px 6px;border-radius:4px;font-weight:600;text-transform:uppercase;">
                                            UTM Medium: <?php echo esc_html( $hit->utm_medium ); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>


</div>
