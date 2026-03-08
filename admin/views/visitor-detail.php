<?php
/**
 * Visitor detail view template — Advanced Stats.
 *
 * @package Statify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

if ( empty( $session_id ) ) {
    echo '<div class="wrap"><h2>Erreur</h2><p>Identifiant de session manquant.</p></div>';
    return;
}

global $wpdb;
$t_sessions = $wpdb->prefix . 'statify_sessions';
$t_hits     = $wpdb->prefix . 'statify_hits';

// Fetch Session
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$session = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$t_sessions} WHERE session_id = %s",
    $session_id
) );

if ( ! $session ) {
    echo '<div class="wrap"><h2>Erreur</h2><p>Session introuvable.</p></div>';
    return;
}

// Fetch Hits for this session, chronological order
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$hits = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$t_hits} WHERE session_id = %s ORDER BY hit_at ASC",
    $session_id
) );

// Reusable function to calculate time ago
function statify_time_ago( $datetime ) {
    $time = strtotime( $datetime . ' UTC' );
    $diff = time() - $time;
    if ( $diff < 60 ) return 'Il y a ' . $diff . ' s';
    if ( $diff < 3600 ) return 'Il y a ' . floor( $diff / 60 ) . ' min';
    if ( $diff < 86400 ) return 'Il y a ' . floor( $diff / 3600 ) . ' h';
    return 'Il y a ' . floor( $diff / 86400 ) . ' j';
}
?>
<div class="wrap statify-wrap">
    
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=statify' ) ); ?>" class="button" style="margin-bottom:20px;">
        &larr; Retour au tableau de bord
    </a>

    <div class="statify-card">
        <div class="statify-card-header">
            <h2>
                Visiteur <?php echo esc_html( substr( $session->visitor_hash, 0, 8 ) ); ?>
            </h2>
            <div>
                <span class="statify-badge"><?php echo esc_html( $session->country_code ?: '🌐' ); ?></span>
                <span class="statify-badge"><?php echo esc_html( $session->device_type ); ?></span>
            </div>
        </div>
        
        <div class="statify-card-body">
            <div style="display:flex;gap:40px;margin-bottom:30px;padding:20px;background:#f8f9fc;border-radius:8px;">
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Pages vues</strong>
                    <div style="font-size:24px;font-weight:600;color:#0f172a;"><?php echo (int) $session->page_count; ?></div>
                </div>
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Durée</strong>
                    <div style="font-size:24px;font-weight:600;color:#0f172a;">
                        <?php 
                        $display_dur = ( ! empty( $session->engagement_time ) && $session->engagement_time > 0 )
                            ? (int) $session->engagement_time
                            : (int) $session->duration;
                        $m = floor( $display_dur / 60 );
                        $s = $display_dur % 60;
                        echo esc_html( ( $m > 0 ? $m . 'm ' : '' ) . $s . 's' ); 
                        ?>
                    </div>
                </div>
                <div>
                    <strong style="color:#64748b;display:block;font-size:12px;text-transform:uppercase;">Dernière activité</strong>
                    <div style="font-size:20px;font-weight:600;color:#0f172a;margin-top:4px;">
                        <?php echo esc_html( statify_time_ago( $session->ended_at ) ); ?>
                    </div>
                </div>
            </div>

            <h3 style="margin-bottom:16px;">Parcours détaillé</h3>
            
            <div style="border-left:2px solid #e2e8f0;margin-left:8px;padding-left:20px;">
                <?php foreach ( $hits as $index => $hit ) : ?>
                    <div style="position:relative;margin-bottom:24px;">
                        <!-- Timeline Dot -->
                        <div style="position:absolute;left:-29px;top:4px;width:16px;height:16px;border-radius:50%;background:#ffffff;border:3px solid #6c63ff;"></div>
                        
                        <div style="color:#64748b;font-size:13px;margin-bottom:4px;">
                            <?php echo esc_html( gmdate( 'H:i:s', strtotime( $hit->hit_at ) ) ); ?>
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
    </div>
</div>
