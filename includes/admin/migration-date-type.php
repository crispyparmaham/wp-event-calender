<?php
defined( 'ABSPATH' ) || exit;

/**
 * Einmalige Migration: event_date_type für bestehende Posts setzen.
 *
 * Phase 1 (v3.1): Legacy-Felder → event_date_type (single/multiple/recurring)
 * Phase 2 (v3.2): 'multiple' → 'single' mit Repeater-Erhalt
 *   - Erster event_dates Eintrag → start_date / start_time / end_time
 *   - Restliche Einträge bleiben im Repeater
 *   - event_date_type wird auf 'single' gesetzt
 */

// ── Phase 1: Legacy → event_date_type ─────────────────────────────
add_action( 'admin_init', 'tc_migrate_event_date_type' );

function tc_migrate_event_date_type() {
    if ( get_option( 'tc_date_type_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $existing = get_field( 'event_date_type', $post_id );
        if ( ! empty( $existing ) ) {
            continue;
        }

        $is_recurring    = (bool) get_field( 'is_recurring', $post_id );
        $event_dates_raw = get_field( 'event_dates', $post_id );
        $has_repeater    = ! empty( $event_dates_raw ) && is_array( $event_dates_raw );

        if ( $is_recurring ) {
            $date_type = 'recurring';
        } elseif ( $has_repeater ) {
            $date_type = 'multiple';
        } else {
            $date_type = 'single';
        }

        update_field( 'event_date_type', $date_type, $post_id );
    }

    update_option( 'tc_date_type_migrated', '1', true );
    tc_clear_events_cache();
}

// ── Phase 2: 'multiple' → 'single' + Repeater-Erhalt ─────────────
add_action( 'admin_init', 'tc_migrate_multiple_to_single' );

function tc_migrate_multiple_to_single() {
    if ( get_option( 'tc_date_type_v2_migrated' ) ) {
        return;
    }

    $posts = get_posts( array(
        'post_type'      => 'time_event',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $post_id ) {
        $date_type = get_field( 'event_date_type', $post_id );
        if ( $date_type !== 'multiple' ) {
            continue;
        }

        $event_dates = get_field( 'event_dates', $post_id );
        if ( ! empty( $event_dates ) && is_array( $event_dates ) ) {
            // Erster Eintrag wird zum Haupttermin
            $first = $event_dates[0];

            if ( ! empty( $first['date_start'] ) ) {
                update_field( 'start_date', $first['date_start'], $post_id );
            }
            if ( ! empty( $first['time_start'] ) ) {
                update_field( 'start_time', $first['time_start'], $post_id );
            }
            if ( ! empty( $first['time_end'] ) ) {
                update_field( 'end_time', $first['time_end'], $post_id );
            }

            // Ersten Eintrag aus Repeater entfernen, Rest behalten
            array_shift( $event_dates );
            if ( ! empty( $event_dates ) ) {
                update_field( 'event_dates', $event_dates, $post_id );
            } else {
                update_field( 'event_dates', array(), $post_id );
            }
        }

        update_field( 'event_date_type', 'single', $post_id );
    }

    update_option( 'tc_date_type_v2_migrated', '1', true );
    tc_clear_events_cache();
}
