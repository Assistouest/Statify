<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Session manager — gère les sessions visiteur en temps réel.
 *
 * Un "hit" = une page vue. Une "session" = un groupe de hits liés.
 * Engagement = page_count > 1 OU durée >= 10 s.
 */
class Statify_Session {

    /**
     * Crée ou met à jour une session à chaque hit de page.
     */
    public static function update_session( $session_id, $hit_data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'statify_sessions';
        $now   = current_time( 'mysql', true ); // UTC

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT session_id, page_count, started_at, max_scroll_depth FROM {$table} WHERE session_id = %s",
            $session_id
        ) );

        if ( null === $existing ) {
            // Nouvelle session
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert( $table, array(
                'session_id'       => $session_id,
                'visitor_hash'     => $hit_data['visitor_hash'],
                'started_at'       => $now,
                'ended_at'         => $now,
                'duration'         => 0,
                'page_count'       => 1,
                'entry_page'       => $hit_data['page_url'],
                'exit_page'        => $hit_data['page_url'],
                'referrer'         => $hit_data['referrer'],
                'device_type'      => $hit_data['device_type'],
                'country_code'     => $hit_data['country_code'],
                'is_bounce'        => 1,    // bounce par défaut
                'max_scroll_depth' => 0,
                'engagement_time'  => 0,
            ) );
        } else {
            $page_count = (int) $existing->page_count + 1;
            $duration   = max( 0, (int) ( strtotime( $now ) - strtotime( $existing->started_at ) ) );
            // Engagé si > 1 page ou > 10 s sur le site
            $is_bounce  = ( $page_count > 1 || $duration >= 10 ) ? 0 : 1;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $table,
                array(
                    'ended_at'   => $now,
                    'duration'   => $duration,
                    'page_count' => $page_count,
                    'exit_page'  => $hit_data['page_url'],
                    'is_bounce'  => $is_bounce,
                ),
                array( 'session_id' => $session_id ),
                array( '%s', '%d', '%d', '%s', '%d' ),
                array( '%s' )
            );
        }
    }

    /**
     * Heartbeat ping : met à jour durée, engagement, et max_scroll_depth.
     *
     * v4 : accepte clientDuration et engagementTime envoyés par le tracker JS.
     * La durée retenue est max(durée_serveur, clientDuration) pour fiabilité.
     *
     * @param string   $session_id      L'ID de session.
     * @param int|null $scroll_depth    Profondeur de scroll actuelle (0-100).
     * @param int|null $client_duration Durée totale côté client (secondes).
     * @param int|null $engagement_time Temps d'engagement côté client (secondes, page visible uniquement).
     */
    public static function ping_session( $session_id, $scroll_depth = null, $client_duration = null, $engagement_time = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'statify_sessions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT started_at, duration, is_bounce, max_scroll_depth, engagement_time FROM {$table} WHERE session_id = %s",
            $session_id
        ) );

        if ( ! $existing ) {
            return;
        }

        $now              = current_time( 'mysql', true );
        $server_duration  = max( 0, (int) ( strtotime( $now ) - strtotime( $existing->started_at ) ) );

        // Durée retenue = max entre serveur et client (le client peut être plus fiable
        // car il mesure depuis le DOMContentLoaded exact, pas depuis le premier hit SQL)
        $duration = $server_duration;
        if ( null !== $client_duration ) {
            $cd = absint( $client_duration );
            // Plafond anti-triche : 24h max
            if ( $cd > 0 && $cd <= 86400 ) {
                $duration = max( $duration, $cd );
            }
        }

        // Ne jamais rétrograder la durée
        $duration = max( (int) $existing->duration, $duration );

        $update = array(
            'ended_at' => $now,
            'duration' => $duration,
        );
        $format = array( '%s', '%d' );

        // Engagement : engagé si durée >= 10s (seuil réduit grâce au sendBeacon)
        // ou si déjà marqué comme engagé
        if ( $duration >= 10 && 1 === (int) $existing->is_bounce ) {
            $update['is_bounce'] = 0;
            $format[]            = '%d';
        }

        // Engagement time (temps page visible)
        if ( null !== $engagement_time ) {
            $et = absint( $engagement_time );
            if ( $et > 0 && $et <= 86400 && $et > (int) $existing->engagement_time ) {
                $update['engagement_time'] = $et;
                $format[]                  = '%d';
            }
        }

        // Mise à jour max_scroll_depth
        if ( null !== $scroll_depth ) {
            $depth = absint( $scroll_depth );
            if ( $depth > (int) $existing->max_scroll_depth ) {
                $update['max_scroll_depth'] = $depth;
                $format[]                   = '%d';
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $table,
            $update,
            array( 'session_id' => $session_id ),
            $format,
            array( '%s' )
        );
    }

    /**
     * Expire les sessions sans activité depuis 30 min.
     * v4 : estime une durée minimale pour les sessions orphelines (duration=0)
     * qui ont au moins un hit mais n'ont jamais reçu de ping.
     */
    public static function expire_stale_sessions() {
        global $wpdb;
        $table_sess = $wpdb->prefix . 'statify_sessions';
        $table_hits = $wpdb->prefix . 'statify_hits';
        $cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) );

        // Sessions orphelines : duration=0, aucun ping reçu, plus de 30 min d'ancienneté.
        // Deux cas distincts :
        //   1. page_count > 1 OU scroll >= 25 → engagement réel mesurable, on estime la durée.
        //   2. page_count = 1 ET scroll = 0   → visiteur non-traçable (< 1%), on laisse à 0.
        //      Ces sessions comptent uniquement comme page vue, exclues des métriques d'engagement.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_sess}
             SET duration = CASE
                    WHEN page_count > 1 THEN GREATEST(5, TIMESTAMPDIFF(SECOND, started_at, ended_at))
                    WHEN max_scroll_depth > 0 THEN 5
                    ELSE 0
                 END,
                 is_bounce = CASE
                    WHEN page_count > 1 OR max_scroll_depth >= 25 THEN 0
                    ELSE is_bounce
                 END
             WHERE ended_at < %s
               AND duration = 0
               AND page_count >= 1",
            $cutoff
        ) );
    }
}
